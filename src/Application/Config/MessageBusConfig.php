<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Application\Config;

use Spiral\Core\Attribute\Singleton;
use Spiral\Core\InjectableConfig;
use Wolfcharaa\MessageBus\Spiral\Application\Job\QueueHandlerJob;

#[Singleton]
final class MessageBusConfig extends InjectableConfig
{
    public const CONFIG = 'message_bus';

    /**
     * Default values for the config.
     * Will be merged with application config in runtime.
     *
     * @var array{registryFile: ?string, queueJob: class-string}
     */
    protected array $config = [
        'registryFile' => null,
        'queueJob' => QueueHandlerJob::class,
    ];

    public function getRegistryFile(): ?string
    {
        $file = $this->config['registryFile'] ?? null;

        return \is_string($file) && $file !== '' ? $file : null;
    }

    /** @return class-string */
    public function getQueueJob(): string
    {
        $job = $this->config['queueJob'] ?? QueueHandlerJob::class;

        return \is_string($job) && $job !== '' ? $job : QueueHandlerJob::class;
    }
}
