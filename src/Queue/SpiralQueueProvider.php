<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Queue;

use Psr\Clock\ClockInterface;
use Spiral\Queue\Options;
use Spiral\Queue\QueueConnectionProviderInterface;
use Wolfcharaa\MessageBus\Queue\QueueEnqueueResult;
use Wolfcharaa\MessageBus\Queue\QueueMessage;
use Wolfcharaa\MessageBus\Queue\QueueProviderInterface;
use Wolfcharaa\MessageBus\Spiral\Application\Job\QueueHandlerJob;

final class SpiralQueueProvider implements QueueProviderInterface
{
    public function __construct(
        private readonly QueueConnectionProviderInterface $provider,
        private readonly ClockInterface $clock,
        private readonly string $job = QueueHandlerJob::class,
    ) {
    }

    public function enqueue(QueueMessage $message): QueueEnqueueResult
    {
        $delay = \max(0, $message->availableAt->getTimestamp() - $this->clock->now()->getTimestamp());
        $options = Options::onQueue($message->queue)
            ->withDelay($delay > 0 ? $delay : null)
            ->withHeader('message-bus-message-id', $message->messageId)
            ->withHeader('message-bus-correlation-id', $message->correlationId)
            ->withHeader('message-bus-flow', $message->flow)
            ->withHeader('message-bus-binding-id', $message->bindingId)
            ->withHeader('message-bus-priority', (string) $message->priority);

        $queueId = $this->provider
            ->getConnection($message->transport)
            ->push($this->job, SerializedEnvelopePayload::toArray($message->envelope), $options);

        return new QueueEnqueueResult($queueId);
    }
}

