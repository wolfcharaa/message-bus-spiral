<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Invoker;

use Psr\Container\ContainerInterface;
use Wolfcharaa\MessageBus\Invoker\ServiceResolverInterface;

final class SpiralServiceResolver implements ServiceResolverInterface
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function get(string $class): object
    {
        $service = $this->container->get($class);

        if (!\is_object($service)) {
            throw new \RuntimeException(\sprintf('Spiral container returned non-object service for `%s`.', $class));
        }

        return $service;
    }
}

