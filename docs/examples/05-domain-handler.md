# Domain capability без application context

`DomainHandler` предназначен для небольших доменных сценариев чтения и записи. Его handler получает только публичный message и не принимает `MessageContextInterface`.

```php
<?php

declare(strict_types=1);

use Wolfcharaa\MessageBus\Attribute\DomainHandler;

final readonly class FindAddressById
{
    public function __construct(public string $id)
    {
    }
}

#[DomainHandler(FindAddressById::class, bindingId: 'address.find_by_id')]
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

По умолчанию binding использует синхронный flow `domain_capability`. При явном списке flows добавьте его в `message_bus.php`:

```php
'flows' => [
    FlowDefinition::sync('default'),
    FlowDefinition::sync('domain_capability'),
],
```

Spiral Tokenizer обнаруживает `DomainHandler`, core compiler проверяет contextless-сигнатуру, а runtime plan вызывает handler без передачи application context.
