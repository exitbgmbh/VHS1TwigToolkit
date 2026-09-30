<?php

namespace App\Mcp\Tool;

use App\DependencyInjection\ContainerFactory;
use App\Mcp\Session\McpSession;
use App\Service\ConfigRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Per-request context of a tool call: session, config registry and the containers built for it.
 */
class ToolContext
{
    private McpSession $session;

    private ConfigRegistry $registry;

    /** @var array<string, ContainerBuilder> */
    private array $containers = [];

    public function __construct(McpSession $session, ConfigRegistry $registry)
    {
        $this->session = $session;
        $this->registry = $registry;
    }

    public function getSession(): McpSession
    {
        return $this->session;
    }

    public function getRegistry(): ConfigRegistry
    {
        return $this->registry;
    }

    /**
     * Slug from arguments['config'] or, if not given, from the session.
     *
     * @throws ToolException if no config is selected or the slug is unknown
     */
    public function resolveSlug(array $arguments): string
    {
        $slug = $arguments['config'] ?? null;
        if (!is_string($slug) || $slug === '') {
            $slug = $this->session->getSelectedSlug();
        }

        if ($slug === null || $slug === '') {
            throw new ToolException('no config selected - call select_config or pass "config"');
        }

        if (!$this->registry->exists($slug)) {
            throw new ToolException(sprintf('Unknown config "%s"', $slug));
        }

        return $slug;
    }

    /**
     * Builds the container for a slug once per request.
     */
    public function getContainer(string $slug): ContainerBuilder
    {
        if (!isset($this->containers[$slug])) {
            $this->containers[$slug] = ContainerFactory::createForSlug($slug, $this->registry);
        }

        return $this->containers[$slug];
    }

    /**
     * @return array<string, ContainerBuilder>
     */
    public function getUsedContainers(): array
    {
        return $this->containers;
    }
}
