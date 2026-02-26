<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Batch;

use CatalogCodex\JsonRpc\Contract\BatchExecutorInterface;

/**
 * Baseline batch strategy: process tasks one by one in current process.
 */
final class SequentialBatchExecutor implements BatchExecutorInterface
{
    public function execute(array $tasks): array
    {
        $results = [];

        foreach ($tasks as $task) {
            $results[] = $task();
        }

        return $results;
    }
}
