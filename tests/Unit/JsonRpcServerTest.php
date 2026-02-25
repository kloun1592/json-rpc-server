<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Tests\Unit;

use CatalogCodex\JsonRpc\Batch\SequentialBatchExecutor;
use CatalogCodex\JsonRpc\Dispatcher;
use CatalogCodex\JsonRpc\Exception\JsonRpcException;
use CatalogCodex\JsonRpc\JsonRpcServer;
use CatalogCodex\JsonRpc\MethodRegistry;
use CatalogCodex\JsonRpc\Protocol\ErrorCode;
use PHPUnit\Framework\TestCase;

final class JsonRpcServerTest extends TestCase
{
    public function testParseError(): void
    {
        $server = $this->createServer();

        $raw = $server->handle('{invalid}');
        $data = json_decode((string) $raw, true);

        self::assertSame('2.0', $data['jsonrpc']);
        self::assertSame(ErrorCode::PARSE_ERROR, $data['error']['code']);
        self::assertNull($data['id']);
    }

    public function testSuccessfulRequest(): void
    {
        $server = $this->createServer();

        $raw = $server->handle('{"jsonrpc":"2.0","method":"math.add","params":[2,3],"id":1}');
        $data = json_decode((string) $raw, true);

        self::assertSame('2.0', $data['jsonrpc']);
        self::assertSame(1, $data['id']);
        self::assertSame(5, $data['result']);
        self::assertArrayNotHasKey('error', $data);
    }

    public function testNotificationReturnsNull(): void
    {
        $server = $this->createServer();

        $raw = $server->handle('{"jsonrpc":"2.0","method":"system.ping"}');

        self::assertNull($raw);
    }

    public function testMethodNotFound(): void
    {
        $server = $this->createServer();

        $raw = $server->handle('{"jsonrpc":"2.0","method":"unknown.method","id":"abc"}');
        $data = json_decode((string) $raw, true);

        self::assertSame(ErrorCode::METHOD_NOT_FOUND, $data['error']['code']);
        self::assertSame('abc', $data['id']);
    }

    public function testInvalidParams(): void
    {
        $server = $this->createServer();

        $raw = $server->handle('{"jsonrpc":"2.0","method":"math.add","params":{"a":1},"id":5}');
        $data = json_decode((string) $raw, true);

        self::assertSame(ErrorCode::INVALID_PARAMS, $data['error']['code']);
        self::assertSame(5, $data['id']);
    }

    public function testApplicationJsonRpcExceptionIsPassedThrough(): void
    {
        $registry = new MethodRegistry();
        $registry->register('demo.error', static function (): never {
            throw new JsonRpcException(-32010, 'Business rule failed', ['domain' => 'demo']);
        });

        $server = new JsonRpcServer(new Dispatcher($registry), new SequentialBatchExecutor());

        $raw = $server->handle('{"jsonrpc":"2.0","method":"demo.error","id":9}');
        $data = json_decode((string) $raw, true);

        self::assertSame(-32010, $data['error']['code']);
        self::assertSame('Business rule failed', $data['error']['message']);
        self::assertSame(['domain' => 'demo'], $data['error']['data']);
        self::assertSame(9, $data['id']);
    }

    public function testBatchWithMixedItems(): void
    {
        $server = $this->createServer();

        $raw = $server->handle('[{"jsonrpc":"2.0","method":"math.add","params":[1,2],"id":1},{"jsonrpc":"2.0","method":"system.ping"},42,{"jsonrpc":"2.0","method":"unknown.method","id":3}]');
        $data = json_decode((string) $raw, true);

        self::assertCount(3, $data);
        self::assertSame(1, $data[0]['id']);
        self::assertSame(3, $data[0]['result']);
        self::assertSame(ErrorCode::INVALID_REQUEST, $data[1]['error']['code']);
        self::assertNull($data[1]['id']);
        self::assertSame(ErrorCode::METHOD_NOT_FOUND, $data[2]['error']['code']);
        self::assertSame(3, $data[2]['id']);
    }

    public function testBatchWithNotificationsOnlyReturnsNull(): void
    {
        $server = $this->createServer();

        $raw = $server->handle('[{"jsonrpc":"2.0","method":"system.ping"},{"jsonrpc":"2.0","method":"system.ping"}]');

        self::assertNull($raw);
    }

    public function testEmptyBatchReturnsInvalidRequestObject(): void
    {
        $server = $this->createServer();

        $raw = $server->handle('[]');
        $data = json_decode((string) $raw, true);

        self::assertSame(ErrorCode::INVALID_REQUEST, $data['error']['code']);
        self::assertNull($data['id']);
    }

    public function testBooleanIdIsInvalidRequest(): void
    {
        $server = $this->createServer();

        $raw = $server->handle('{"jsonrpc":"2.0","method":"system.ping","id":true}');
        $data = json_decode((string) $raw, true);

        self::assertSame(ErrorCode::INVALID_REQUEST, $data['error']['code']);
        self::assertNull($data['id']);
    }

    private function createServer(): JsonRpcServer
    {
        $registry = new MethodRegistry();
        $registry->register('math.add', static fn (int $a, int $b): int => $a + $b);
        $registry->register('system.ping', static fn (): string => 'pong');

        return new JsonRpcServer(
            dispatcher: new Dispatcher($registry),
            batchExecutor: new SequentialBatchExecutor(),
        );
    }
}
