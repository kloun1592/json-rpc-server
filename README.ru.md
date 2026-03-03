# JSON-RPC 2.0 Server Library (PHP 8.3)

[English version](README.md)

Composer-библиотека для построения JSON-RPC 2.0 серверов на чистом PHP с PSR-архитектурой, тестами и Docker-окружением.

## Возможности

- Полная базовая совместимость с JSON-RPC 2.0:
  - `jsonrpc: "2.0"`
  - single request
  - batch request
  - notification (без ответа)
  - стандартные ошибки `-32700`, `-32600`, `-32601`, `-32602`, `-32603`
- Реестр методов + отдельный dispatcher
- Middleware pipeline для cross-cutting логики (auth, audit, tracing, rate limit)
- Плаггируемый batch executor
  - по умолчанию: последовательный
  - опционально: coroutine-based executor на OpenSwoole
- Unit + integration тесты
- Docker для запуска демо-сервера и тестов

## Установка

```bash
composer require mihailromanov/json-rpc-server
```

## Быстрый старт (как библиотека)

```php
<?php

declare(strict_types=1);

use CatalogCodex\JsonRpc\Batch\SequentialBatchExecutor;
use CatalogCodex\JsonRpc\Dispatcher;
use CatalogCodex\JsonRpc\JsonRpcServer;
use CatalogCodex\JsonRpc\MethodRegistry;

$registry = (new MethodRegistry())
    ->register('math.add', static fn (int $a, int $b): int => $a + $b)
    ->register('system.ping', static fn (): string => 'pong');

$server = new JsonRpcServer(
    dispatcher: new Dispatcher($registry),
    batchExecutor: new SequentialBatchExecutor(),
);

$responseJson = $server->handle('{"jsonrpc":"2.0","method":"math.add","params":[2,3],"id":1}');
```

## Параллельная обработка batch

Для конкурентной обработки batch-запросов можно использовать `OpenSwooleCoroutineBatchExecutor`.

```php
$server = new JsonRpcServer(
    dispatcher: new Dispatcher($registry),
    batchExecutor: new \CatalogCodex\JsonRpc\Batch\OpenSwooleCoroutineBatchExecutor(),
);
```

## Middleware pipeline

Middleware работает вокруг вызова метода и получает `Request` + `RpcRequestHandlerInterface`.

```php
use CatalogCodex\JsonRpc\Contract\RpcMiddlewareInterface;
use CatalogCodex\JsonRpc\Contract\RpcRequestHandlerInterface;
use CatalogCodex\JsonRpc\Protocol\Request;

$timingMiddleware = new class () implements RpcMiddlewareInterface {
    public function process(Request $request, RpcRequestHandlerInterface $handler): mixed
    {
        $startedAt = microtime(true);
        $result = $handler->handle($request);
        $elapsedMs = (microtime(true) - $startedAt) * 1000;

        return [
            'result' => $result,
            'meta' => ['elapsed_ms' => round($elapsedMs, 2)],
        ];
    }
};

$server = new JsonRpcServer(
    dispatcher: new Dispatcher($registry),
    middlewares: [$timingMiddleware],
);
```

## Запуск demo-сервера в Docker

```bash
docker compose up --build rpc
```

RPC endpoint: `POST http://localhost:9501/rpc`

### Пример single request

```bash
curl -s -X POST http://localhost:9501/rpc \
  -H 'content-type: application/json' \
  -d '{"jsonrpc":"2.0","method":"math.add","params":[10,32],"id":1}'
```

Ожидаемый ответ:

```json
{"jsonrpc":"2.0","id":1,"result":42}
```

### Пример batch

```bash
curl -s -X POST http://localhost:9501/rpc \
  -H 'content-type: application/json' \
  -d '[
    {"jsonrpc":"2.0","method":"system.sleep","params":[120],"id":1},
    {"jsonrpc":"2.0","method":"system.sleep","params":[80],"id":2},
    {"jsonrpc":"2.0","method":"system.ping"}
  ]'
```

Уведомление (`system.ping` без `id`) не возвращается в ответ.

## Тесты

```bash
composer test
```

Или через Docker:

```bash
docker compose run --rm test
```

## Структура

- `src/JsonRpcServer.php` - протокольная обработка и оркестрация
- `src/Dispatcher.php` - вызов метода
- `src/MethodRegistry.php` - реестр методов
- `src/Support/CallableInvoker.php` - маппинг positional/named params на callable
- `src/Contract/RpcMiddlewareInterface.php` - контракт middleware
- `src/Contract/RpcRequestHandlerInterface.php` - контракт next-handler
- `src/Middleware/*` - реализация middleware pipeline
- `src/Batch/*` - стратегии batch execution
- `src/Adapter/OpenSwoole/OpenSwooleHttpServer.php` - HTTP адаптер для OpenSwoole
- `tests/*` - unit/integration тесты
