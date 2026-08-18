# Bootloader и конфигурация Spiral

Пакет подключает `MessageBusBootloader`, который регистрирует:

- `MessageBusInterface`;
- `MessageRegistryInterface` из compiled registry;
- Spiral invoker и DI resolver;
- Spiral Queue provider;
- listener для `Spiral\Tokenizer`.

## Bootloader

Добавьте bootloader в приложение:

```php
<?php

declare(strict_types=1);

use Wolfcharaa\MessageBus\Spiral\Application\Bootloader\MessageBusBootloader;

return [
    // ...
    MessageBusBootloader::class,
];
```

## Config `message_bus.php`

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

`registryFile` должен указывать на writable runtime-файл. При boot приложения Spiral Tokenizer найдёт классы с attributes, core compiler проверит контракт, а listener сохранит compiled registry в этот файл.

Runtime больше не сканирует проект при каждом обращении к `MessageBusInterface`; он читает готовый compiled registry.
