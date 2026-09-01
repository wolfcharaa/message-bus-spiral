# MessageBus Spiral

`romanfedorskij/message-bus-spiral` — адаптер Spiral Framework для `romanfedorskij/message-bus` v5.

Поддерживаемые версии: Spiral Framework `^3.16`, RoadRunner Bridge `^3.8 || ^4.0`.

Пакет не содержит отдельного registry builder. В v5 связи `message -> action` компилируются основным `message-bus`, а Spiral-пакет подключает:

- `Spiral\Tokenizer` listener для поиска классов с attributes;
- compiler listener, который пишет compiled registry в runtime-файл;
- Spiral DI resolver;
- Spiral invoker для handler/middleware;
- Spiral Queue provider;
- Spiral Queue job для выполнения serialized envelope.

## Установка

```bash
composer require romanfedorskij/message-bus-spiral
```

## Конфигурация

В приложении нужно указать путь к compiled registry. Listener создаст этот файл при boot приложения:

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
    ],
];
```

Файл подключается как `message_bus` config.

## Discovery через Spiral Tokenizer

`MessageBusBootloader` регистрирует listener в `TokenizerListenerBootloader`.
Listener получает классы с attributes:

- `CommandHandler`;
- `QueryHandler`;
- `EventSubscriber`;
- `MessageAlias`.

После обхода проекта listener вызывает core `MessageRegistryCompiler` и сохраняет результат в `registryFile`.
Runtime `MessageBus` читает уже compiled registry через `CompiledMessageRegistry::fromFile()`.

## Runtime plan для long-running Spiral

После чтения compiled registry адаптер собирает `RuntimePlanRegistry` в памяти процесса:

- заранее подготавливает карту `bindingId -> plan`;
- заранее сортирует bindings по priority;
- заранее объединяет `flow middleware + binding middleware`;
- заранее объединяет `flow delivery + binding delivery`;
- автоматически подменяет default sync/async strategies на runtime-plan strategies.

Runtime plan хранит только class-string и immutable definition-данные. Объекты action и middleware не кешируются адаптером: их продолжает создавать Spiral DI в текущем scope.
Это сохраняет корректную работу request/auth scope и подмен интерфейсов в long-running RoadRunner worker.

## Flow для Spiral Queue

`transport` используется как имя Spiral queue connection.
`queue` используется как queue name внутри options.

```php
use Wolfcharaa\MessageBus\Flow\FlowDefinition;
use Wolfcharaa\MessageBus\Flow\FlowRegistry;

$flows = new FlowRegistry(
    FlowDefinition::sync('default'),
    FlowDefinition::async('notifications')
        ->transport('roadrunner', 'notifications'),
);
```

Async binding обязан иметь стабильный `bindingId`.

## Worker

Producer сохраняет в Spiral Queue переносимый array payload.
Worker восстанавливает `SerializedEnvelope` и вызывает core worker:

```php
\Wolfcharaa\MessageBus\Spiral\Application\Job\QueueHandlerJob::class
```

PHP `serialize()` не используется.

## Примеры

- [Быстрое подключение bootloader и config](docs/examples/01-bootloader-config.md)
- [Command handler и compiled registry](docs/examples/02-command-handler.md)
- [Async flow через Spiral Queue](docs/examples/03-async-queue.md)
- [Runtime plan в long-running процессе](docs/examples/04-runtime-plan.md)

## Тесты

```bash
composer test
```
