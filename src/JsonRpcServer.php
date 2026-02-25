<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc;

use CatalogCodex\JsonRpc\Batch\SequentialBatchExecutor;
use CatalogCodex\JsonRpc\Contract\BatchExecutorInterface;
use CatalogCodex\JsonRpc\Contract\RpcMiddlewareInterface;
use CatalogCodex\JsonRpc\Contract\RpcRequestHandlerInterface;
use CatalogCodex\JsonRpc\Exception\InvalidParamsException;
use CatalogCodex\JsonRpc\Exception\JsonRpcException;
use CatalogCodex\JsonRpc\Exception\MethodNotFoundException;
use CatalogCodex\JsonRpc\Middleware\DispatcherRequestHandler;
use CatalogCodex\JsonRpc\Middleware\MiddlewarePipeline;
use CatalogCodex\JsonRpc\Protocol\ErrorCode;
use CatalogCodex\JsonRpc\Protocol\Request;
use CatalogCodex\JsonRpc\Protocol\Response;
use JsonException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Main JSON-RPC 2.0 server entry point.
 *
 * This class is transport-agnostic: it accepts raw JSON payload and returns
 * a JSON string (or null for notification-only flow).
 */
final class JsonRpcServer
{
    private const VERSION = '2.0';

    private const MESSAGE_PARSE_ERROR = 'Parse error';
    private const MESSAGE_INVALID_REQUEST = 'Invalid Request';
    private const MESSAGE_INVALID_PARAMS = 'Invalid method parameter(s)';
    private const MESSAGE_INTERNAL_ERROR = 'Internal error';

    private const INTERNAL_ERROR_FALLBACK_JSON = '{"jsonrpc":"2.0","error":{"code":-32603,"message":"Internal error"},"id":null}';

    private readonly RpcRequestHandlerInterface $requestHandler;

    /**
     * @param iterable<RpcMiddlewareInterface> $middlewares
     */
    public function __construct(
        Dispatcher $dispatcher,
        private readonly BatchExecutorInterface $batchExecutor = new SequentialBatchExecutor(),
        private readonly ?LoggerInterface $logger = null,
        iterable $middlewares = [],
    ) {
        $this->requestHandler = new MiddlewarePipeline(
            coreHandler: new DispatcherRequestHandler($dispatcher),
            middlewares: $middlewares,
        );
    }

    /**
     * Handle raw JSON-RPC request payload.
     *
     * Returns null when input is notification-only, as required by spec.
     */
    public function handle(string $payload): ?string
    {
        $decoded = $this->decodePayload($payload);

        if ($decoded instanceof Response) {
            return $this->encodeSingle($decoded);
        }

        if (is_array($decoded)) {
            return $this->handleBatch($decoded);
        }

        if (is_object($decoded)) {
            $response = $this->handleRequestValue($decoded);
            return $response === null ? null : $this->encodeSingle($response);
        }

        return $this->encodeSingle($this->invalidRequest());
    }

    /**
     * Decode raw JSON. Parse errors always map to a protocol error response.
     */
    private function decodePayload(string $payload): mixed
    {
        try {
            return json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return Response::error(null, ErrorCode::PARSE_ERROR, self::MESSAGE_PARSE_ERROR);
        }
    }

    /**
     * @param array<int,mixed> $batch
     */
    private function handleBatch(array $batch): ?string
    {
        if ($batch === []) {
            return $this->encodeSingle($this->invalidRequest());
        }

        $tasks = [];
        foreach ($batch as $entry) {
            $tasks[] = fn (): ?Response => $this->handleRequestValue($entry);
        }

        $results = $this->executeBatch($tasks);

        $responsePayload = [];
        foreach ($results as $result) {
            if ($result instanceof Response) {
                $responsePayload[] = $result->toArray();
            }
        }

        if ($responsePayload === []) {
            return null;
        }

        return $this->encode($responsePayload);
    }

    /**
     * @param list<callable(): ?Response> $tasks
     * @return list<?Response>
     */
    private function executeBatch(array $tasks): array
    {
        try {
            return $this->batchExecutor->execute($tasks);
        } catch (Throwable $e) {
            // Batch strategy is pluggable. If custom executor breaks,
            // fail safe by processing requests sequentially.
            $this->logger?->error('Batch executor failed, fallback to sequential executor', ['exception' => $e]);
            return (new SequentialBatchExecutor())->execute($tasks);
        }
    }

    /**
     * Handle a single decoded item (single request or one batch element).
     */
    private function handleRequestValue(mixed $value): ?Response
    {
        if (!is_object($value)) {
            return $this->invalidRequest();
        }

        $requestOrError = $this->toRequest($value);
        if ($requestOrError instanceof Response) {
            return $requestOrError;
        }

        return $this->executeRequest($requestOrError);
    }

    private function executeRequest(Request $request): ?Response
    {
        try {
            $result = $this->requestHandler->handle($request);

            if ($request->isNotification()) {
                return null;
            }

            return Response::success($request->id, $result);
        } catch (MethodNotFoundException|InvalidParamsException|JsonRpcException $e) {
            if ($request->isNotification()) {
                return null;
            }

            return Response::error($request->id, $e->rpcCode(), $e->getMessage(), $e->rpcData());
        } catch (Throwable $e) {
            $this->logger?->error('Unhandled method exception', [
                'method' => $request->method,
                'exception' => $e,
            ]);

            if ($request->isNotification()) {
                return null;
            }

            return Response::error($request->id, ErrorCode::INTERNAL_ERROR, self::MESSAGE_INTERNAL_ERROR);
        }
    }

    /**
     * Validate decoded JSON object and normalize to internal Request model.
     *
     * @return Request|Response
     */
    private function toRequest(object $value): Request|Response
    {
        $hasId = property_exists($value, 'id');
        $requestId = null;

        if ($hasId) {
            if (!$this->isValidId($value->id)) {
                // Invalid id means whole object is invalid request.
                return $this->invalidRequest();
            }

            $requestId = $value->id;
        }

        if (!property_exists($value, 'jsonrpc') || $value->jsonrpc !== self::VERSION) {
            return $this->invalidRequest($requestId);
        }

        if (!property_exists($value, 'method') || !is_string($value->method) || $value->method === '') {
            return $this->invalidRequest($requestId);
        }

        if (property_exists($value, 'params') && !(is_array($value->params) || is_object($value->params))) {
            return Response::error($requestId, ErrorCode::INVALID_PARAMS, self::MESSAGE_INVALID_PARAMS);
        }

        return new Request(
            method: $value->method,
            params: property_exists($value, 'params') ? $this->normalizeValue($value->params) : null,
            id: $requestId,
            hasId: $hasId,
        );
    }

    private function invalidRequest(mixed $id = null): Response
    {
        return Response::error($id, ErrorCode::INVALID_REQUEST, self::MESSAGE_INVALID_REQUEST);
    }

    /**
     * JSON-RPC id can be string, number or null.
     */
    private function isValidId(mixed $id): bool
    {
        return is_int($id) || is_float($id) || is_string($id) || $id === null;
    }

    /**
     * Convert stdClass trees from json_decode into plain arrays.
     */
    private function normalizeValue(mixed $value): mixed
    {
        if (is_object($value)) {
            $normalized = [];
            foreach (get_object_vars($value) as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->normalizeValue($item);
            }
        }

        return $value;
    }

    private function encodeSingle(Response $response): string
    {
        return $this->encode($response->toArray());
    }

    private function encode(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            return self::INTERNAL_ERROR_FALLBACK_JSON;
        }
    }
}
