<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc;

use CatalogCodex\JsonRpc\Exception\MethodNotFoundException;

/**
 * In-memory registry for JSON-RPC method handlers.
 */
final class MethodRegistry
{
    /**
     * @var array<string, callable>
     */
    private array $methods = [];

    /**
     * Register (or overwrite) method handler.
     */
    public function register(string $name, callable $handler): self
    {
        $this->methods[$name] = $handler;
        return $this;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->methods);
    }

    /**
     * @throws MethodNotFoundException
     */
    public function get(string $name): callable
    {
        if (!$this->has($name)) {
            throw new MethodNotFoundException($name);
        }

        return $this->methods[$name];
    }
}
