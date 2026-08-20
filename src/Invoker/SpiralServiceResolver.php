<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Invoker;

use Psr\Container\ContainerInterface;

/**
 * Legacy wrapper kept for applications that used it directly before MessageBus v5.
 * Core v5 uses PSR-11 container directly and no longer exposes ServiceResolverInterface.
 */
final class SpiralServiceResolver
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
