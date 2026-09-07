<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Tests\Fixture;

use Wolfcharaa\MessageBus\Attribute\DomainHandler;

#[DomainHandler(CompileDomainLookupMessage::class, bindingId: 'compile.domain_lookup')]
final class CompileDomainLookupHandler
{
    public function __invoke(CompileDomainLookupMessage $message): string
    {
        return 'domain:' . $message->id;
    }
}
