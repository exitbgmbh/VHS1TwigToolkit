<?php

namespace App\Mcp;

use App\Mcp\Session\McpSession;
use App\Mcp\Tool\ArgumentValidator;
use App\Mcp\Tool\ToolContext;
use App\Mcp\Tool\ToolException;
use App\Mcp\Tool\ToolRegistry;
use App\Mcp\Tool\ToolResult;
use App\Service\ConfigRegistry;

/**
 * JSON-RPC 2.0 handler for the MCP methods initialize, ping, tools/list and tools/call.
 */
class McpServer
{
    public const PARSE_ERROR = -32700;
    public const INVALID_REQUEST = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS = -32602;
    public const INTERNAL_ERROR = -32603;
    public const SESSION_NOT_FOUND = -32001;

    public const SUPPORTED_PROTOCOL_VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05'];

    private const SERVER_NAME = 'vhs1-twig-toolkit';
    private const SERVER_VERSION = '0.1.0';

    private const INSTRUCTIONS = 'VHS1 Twig toolkit. Call list_configs, then select_config. '
        . "Data tools use the selected config unless 'config' is passed.";

    private ToolRegistry $registry;

    private ConfigRegistry $configRegistry;

    public function __construct(ToolRegistry $registry, ConfigRegistry $configRegistry)
    {
        $this->registry = $registry;
        $this->configRegistry = $configRegistry;
    }

    /**
     * @return array|null JSON-RPC response, null for notifications and responses
     */
    public function handle(array $message, McpSession $session): ?array
    {
        $hasId = array_key_exists('id', $message);
        $id = $message['id'] ?? null;
        $method = $message['method'] ?? null;

        $isClientResponse = $method === null
            && (array_key_exists('result', $message) || array_key_exists('error', $message));
        if (!$hasId || $isClientResponse) {
            // notifications and client responses never get an answer
            return null;
        }

        if (($message['jsonrpc'] ?? null) !== '2.0' || !is_string($method)
            || !(is_string($id) || is_int($id))
        ) {
            return self::error(is_string($id) || is_int($id) ? $id : null, self::INVALID_REQUEST, 'Invalid Request');
        }

        $params = $message['params'] ?? [];
        if (!is_array($params)) {
            return self::error($id, self::INVALID_PARAMS, 'Invalid params');
        }

        switch ($method) {
            case 'initialize':
                return self::result($id, $this->_initialize($params));
            case 'ping':
                return self::result($id, new \stdClass());
            case 'tools/list':
                return self::result($id, $this->_listTools());
            case 'tools/call':
                return $this->_callTool($id, $params, $session);
            default:
                return self::error($id, self::METHOD_NOT_FOUND, 'Method not found');
        }
    }

    public static function result(string|int|null $id, array|object $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    public static function error(string|int|null $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }

    private function _initialize(array $params): array
    {
        $requested = $params['protocolVersion'] ?? null;
        $version = in_array($requested, self::SUPPORTED_PROTOCOL_VERSIONS, true)
            ? $requested
            : self::SUPPORTED_PROTOCOL_VERSIONS[0];

        return [
            'protocolVersion' => $version,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => self::SERVER_NAME, 'version' => self::SERVER_VERSION],
            'instructions' => self::INSTRUCTIONS,
        ];
    }

    private function _listTools(): array
    {
        $tools = [];
        foreach ($this->registry->all() as $tool) {
            $tools[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema(),
            ];
        }

        return ['tools' => $tools];
    }

    private function _callTool(string|int $id, array $params, McpSession $session): array
    {
        $name = $params['name'] ?? null;
        $tool = is_string($name) ? $this->registry->get($name) : null;
        if ($tool === null) {
            return self::error($id, self::INVALID_PARAMS, 'Unknown tool');
        }

        $arguments = $params['arguments'] ?? [];
        if (!is_array($arguments)) {
            return self::error($id, self::INVALID_PARAMS, 'Invalid params: arguments must be an object');
        }

        $context = new ToolContext($session, $this->configRegistry);

        $validationError = ArgumentValidator::validate($tool->getInputSchema(), $arguments);
        if ($validationError !== null) {
            $result = ToolResult::error($validationError);
        } else {
            try {
                $result = $tool->call($arguments, $context);
            } catch (ToolException $e) {
                $result = ToolResult::error($e->getMessage());
            } catch (\Throwable $e) {
                $shortName = (new \ReflectionClass($e))->getShortName();
                $result = ToolResult::error($shortName . ': ' . $e->getMessage());
            }
        }

        // leak protection: every text of every result
        $containers = $context->getUsedContainers();
        $texts = [];
        foreach ($result->getTexts() as $text) {
            $texts[] = McpOutputGuard::redact($text, $containers);
        }

        return self::result($id, $result->withTexts($texts)->toArray());
    }
}
