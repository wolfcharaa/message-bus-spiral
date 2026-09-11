<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Tests\Fixture;

use Wolfcharaa\MessageBus\Attribute\QueryHandler;
use Wolfcharaa\MessageBus\Context\MessageContextInterface;

#[QueryHandler(CompilePingMessage::class, bindingId: 'compile.ping')]
final class CompilePingAction
{
    public function __invoke(CompilePingMessage $message, MessageContextInterface $context): string
    {
        return $message->name;
    }
}
