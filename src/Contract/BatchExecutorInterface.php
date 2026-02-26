<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Contract;

/**
 * Strategy interface for running batch items.
 *
 * The server builds one task per batch item and delegates execution policy
 * (sequential, coroutine-based, process-based, etc.) to this interface.
 */
interface BatchExecutorInterface
{
    /**
     * Execute tasks and return results in the same order as input.
     *
     * @param list<callable(): mixed> $tasks
     * @return list<mixed>
     */
    public function execute(array $tasks): array;
}
