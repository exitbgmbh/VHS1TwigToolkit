<?php

namespace App\Controller;

use App\Mcp\McpServer;
use App\Mcp\Session\McpSessionStore;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MCP endpoint (Streamable HTTP, JSON responses only) under /mcp.
 * Catches everything itself: no warning, path or stack trace ever reaches the response body.
 */
class McpController
{
    private const SESSION_HEADER = 'Mcp-Session-Id';

    private const DEFAULT_ALLOWED_ORIGIN_HOSTS = ['localhost', '127.0.0.1', 'toolkit.blissdev.de'];

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    private McpServer $server;

    private McpSessionStore $store;

    public function __construct(McpServer $server, McpSessionStore $store)
    {
        $this->server = $server;
        $this->store = $store;
    }

    public function handle(Request $request): Response
    {
        $obLevel = ob_get_level();
        ob_start();
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            return $this->_dispatch($request);
        } catch (\Throwable $e) {
            return $this->_jsonRpcError(500, null, McpServer::INTERNAL_ERROR, 'Internal error');
        } finally {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
            restore_error_handler();
        }
    }

    private function _dispatch(Request $request): Response
    {
        if (!$this->_isOriginAllowed($request)) {
            return $this->_jsonRpcError(403, null, McpServer::INVALID_REQUEST, 'Forbidden origin');
        }

        switch ($request->getMethod()) {
            case 'POST':
                return $this->_handlePost($request);
            case 'DELETE':
                return $this->_handleDelete($request);
            default:
                return new Response('', 405, ['Allow' => 'POST, DELETE']);
        }
    }

    private function _handlePost(Request $request): Response
    {
        $body = (string) $request->getContent();
        $message = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->_jsonRpcError(400, null, McpServer::PARSE_ERROR, 'Parse error');
        }

        if (!is_array($message) || str_starts_with(ltrim($body), '[')) {
            // batches and non-object messages are not supported
            return $this->_jsonRpcError(400, null, McpServer::INVALID_REQUEST, 'Invalid Request');
        }

        $isInitialize = ($message['method'] ?? null) === 'initialize' && array_key_exists('id', $message);

        if ($isInitialize) {
            $session = $this->store->create();
        } else {
            $sessionId = (string) $request->headers->get(self::SESSION_HEADER, '');
            if ($sessionId === '') {
                return $this->_jsonRpcError(400, null, McpServer::INVALID_REQUEST, 'Missing Mcp-Session-Id header');
            }

            $session = $this->store->load($sessionId);
            if ($session === null) {
                return $this->_jsonRpcError(404, null, McpServer::SESSION_NOT_FOUND, 'Session not found');
            }
        }

        $result = $this->server->handle($message, $session);
        $this->store->save($session);

        $headers = $isInitialize ? [self::SESSION_HEADER => $session->getId()] : [];

        if ($result === null) {
            return new Response('', 202, $headers);
        }

        return $this->_json(200, $result, $headers);
    }

    private function _handleDelete(Request $request): Response
    {
        $sessionId = (string) $request->headers->get(self::SESSION_HEADER, '');
        if ($sessionId === '') {
            return $this->_jsonRpcError(400, null, McpServer::INVALID_REQUEST, 'Missing Mcp-Session-Id header');
        }

        if (!$this->store->delete($sessionId)) {
            return $this->_jsonRpcError(404, null, McpServer::SESSION_NOT_FOUND, 'Session not found');
        }

        return new Response('', 204);
    }

    /**
     * Protection against DNS rebinding: a present Origin must point to an allowed host.
     */
    private function _isOriginAllowed(Request $request): bool
    {
        if (!$request->headers->has('Origin')) {
            return true;
        }

        $host = parse_url((string) $request->headers->get('Origin'), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        $allowed = self::DEFAULT_ALLOWED_ORIGIN_HOSTS;
        $extra = getenv('MCP_ALLOWED_ORIGIN_HOSTS');
        if (is_string($extra) && $extra !== '') {
            foreach (explode(',', $extra) as $extraHost) {
                $extraHost = strtolower(trim($extraHost));
                if ($extraHost !== '') {
                    $allowed[] = $extraHost;
                }
            }
        }

        return in_array(strtolower($host), $allowed, true);
    }

    private function _jsonRpcError(int $status, string|int|null $id, int $code, string $message): Response
    {
        return $this->_json($status, McpServer::error($id, $code, $message));
    }

    private function _json(int $status, array $payload, array $headers = []): Response
    {
        $json = json_encode($payload, self::JSON_FLAGS);
        if ($json === false) {
            $status = 500;
            $json = json_encode(McpServer::error(null, McpServer::INTERNAL_ERROR, 'Internal error'));
        }

        $response = new JsonResponse($json, $status, $headers, true);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
}
