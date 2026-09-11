<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Tests\Fixture;

use Wolfcharaa\MessageBus\Attribute\QueryHandler;

#[QueryHandler(CompileDomainLookupMessage::class, flow: 'domain_capability', bindingId: 'compile.domain_lookup', contextAware: false)]
final class CompileDomainLookupHandler
{
    public function __invoke(CompileDomainLookupMessage $message): string
    {
        return 'domain:' . $message->id;
    }
}
