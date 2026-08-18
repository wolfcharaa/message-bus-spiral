# Command handler

MessageBus v4 описывает связи через core attributes. Spiral-адаптер помогает найти эти классы через `Spiral\Tokenizer` и выполнить action через Spiral DI.

## Message

```php
<?php

declare(strict_types=1);

namespace App\Application\User;

use Wolfcharaa\MessageBus\Message\Command;

/** @implements Command<CreateUserResult> */
final readonly class CreateUserMessage implements Command
{
    public function __construct(
        public string $email,
    ) {
    }
}
```

## Action

```php
<?php

declare(strict_types=1);

namespace App\Application\User;

use Wolfcharaa\MessageBus\Attribute\CommandHandler;
use Wolfcharaa\MessageBus\Context\MessageContextInterface;

#[CommandHandler(CreateUserMessage::class, bindingId: 'user.create')]
final class CreateUserAction
{
    public function __construct(
        private readonly UserStorage $storage,
    ) {
    }

    public function __invoke(CreateUserMessage $message, MessageContextInterface $context): CreateUserResult
    {
        return $this->storage->create($message->email);
    }
}
```

`bindingId` нужен как стабильное имя связи. Для sync command без явного `bindingId` core compiler может создать auto binding, но для async flow стабильный `bindingId` обязателен.

## Использование

```php
<?php

declare(strict_types=1);

use Wolfcharaa\MessageBus\MessageBusInterface;

final class CreateUserController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function __invoke(): CreateUserResult
    {
        return $this->bus->dispatch(new CreateUserMessage('user@example.test'));
    }
}
```

Если action имеет зависимости, Spiral DI создаст action через `SpiralCallableInvoker`.
