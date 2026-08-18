<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Application\Job;

use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\InvokerInterface;
use Spiral\Queue\JobHandler;
use Wolfcharaa\MessageBus\Queue\QueueWorkerInterface;
use Wolfcharaa\MessageBus\Spiral\Queue\SerializedEnvelopePayload;

#[Singleton]
final class QueueHandlerJob extends JobHandler
{
    public function __construct(
        #[Proxy] InvokerInterface $invoker,
        private readonly QueueWorkerInterface $worker,
    ) {
        parent::__construct($invoker);
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $envelopeHeaders
     */
    public function invoke(
        array $message,
        array $envelopeHeaders,
        string $messageId,
        ?string $causationId,
        string $correlationId,
        string $flow,
        ?string $bindingId,
        string $createdAt,
    ): void {
        $this->worker->handle(SerializedEnvelopePayload::fromArray([
            'message' => $message,
            'envelopeHeaders' => $envelopeHeaders,
            'messageId' => $messageId,
            'causationId' => $causationId,
            'correlationId' => $correlationId,
            'flow' => $flow,
            'bindingId' => $bindingId,
            'createdAt' => $createdAt,
        ]));
    }
}
