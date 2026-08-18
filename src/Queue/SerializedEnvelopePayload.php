<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Queue;

use DateTimeImmutable;
use Wolfcharaa\MessageBus\Envelope\SerializedEnvelope;
use Wolfcharaa\MessageBus\Serialization\SerializedMessage;

final class SerializedEnvelopePayload
{
    /** @return array<string, mixed> */
    public static function toArray(SerializedEnvelope $envelope): array
    {
        return [
            'message' => [
                'name' => $envelope->message->name,
                'contentType' => $envelope->message->contentType,
                'payload' => $envelope->message->payload,
                'headers' => $envelope->message->headers,
            ],
            'envelopeHeaders' => $envelope->headers,
            'messageId' => $envelope->messageId,
            'causationId' => $envelope->causationId,
            'correlationId' => $envelope->correlationId,
            'flow' => $envelope->flow,
            'bindingId' => $envelope->bindingId,
            'createdAt' => $envelope->createdAt->format(DATE_ATOM),
        ];
    }

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): SerializedEnvelope
    {
        $message = $payload['message'] ?? null;
        if (!\is_array($message)) {
            throw new \InvalidArgumentException('Serialized envelope payload must contain message array.');
        }

        return new SerializedEnvelope(
            new SerializedMessage(
                self::string($message, 'name'),
                self::string($message, 'contentType'),
                self::string($message, 'payload'),
                self::array($message, 'headers'),
            ),
            self::array($payload, 'envelopeHeaders'),
            self::string($payload, 'messageId'),
            self::nullableString($payload, 'causationId'),
            self::string($payload, 'correlationId'),
            self::string($payload, 'flow'),
            self::nullableString($payload, 'bindingId'),
            new DateTimeImmutable(self::string($payload, 'createdAt')),
        );
    }

    /** @param array<string, mixed> $payload */
    private static function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!\is_string($value) || $value === '') {
            throw new \InvalidArgumentException(\sprintf('Serialized envelope field `%s` must be a non-empty string.', $key));
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    private static function nullableString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;
        if ($value === null) {
            return null;
        }

        if (!\is_string($value) || $value === '') {
            throw new \InvalidArgumentException(\sprintf('Serialized envelope field `%s` must be null or non-empty string.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function array(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];
        if (!\is_array($value)) {
            throw new \InvalidArgumentException(\sprintf('Serialized envelope field `%s` must be array.', $key));
        }

        return $value;
    }
}

