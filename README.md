# MessageBus Spiral

`romanfedorskij/message-bus-spiral` — адаптер Spiral Framework для `romanfedorskij/message-bus` v4.

Поддерживаемая версия Spiral Framework: `^3.16`.

Пакет не собирает registry сам. В v4 registry компилируется основным `message-bus`, а Spiral-пакет подключает:

- Spiral DI resolver;
- Spiral invoker для handler/middleware;
- Spiral Queue provider;
- Spiral Queue job для выполнения serialized envelope.

## Установка

```bash
composer require romanfedorskij/message-bus-spiral
```

## Конфигурация

В приложении нужно указать путь к compiled registry:

```php
<?php

declare(strict_types=1);

return [
    'registryFile' => directory('runtime') . 'cache/message_bus_registry.php',
    'queueJob' => \Wolfcharaa\MessageBus\Spiral\Application\Job\QueueHandlerJob::class,
];
```

Файл подключается как `message_bus` config.

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

## Тесты

```bash
composer test
```
