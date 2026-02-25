<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Support;

use CatalogCodex\JsonRpc\Exception\InvalidParamsException;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

/**
 * Invokes arbitrary PHP callable using JSON-RPC compatible params.
 *
 * JSON-RPC allows params to be:
 * - omitted
 * - positional array
 * - named object (already normalized to associative array)
 */
final class CallableInvoker
{
    /**
     * @throws InvalidParamsException
     */
    public function invoke(callable $callable, mixed $params): mixed
    {
        $reflection = $this->reflectCallable($callable);

        if ($params === null) {
            return $this->invokePositional($callable, $reflection, []);
        }

        if (!is_array($params)) {
            throw new InvalidParamsException('"params" must be either an array or an object');
        }

        if (array_is_list($params)) {
            return $this->invokePositional($callable, $reflection, $params);
        }

        return $this->invokeNamed($callable, $reflection, $params);
    }

    /**
     * Resolve reflection metadata for callable variants.
     *
     * @throws ReflectionException
     */
    private function reflectCallable(callable $callable): ReflectionFunctionAbstract
    {
        if (is_array($callable)) {
            return new ReflectionMethod($callable[0], $callable[1]);
        }

        if (is_string($callable) && str_contains($callable, '::')) {
            [$class, $method] = explode('::', $callable, 2);
            return new ReflectionMethod($class, $method);
        }

        if (is_object($callable) && !($callable instanceof \Closure) && method_exists($callable, '__invoke')) {
            return new ReflectionMethod($callable, '__invoke');
        }

        return new ReflectionFunction($callable);
    }

    /**
     * @param list<mixed> $params
     * @throws InvalidParamsException
     */
    private function invokePositional(callable $callable, ReflectionFunctionAbstract $reflection, array $params): mixed
    {
        $requiredCount = $reflection->getNumberOfRequiredParameters();
        $declaredCount = $reflection->getNumberOfParameters();

        if (count($params) < $requiredCount) {
            throw new InvalidParamsException('Not enough parameters provided');
        }

        if (!$reflection->isVariadic() && count($params) > $declaredCount) {
            throw new InvalidParamsException('Too many parameters provided');
        }

        return $callable(...$params);
    }

    /**
     * @param array<string,mixed> $params
     * @throws InvalidParamsException
     */
    private function invokeNamed(callable $callable, ReflectionFunctionAbstract $reflection, array $params): mixed
    {
        $arguments = [];

        foreach ($reflection->getParameters() as $parameter) {
            $parameterName = $parameter->getName();

            if (array_key_exists($parameterName, $params)) {
                $arguments[] = $params[$parameterName];
                unset($params[$parameterName]);
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->isVariadic()) {
                // Named params cannot map naturally to variadic arguments.
                // Keep behavior strict and let caller pass positional params.
                continue;
            }

            throw new InvalidParamsException(sprintf('Missing required named parameter "%s"', $parameterName));
        }

        if ($params !== []) {
            $unknown = implode(', ', array_keys($params));
            throw new InvalidParamsException(sprintf('Unknown named parameter(s): %s', $unknown));
        }

        return $callable(...$arguments);
    }
}
