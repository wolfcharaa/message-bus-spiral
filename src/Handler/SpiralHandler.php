<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Handler;

use Closure;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\TargetInterface;
use Spiral\Interceptors\HandlerInterface;
use Throwable;
use Wolfcharaa\MessageBus\Handler\Handler;
use Wolfcharaa\MessageBus\Message\Context;
use Wolfcharaa\MessageBus\Message\Message;

/**
 * @template TResult
 * @template TMessage of Message<TResult>
 * @implements Handler<TResult, TMessage>
 */
class SpiralHandler implements Handler
{
    /**
     * @param Closure(TMessage, Context<TResult, TMessage>): TResult $handler
     */
    public function __construct(
        private readonly HandlerInterface $handler,
        private readonly TargetInterface $target
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handle(Context $context): mixed
    {
        return $this->handler->handle(new CallContext($this->target, [$context->envelope->message, $context]));
    }
}
