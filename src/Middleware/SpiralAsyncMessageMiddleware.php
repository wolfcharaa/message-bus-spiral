<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Middleware;

use Wolfcharaa\MessageBus\Attribute\Async;
use Wolfcharaa\MessageBus\Message\Context;
use Wolfcharaa\MessageBus\Middleware\Middleware;
use Wolfcharaa\MessageBus\Pipeline\Pipeline;
use Wolfcharaa\MessageBus\Spiral\Application\Job\QueueHandlerJob;
use ReflectionClass;
use Spiral\Queue\QueueConnectionProviderInterface;

class SpiralAsyncMessageMiddleware implements Middleware
{
    public function __construct(
        private readonly QueueConnectionProviderInterface $provider,
    ) {
    }

    public function handle(Context $context, Pipeline $pipeline): mixed
    {
        if (!filter_var($context->envelope->headers[Async::IS_STARTED] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $attributes = new ReflectionClass($context->envelope->message);
            $attr = $attributes->getAttributes(Async::class)[0] ?? null;

            if ($attr !== null) {
                /** @var Async $instance */
                $instance = $attr->newInstance();
                $this->provider->getConnection($instance->driver)->push(
                    QueueHandlerJob::class,
                    $context->envelope->jsonSerialize(),
                );

                return null;
            }
        }

        return $pipeline->continue();
    }
}
