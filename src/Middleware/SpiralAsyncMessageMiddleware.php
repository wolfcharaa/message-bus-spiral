<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Middleware;

use Wolfcharaa\MessageBus\Message\Context;
use Wolfcharaa\MessageBus\Middleware\Middleware;
use Wolfcharaa\MessageBus\Pipeline\Pipeline;
use Wolfcharaa\MessageBus\Spiral\Application\Job\Header\SpiralQueueHeader;
use Wolfcharaa\MessageBus\Spiral\Application\Job\QueueHandlerJob;
use Spiral\Queue\QueueConnectionProviderInterface;

class SpiralAsyncMessageMiddleware implements Middleware
{
    public function __construct(
        private readonly QueueConnectionProviderInterface $provider,
    ) {
    }

    public function handle(Context $context, Pipeline $pipeline): mixed
    {
        if (
            ($header = $context->envelope->header->get(SpiralQueueHeader::class)) !== null
            && $header->isStarted === false
        ) {
            // QUEUE_SYNC=1 в .env — выполняем синхронно (для отладки через Xdebug)
            if (filter_var($_ENV['QUEUE_SYNC'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $header->isStarted = true;

                return $pipeline->continue();
            }

            $this->provider->getConnection($header->driver)->push(
                QueueHandlerJob::class,
                $context->envelope->jsonSerialize(),
            );

            return null;
        }

        return $pipeline->continue();
    }
}
