# Async flow через Spiral Queue

Async flow использует core `QueueExecutionStrategy`, а Spiral-адаптер предоставляет `QueueProviderInterface` поверх `Spiral\Queue`.

## Config

```php
<?php

declare(strict_types=1);

use Wolfcharaa\MessageBus\Flow\FlowDefinition;
use Wolfcharaa\MessageBus\Spiral\Application\Job\QueueHandlerJob;

return [
    'registryFile' => directory('runtime') . 'cache/message_bus_registry.php',
    'queueJob' => QueueHandlerJob::class,
    'runtimePlan' => true,
    'flows' => [
        FlowDefinition::sync('default'),
        FlowDefinition::async('notifications')
            ->transport('roadrunner', 'notifications'),
    ],
];
```

`transport` передаётся как имя Spiral queue connection, а `queue` как имя очереди внутри `Spiral\Queue\Options`.

## Message

Async message должен иметь стабильный alias, потому что queue payload переносимый и не использует PHP `serialize()`.

```php
<?php

declare(strict_types=1);

namespace App\Application\User;

use Wolfcharaa\MessageBus\Attribute\MessageAlias;
use Wolfcharaa\MessageBus\Message\Event;

#[MessageAlias('user.created')]
final readonly class UserCreatedMessage implements Event
{
    public function __construct(
        public int $userId,
    ) {
    }
}
```

## Subscriber

```php
<?php

declare(strict_types=1);

namespace App\Application\User;

use Wolfcharaa\MessageBus\Attribute\EventSubscriber;
use Wolfcharaa\MessageBus\Context\MessageContextInterface;

#[EventSubscriber(
    UserCreatedMessage::class,
    flow: 'notifications',
    bindingId: 'user.created.email',
)]
final class SendUserCreatedEmailAction
{
    public function __invoke(UserCreatedMessage $message, MessageContextInterface $context): void
    {
        // Отправка письма, запись уведомления или другой side-effect.
    }
}
```

## Publish

```php
<?php

declare(strict_types=1);

$bus->publish(new UserCreatedMessage($userId));
```

Producer положит переносимый array payload в Spiral Queue. Worker вызовет `QueueHandlerJob`, восстановит `SerializedEnvelope` и передаст его core `MessageBusQueueWorker`.

PHP `serialize()` не используется, поэтому payload можно развивать в сторону JSON/protobuf сериализаторов.
