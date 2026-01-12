<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Application\Job;

use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\InvokerInterface;
use Spiral\Queue\JobHandler;
use Wolfcharaa\MessageBus\Attribute\Async;
use Wolfcharaa\MessageBus\Envelope;
use Wolfcharaa\MessageBus\MessageBusInterface;
use Wolfcharaa\MessageBus\PublishOptions;

#[Singleton]
final class QueueHandlerJob extends JobHandler
{
    public function __construct(
        #[Proxy] InvokerInterface $invoker,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct($invoker);
    }

    public function invoke(string $id, mixed $payload, array $headers): void
    {
        $envelope = Envelope::restore($payload);

        $this->messageBus->dispatch(
            $envelope->message,
            new PublishOptions($envelope->messageId, [Async::IS_STARTED => true]),
            $envelope
        );
    }
}
