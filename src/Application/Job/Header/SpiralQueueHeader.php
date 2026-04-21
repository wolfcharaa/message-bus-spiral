<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Application\Job\Header;

final class SpiralQueueHeader implements \JsonSerializable
{
    public function __construct(
        public bool $isStarted = false,
        public string $driver = 'roadrunner'
    ) {
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
