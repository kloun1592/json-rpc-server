<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Tests\Unit;

use CatalogCodex\JsonRpc\Batch\SequentialBatchExecutor;
use CatalogCodex\JsonRpc\Contract\RpcMiddlewareInterface;
use CatalogCodex\JsonRpc\Contract\RpcRequestHandlerInterface;
use CatalogCodex\JsonRpc\Dispatcher;
use CatalogCodex\JsonRpc\JsonRpcServer;
use CatalogCodex\JsonRpc\MethodRegistry;
use CatalogCodex\JsonRpc\Protocol\Request;
use PHPUnit\Framework\TestCase;

final class MiddlewarePipelineTest extends TestCase
{
    public function testMiddlewareCanWrapResult(): void
    {
        $registry = (new MethodRegistry())
            ->register('math.add', static fn (int $a, int $b): int => $a + $b);

        $middleware = new class () implements RpcMiddlewareInterface {
            public function process(Request $request, RpcRequestHandlerInterface $handler): mixed
            {
                $result = $handler->handle($request);
                return $result * 2;
            }
        };

        $server = new JsonRpcServer(
            dispatcher: new Dispatcher($registry),
            batchExecutor: new SequentialBatchExecutor(),
            middlewares: [$middleware],
        );

        $raw = $server->handle('{"jsonrpc":"2.0","method":"math.add","params":[2,3],"id":1}');
        $data = json_decode((string) $raw, true);

        self::assertSame(10, $data['result']);
    }

    public function testMiddlewareCanAlterRequest(): void
    {
        $registry = (new MethodRegistry())
            ->register('math.add', static fn (int $a, int $b): int => $a + $b);

        $middleware = new class () implements RpcMiddlewareInterface {
            public function process(Request $request, RpcRequestHandlerInterface $handler): mixed
            {
                if ($request->method !== 'math.add') {
                    return $handler->handle($request);
                }

                return $handler->handle(new Request(
                    method: $request->method,
                    params: [10, 5],
                    id: $request->id,
                    hasId: $request->hasId,
                ));
            }
        };

        $server = new JsonRpcServer(
            dispatcher: new Dispatcher($registry),
            batchExecutor: new SequentialBatchExecutor(),
            middlewares: [$middleware],
        );

        $raw = $server->handle('{"jsonrpc":"2.0","method":"math.add","params":[1,1],"id":1}');
        $data = json_decode((string) $raw, true);

        self::assertSame(15, $data['result']);
    }

    public function testMiddlewareOrderIsDeterministic(): void
    {
        $trace = new \ArrayObject();

        $makeMiddleware = static function (string $name) use ($trace): RpcMiddlewareInterface {
            return new class ($name, $trace) implements RpcMiddlewareInterface {
                public function __construct(
                    private string $name,
                    private \ArrayObject $trace,
                ) {
                }

                public function process(Request $request, RpcRequestHandlerInterface $handler): mixed
                {
                    $this->trace->append($this->name . '-before');
                    $result = $handler->handle($request);
                    $this->trace->append($this->name . '-after');

                    return $result;
                }
            };
        };

        $registry = (new MethodRegistry())
            ->register('system.ping', static fn (): string => 'pong');

        $server = new JsonRpcServer(
            dispatcher: new Dispatcher($registry),
            middlewares: [
                $makeMiddleware('first'),
                $makeMiddleware('second'),
            ],
        );

        $server->handle('{"jsonrpc":"2.0","method":"system.ping","id":1}');

        self::assertSame(
            ['first-before', 'second-before', 'second-after', 'first-after'],
            $trace->getArrayCopy(),
        );
    }

    public function testMiddlewareCanShortCircuit(): void
    {
        $calls = 0;

        $registry = (new MethodRegistry())
            ->register('system.ping', static function () use (&$calls): string {
                $calls++;
                return 'pong';
            });

        $middleware = new class () implements RpcMiddlewareInterface {
            public function process(Request $request, RpcRequestHandlerInterface $handler): mixed
            {
                return 'short-circuit';
            }
        };

        $server = new JsonRpcServer(
            dispatcher: new Dispatcher($registry),
            middlewares: [$middleware],
        );

        $raw = $server->handle('{"jsonrpc":"2.0","method":"system.ping","id":1}');
        $data = json_decode((string) $raw, true);

        self::assertSame('short-circuit', $data['result']);
        self::assertSame(0, $calls);
    }
}
