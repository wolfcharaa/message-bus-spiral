<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Message
{
    public function __construct(
        public bool $isEvent = false,
        public ?string $alias = null
    ) {
    }
}
