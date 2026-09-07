<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Tests\Fixture;

final readonly class CompileDomainLookupMessage
{
    public function __construct(public int $id)
    {
    }
}
