<?php

declare(strict_types=1);

namespace CatalogCodex\JsonRpc\Tests\Unit;

use CatalogCodex\JsonRpc\Exception\InvalidParamsException;
use CatalogCodex\JsonRpc\Support\CallableInvoker;
use PHPUnit\Framework\TestCase;

final class CallableInvokerTest extends TestCase
{
    private CallableInvoker $invoker;

    protected function setUp(): void
    {
        $this->invoker = new CallableInvoker();
    }

    public function testInvokePositionalParams(): void
    {
        $result = $this->invoker->invoke(
            static fn (int $a, int $b): int => $a + $b,
            [1, 2],
        );

        self::assertSame(3, $result);
    }

    public function testInvokeNamedParams(): void
    {
        $result = $this->invoker->invoke(
            static fn (int $x, int $y): int => $x * $y,
            ['x' => 3, 'y' => 4],
        );

        self::assertSame(12, $result);
    }

    public function testInvokeNamedParamsWithUnknownArgumentThrows(): void
    {
        $this->expectException(InvalidParamsException::class);

        $this->invoker->invoke(
            static fn (string $name): string => $name,
            ['name' => 'john', 'extra' => true],
        );
    }

    public function testInvokeWithTooManyArgumentsThrows(): void
    {
        $this->expectException(InvalidParamsException::class);

        $this->invoker->invoke(
            static fn (int $a): int => $a,
            [1, 2],
        );
    }
}
