<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Tests\Fixture;

use Wolfcharaa\MessageBus\Message\Command;

/** @implements Command<string> */
final readonly class CompilePingMessage implements Command
{
    public function __construct(public string $name)
    {
    }
}
