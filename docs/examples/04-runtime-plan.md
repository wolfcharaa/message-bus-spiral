# Runtime plan в long-running процессе

Spiral/RoadRunner работает как long-running приложение, поэтому после compiled registry можно держать подготовленный execution plan в памяти процесса.

## Что остаётся в compiled registry

Compiled registry остаётся переносимой картой:

- message aliases;
- message class names;
- binding ids;
- action class names;
- method names;
- flow definitions;
- middleware class names.

В compiled registry не сохраняются объекты контейнера, action или middleware.

## Что собирает Spiral-адаптер при старте

При чтении compiled registry bootloader создаёт `RuntimePlanRegistry`.
Он заранее подготавливает:

- `bindingId -> RuntimeHandlerPlan`;
- отсортированные bindings для message;
- объединённый список `flow middleware + binding middleware`;
- объединённые настройки доставки `flow delivery + binding delivery`.

Если `runtimePlan` включён, default strategies заменяются на:

- `RuntimePlanSequentialExecutionStrategy` для sync flow;
- `RuntimePlanQueueExecutionStrategy` для async flow.

Worker очереди использует `RuntimePlanQueueWorker`, чтобы async binding из очереди тоже исполнялся через runtime plan.

## Почему middleware не кешируется объектами

Middleware может зависеть от scoped-сервисов: request context, auth context, tenant context, storage с подменами интерфейсов.
Если создать такие объекты один раз при старте RoadRunner, они могут захватить неправильный scope.

Поэтому runtime plan хранит только class-string цепочку middleware.
Фактическое создание middleware и action остаётся за Spiral DI и происходит через invoker в текущем scope.

## Отключение

Runtime plan включён по умолчанию.
Отключить можно в config:

```php
<?php

declare(strict_types=1);

return [
    'runtimePlan' => false,
];
```

Отключение оставит стандартные core strategies из compiled flow definitions.
