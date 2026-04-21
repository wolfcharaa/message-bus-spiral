<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Application\Config;

use Spiral\Core\Attribute\Singleton;
use Spiral\Core\InjectableConfig;
use Spiral\Interceptors\InterceptorInterface;
use Wolfcharaa\MessageBus\Middleware\Middleware;

#[Singleton]
final class MessageBusConfig extends InjectableConfig
{
    public const CONFIG = 'message_bus';
    public const MIDDLEWARE = 'middlewareGroups';

    /**
     * Default values for the config.
     * Will be merged with application config in runtime.
     *
     * @var array{middlewareGroups: <string, array<class-string<Middleware|InterceptorInterface>>>}
     */
    protected array $config = [];

    /**
     * @return array{}|array<class-string<Middleware|InterceptorInterface>>
     */
    public function getMiddlewareGroup(string $group): array
    {
        return $this->config[self::MIDDLEWARE][$group] ?? [];
    }
}
