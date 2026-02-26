<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Batch;

use CatalogCodex\JsonRpc\Contract\BatchExecutorInterface;
use RuntimeException;

/**
 * Coroutine-based executor for concurrent batch handling.
 *
 * Each task runs in its own coroutine and writes result by original index,
 * so output order stays stable even if execution finishes out of order.
 */
final class OpenSwooleCoroutineBatchExecutor implements BatchExecutorInterface
{
    public function execute(array $tasks): array
    {
        if ($tasks === []) {
            return [];
        }

        if (!class_exists(\OpenSwoole\Coroutine::class) || !class_exists(\OpenSwoole\Coroutine\Channel::class)) {
            throw new RuntimeException('OpenSwoole is required for OpenSwooleCoroutineBatchExecutor');
        }

        $taskCount = count($tasks);
        $results = array_fill(0, $taskCount, null);

        $runner = static function () use ($tasks, $taskCount, &$results): void {
            $doneChannel = new \OpenSwoole\Coroutine\Channel($taskCount);

            foreach ($tasks as $index => $task) {
                \OpenSwoole\Coroutine::create(static function () use ($task, $index, &$results, $doneChannel): void {
                    try {
                        $results[$index] = $task();
                    } finally {
                        $doneChannel->push(true);
                    }
                });
            }

            for ($i = 0; $i < $taskCount; ++$i) {
                $doneChannel->pop();
            }
        };

        if (\OpenSwoole\Coroutine::getCid() > 0) {
            $runner();
            return $results;
        }

        \OpenSwoole\Coroutine\run($runner);
        return $results;
    }
}
