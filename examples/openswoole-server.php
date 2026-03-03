<?php

declare(strict_types=1);

use CatalogCodex\JsonRpc\Adapter\OpenSwoole\OpenSwooleHttpServer;
use CatalogCodex\JsonRpc\Batch\OpenSwooleCoroutineBatchExecutor;
use CatalogCodex\JsonRpc\Contract\RpcMiddlewareInterface;
use CatalogCodex\JsonRpc\Contract\RpcRequestHandlerInterface;
use CatalogCodex\JsonRpc\Dispatcher;
use CatalogCodex\JsonRpc\JsonRpcServer;
use CatalogCodex\JsonRpc\MethodRegistry;
use CatalogCodex\JsonRpc\Protocol\Request;

require_once __DIR__ . '/../vendor/autoload.php';

// Register a few demo methods with intentionally simple signatures.
$registry = (new MethodRegistry())
    ->register('math.add', static fn (int $a, int $b): int => $a + $b)
    ->register('math.sub', static fn (int $a, int $b): int => $a - $b)
    ->register('system.ping', static fn (): string => 'pong')
    ->register('system.sleep', static function (int $milliseconds): array {
        // Sleep helps demonstrate that batch items can be processed concurrently.
        usleep($milliseconds * 1000);
        return ['slept_ms' => $milliseconds];
    });

// Example middleware that measures method execution time.
$timingMiddleware = new class () implements RpcMiddlewareInterface {
    public function process(Request $request, RpcRequestHandlerInterface $handler): mixed
    {
        $start = microtime(true);
        $result = $handler->handle($request);

        return [
            'value' => $result,
            'meta' => ['duration_ms' => round((microtime(true) - $start) * 1000, 2)],
        ];
    }
};

$server = new JsonRpcServer(
    dispatcher: new Dispatcher($registry),
    batchExecutor: new OpenSwooleCoroutineBatchExecutor(),
    middlewares: [$timingMiddleware],
);

// Expose transport-agnostic JSON-RPC server through OpenSwoole HTTP endpoint.
$httpServer = new OpenSwooleHttpServer($server, host: '0.0.0.0', port: 9501, rpcPath: '/rpc');
$httpServer->start();
