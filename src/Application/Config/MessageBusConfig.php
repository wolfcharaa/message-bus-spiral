<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Application\Config;

use Spiral\Core\Attribute\Singleton;
use Spiral\Core\InjectableConfig;
use Wolfcharaa\MessageBus\Flow\FlowDefinition;
use Wolfcharaa\MessageBus\Flow\FlowRegistry;
use Wolfcharaa\MessageBus\Spiral\Application\Job\QueueHandlerJob;

#[Singleton]
final class MessageBusConfig extends InjectableConfig
{
    public const CONFIG = 'message_bus';

    /**
     * Default values for the config.
     * Will be merged with application config in runtime.
     *
     * @var array{registryFile: ?string, queueJob: class-string, runtimePlan: bool, flows: FlowRegistry|array<FlowDefinition|array<string, mixed>>}
     */
    protected array $config = [
        'registryFile' => null,
        'queueJob' => QueueHandlerJob::class,
        'runtimePlan' => true,
        'flows' => [],
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

    public function useRuntimePlan(): bool
    {
        return ($this->config['runtimePlan'] ?? true) === true;
    }

    public function getFlowRegistry(): FlowRegistry
    {
        $flows = $this->config['flows'] ?? [];

        if ($flows instanceof FlowRegistry) {
            return $flows;
        }

        if (!\is_array($flows)) {
            throw new \InvalidArgumentException('MessageBus config `flows` must be FlowRegistry or array.');
        }

        if ($flows === []) {
            return new FlowRegistry();
        }

        $definitions = [];
        foreach ($flows as $flow) {
            if ($flow instanceof FlowDefinition) {
                $definitions[] = $flow;
                continue;
            }

            if (\is_array($flow)) {
                $definitions[] = FlowDefinition::fromArray($flow);
                continue;
            }

            throw new \InvalidArgumentException('MessageBus config `flows` contains invalid flow definition.');
        }

        return new FlowRegistry(...$definitions);
    }
}
