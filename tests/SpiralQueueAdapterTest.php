<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Spiral\Queue\OptionsInterface;
use Spiral\Queue\QueueConnectionProviderInterface;
use Spiral\Queue\QueueInterface;
use Wolfcharaa\MessageBus\Envelope\SerializedEnvelope;
use Wolfcharaa\MessageBus\Queue\QueueMessage;
use Wolfcharaa\MessageBus\Serialization\SerializedMessage;
use Wolfcharaa\MessageBus\Spiral\Queue\SerializedEnvelopePayload;
use Wolfcharaa\MessageBus\Spiral\Queue\SpiralQueueProvider;

final class SpiralQueueAdapterTest extends TestCase
{
    public function testSerializedEnvelopePayloadRoundTrip(): void
    {
        $envelope = self::serializedEnvelope();

        $restored = SerializedEnvelopePayload::fromArray(SerializedEnvelopePayload::toArray($envelope));

        self::assertSame($envelope->message->name, $restored->message->name);
        self::assertSame($envelope->message->contentType, $restored->message->contentType);
        self::assertSame($envelope->message->payload, $restored->message->payload);
        self::assertSame($envelope->message->headers, $restored->message->headers);
        self::assertSame($envelope->headers, $restored->headers);
        self::assertSame($envelope->messageId, $restored->messageId);
        self::assertSame($envelope->causationId, $restored->causationId);
        self::assertSame($envelope->correlationId, $restored->correlationId);
        self::assertSame($envelope->flow, $restored->flow);
        self::assertSame($envelope->bindingId, $restored->bindingId);
        self::assertSame($envelope->createdAt->format(DATE_ATOM), $restored->createdAt->format(DATE_ATOM));
    }

    public function testQueueProviderPushesSpiralJobWithTransportQueueAndDelay(): void
    {
        $queue = new RecordingQueue();
        $provider = new RecordingQueueConnectionProvider($queue);
        $spiral = new SpiralQueueProvider($provider, new FrozenClock(), 'custom.job');

        $result = $spiral->enqueue(new QueueMessage(
            'roadrunner',
            'notifications',
            self::serializedEnvelope(),
            'message-1',
            'correlation-1',
            'notifications',
            'user.created.email',
            new DateTimeImmutable('2026-08-18T12:00:30+00:00'),
            7,
        ));

        self::assertSame('queue-id-1', $result->queueMessageId);
        self::assertSame('roadrunner', $provider->connectionName);
        self::assertSame('custom.job', $queue->name);
        self::assertSame('notifications', $queue->options?->getQueue());
        self::assertSame(30, $queue->options?->getDelay());
        self::assertSame('message-1', $queue->options?->getHeaderLine('message-bus-message-id'));
        self::assertSame('correlation-1', $queue->options?->getHeaderLine('message-bus-correlation-id'));
        self::assertSame('notifications', $queue->options?->getHeaderLine('message-bus-flow'));
        self::assertSame('user.created.email', $queue->options?->getHeaderLine('message-bus-binding-id'));
        self::assertSame('7', $queue->options?->getHeaderLine('message-bus-priority'));
        self::assertSame('user.created', $queue->payload['message']['name'] ?? null);
    }

    private static function serializedEnvelope(): SerializedEnvelope
    {
        return new SerializedEnvelope(
            new SerializedMessage(
                'user.created',
                'application/json',
                '{"userId":10}',
                ['message_header' => 'value'],
            ),
            ['request_id' => 'request-1'],
            'message-1',
            null,
            'correlation-1',
            'notifications',
            'user.created.email',
            new DateTimeImmutable('2026-08-18T12:00:00+00:00'),
        );
    }
}

final class FrozenClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-18T12:00:00+00:00');
    }
}

final class RecordingQueueConnectionProvider implements QueueConnectionProviderInterface
{
    public ?string $connectionName = null;

    public function __construct(private readonly QueueInterface $queue)
    {
    }

    public function getConnection(?string $name = null): QueueInterface
    {
        $this->connectionName = $name;

        return $this->queue;
    }
}

final class RecordingQueue implements QueueInterface
{
    public ?string $name = null;

    /** @var array<string, mixed> */
    public array $payload = [];

    public ?OptionsInterface $options = null;

    public function push(string $name, array $payload = [], ?OptionsInterface $options = null): string
    {
        $this->name = $name;
        $this->payload = $payload;
        $this->options = $options;

        return 'queue-id-1';
    }
}
