<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Attribute;

use Spiral\Interceptors\InterceptorInterface;
use Wolfcharaa\MessageBus\Middleware\Middleware;
use Wolfcharaa\MessageBus\Spiral\Application\Config\MessageBusConfig;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Handler
{
    public array $middleware;

    /**
     * @param ?string $group
     * @see MessageBusConfig
     * @param class-string<Middleware|InterceptorInterface> ...$middleware
     */
    public function __construct(
        public ?string $group = null,
        string ...$middleware
    ) {
        $this->middleware = $middleware;
    }
}
