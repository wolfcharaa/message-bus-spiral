<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Invoker;

use Spiral\Core\InvokerInterface;
use Wolfcharaa\MessageBus\Invoker\CallableInvokerInterface;

final class SpiralCallableInvoker implements CallableInvokerInterface
{
    public function __construct(private readonly InvokerInterface $invoker)
    {
    }

    public function invoke(string $class, string $method, array $arguments = []): mixed
    {
        return $this->invoker->invoke([$class, $method], $arguments);
    }
}

