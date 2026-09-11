# Command handler

MessageBus v6 описывает связи через core attributes. Spiral-адаптер помогает найти эти классы через `Spiral\Tokenizer` и выполнить action через Spiral DI.

## Message

```php
<?php

declare(strict_types=1);

namespace App\Application\User;

use Wolfcharaa\MessageBus\Message\Command;

final readonly class CreateUserCommand implements Command
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

#[CommandHandler(CreateUserCommand::class, bindingId: 'user.create')]
final class CreateUserAction
{
    public function __construct(
        private readonly UserStorage $storage,
    ) {
    }

    public function __invoke(CreateUserCommand $message, MessageContextInterface $context): void
    {
        $this->storage->create($message->email);
    }
}
```

`bindingId` нужен как стабильное имя связи. В v6 command handler выполняет правило обработки и возвращает `void`. Если вызывающей стороне нужен результат, используйте `QueryHandler`.

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

    public function __invoke(): void
    {
        $this->bus->dispatch(new CreateUserCommand('user@example.test'));
    }
}
```

Если action имеет зависимости, Spiral DI создаст action через `SpiralCallableInvoker`.
