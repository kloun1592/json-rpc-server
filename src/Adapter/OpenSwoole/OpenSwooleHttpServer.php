<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Adapter\OpenSwoole;

use CatalogCodex\JsonRpc\JsonRpcServer;
use RuntimeException;

/**
 * Thin HTTP transport adapter for JSON-RPC server.
 *
 * Request mapping:
 * - only POST is accepted;
 * - only configured path is accepted;
 * - body is passed directly to transport-agnostic JsonRpcServer.
 */
final class OpenSwooleHttpServer
{
    public function __construct(
        private readonly JsonRpcServer $jsonRpcServer,
        private readonly string $host = '0.0.0.0',
        private readonly int $port = 9501,
        private readonly string $rpcPath = '/rpc',
    ) {
        if (!class_exists(\OpenSwoole\Http\Server::class)) {
            throw new RuntimeException('OpenSwoole is required for OpenSwooleHttpServer');
        }
    }

    public function start(): void
    {
        $server = new \OpenSwoole\Http\Server($this->host, $this->port);

        $server->on('request', function (\OpenSwoole\Http\Request $request, \OpenSwoole\Http\Response $response): void {
            if (!$this->isRpcPath($request)) {
                $response->status(404);
                $response->end('Not Found');
                return;
            }

            if (!$this->isPostMethod($request)) {
                $response->status(405);
                $response->header('Allow', 'POST');
                $response->end('Method Not Allowed');
                return;
            }

            $rawPayload = $request->rawContent() ?: '';
            $rpcResponse = $this->jsonRpcServer->handle($rawPayload);

            if ($rpcResponse === null) {
                // Notification-only request: spec requires no response body.
                $response->status(204);
                $response->end();
                return;
            }

            $response->header('Content-Type', 'application/json');
            $response->end($rpcResponse);
        });

        $server->start();
    }

    private function isRpcPath(\OpenSwoole\Http\Request $request): bool
    {
        $path = $request->server['request_uri'] ?? '/';
        return $path === $this->rpcPath;
    }

    private function isPostMethod(\OpenSwoole\Http\Request $request): bool
    {
        $method = strtoupper($request->server['request_method'] ?? 'GET');
        return $method === 'POST';
    }
}
