<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Middleware;

use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;
use Wolfcharaa\MessageBus\Message\Context;
use Wolfcharaa\MessageBus\Middleware\Middleware;
use Wolfcharaa\MessageBus\Pipeline\Pipeline;

class SpiralMiddleware implements Middleware
{
    public function __construct(
        private readonly InterceptorInterface $interceptor,
        private readonly HandlerInterface $handler,
    ) {
    }

    public function handle(Context $context, Pipeline $pipeline): mixed
    {
        $callContext = new CallContext(
            Target::fromPair($pipeline, 'continue'),
            [$context->envelope->message, $context]
        );

        return $this->interceptor->intercept($callContext, $this->handler);
    }
}
