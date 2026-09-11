# Capability query без application context

`QueryHandler(contextAware: false)` подходит для небольших capability-сценариев чтения, где handler получает только публичный message и не принимает `MessageContextInterface`.

```php
<?php

declare(strict_types=1);

use Wolfcharaa\MessageBus\Attribute\QueryHandler;

final readonly class FindAddressById
{
    public function __construct(public string $id)
    {
    }
}

#[QueryHandler(FindAddressById::class, flow: 'domain_capability', bindingId: 'address.find_by_id', contextAware: false)]
final readonly class FindAddressByIdHandler
{
    public function __construct(private FindAddressByIdReadInterface $addresses)
    {
    }

    public function __invoke(FindAddressById $message): FindAddressByIdResult
    {
        return new FindAddressByIdResult($this->addresses->find($message->id));
    }
}
```

При явном списке flows добавьте `domain_capability` в `message_bus.php`:

```php
'flows' => [
    FlowDefinition::sync('default'),
    FlowDefinition::sync('domain_capability'),
],
```

Spiral Tokenizer обнаруживает `QueryHandler`, core compiler проверяет contextless-сигнатуру, а runtime plan вызывает handler без передачи application context.
