<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Tests\Integration;

use CatalogCodex\JsonRpc\Contract\BatchExecutorInterface;
use CatalogCodex\JsonRpc\Dispatcher;
use CatalogCodex\JsonRpc\JsonRpcServer;
use CatalogCodex\JsonRpc\MethodRegistry;
use PHPUnit\Framework\TestCase;

final class BatchExecutorIntegrationTest extends TestCase
{
    public function testServerUsesConfiguredBatchExecutor(): void
    {
        $registry = new MethodRegistry();
        $registry->register('math.add', static fn (int $a, int $b): int => $a + $b);

        $executor = new class () implements BatchExecutorInterface {
            public int $calls = 0;

            public function execute(array $tasks): array
            {
                $this->calls++;
                $results = [];
                foreach ($tasks as $task) {
                    $results[] = $task();
                }
                return $results;
            }
        };

        $server = new JsonRpcServer(new Dispatcher($registry), $executor);

        $raw = $server->handle('[{"jsonrpc":"2.0","method":"math.add","params":[1,1],"id":1},{"jsonrpc":"2.0","method":"math.add","params":[2,3],"id":2}]');
        $data = json_decode((string) $raw, true);

        self::assertSame(1, $executor->calls);
        self::assertCount(2, $data);
        self::assertSame(2, $data[0]['result']);
        self::assertSame(5, $data[1]['result']);
    }
}
