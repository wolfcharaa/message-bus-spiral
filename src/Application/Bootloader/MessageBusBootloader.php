<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Application\Bootloader;

use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\Environment;
use Spiral\Core\Attribute\Singleton;
use Spiral\Queue\Bootloader\QueueBootloader;
use Spiral\Queue\QueueConnectionProviderInterface;
use Spiral\Tokenizer\Bootloader\TokenizerListenerBootloader;
use Wolfcharaa\MessageBus\Clock\WallClock;
use Wolfcharaa\MessageBus\Context\DefaultMessageContextFactory;
use Wolfcharaa\MessageBus\Envelope\DefaultEnvelopeSerializer;
use Wolfcharaa\MessageBus\Envelope\EnvelopeSerializerInterface;
use Wolfcharaa\MessageBus\Execution\QueueExecutionStrategy;
use Wolfcharaa\MessageBus\Execution\SequentialExecutionStrategy;
use Wolfcharaa\MessageBus\Flow\FlowRegistry;
use Wolfcharaa\MessageBus\Invoker\CallableInvokerInterface;
use Wolfcharaa\MessageBus\Message\MessageIdGenerator;
use Wolfcharaa\MessageBus\Message\RandomMessageIdGenerator;
use Wolfcharaa\MessageBus\MessageBus;
use Wolfcharaa\MessageBus\MessageBusInterface;
use Wolfcharaa\MessageBus\Queue\QueueProviderInterface;
use Wolfcharaa\MessageBus\Queue\QueueWorkerInterface;
use Wolfcharaa\MessageBus\Registry\CompiledMessageRegistry;
use Wolfcharaa\MessageBus\Registry\MessageRegistryInterface;
use Wolfcharaa\MessageBus\Serialization\JsonMessageSerializer;
use Wolfcharaa\MessageBus\Serialization\MessageNameResolverInterface;
use Wolfcharaa\MessageBus\Spiral\Application\Config\MessageBusConfig;
use Wolfcharaa\MessageBus\Spiral\Application\Job\QueueHandlerJob;
use Wolfcharaa\MessageBus\Spiral\Discovery\MessageBusCompilerListener;
use Wolfcharaa\MessageBus\Spiral\Invoker\SpiralCallableInvoker;
use Wolfcharaa\MessageBus\Spiral\Queue\SpiralQueueProvider;
use Wolfcharaa\MessageBus\Spiral\Runtime\RuntimePlanQueueExecutionStrategy;
use Wolfcharaa\MessageBus\Spiral\Runtime\RuntimePlanQueueWorker;
use Wolfcharaa\MessageBus\Spiral\Runtime\RuntimePlanRegistry;
use Wolfcharaa\MessageBus\Spiral\Runtime\RuntimePlanSequentialExecutionStrategy;

/**
 * @link https://spiral.dev/docs/http-interceptors
 */
#[Singleton]
final class MessageBusBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        QueueBootloader::class,
        \Spiral\RoadRunnerBridge\Bootloader\QueueBootloader::class,
        TokenizerListenerBootloader::class,
    ];

    protected const SINGLETONS = [
        ClockInterface::class => WallClock::class,
        CallableInvokerInterface::class => SpiralCallableInvoker::class,
        DefaultMessageContextFactory::class => DefaultMessageContextFactory::class,
        QueueProviderInterface::class => [self::class, 'createQueueProvider'],
        QueueWorkerInterface::class => RuntimePlanQueueWorker::class,
        RuntimePlanRegistry::class => [self::class, 'createRegistry'],
        MessageRegistryInterface::class => [self::class, 'createMessageRegistry'],
        FlowRegistry::class => [self::class, 'createFlowRegistry'],
        EnvelopeSerializerInterface::class => [self::class, 'createEnvelopeSerializer'],
        MessageBusInterface::class => [self::class, 'createMessageBus'],
        MessageIdGenerator::class => [self::class, 'createMessageId'],
    ];

    public function boot(
        TokenizerListenerBootloader $tokenizer,
        MessageBusCompilerListener $listener,
    ): void {
        $tokenizer->addListener($listener);
    }

    public function createRegistry(MessageBusConfig $config): RuntimePlanRegistry
    {
        $file = $config->getRegistryFile();
        if ($file === null) {
            throw new \RuntimeException(
                'MessageBus registry file is not configured. Set `message_bus.registryFile` to compiled registry path.'
            );
        }

        return RuntimePlanRegistry::fromCompiled(CompiledMessageRegistry::fromFile($file));
    }

    public function createMessageRegistry(RuntimePlanRegistry $registry): MessageRegistryInterface
    {
        return $registry;
    }

    public function createFlowRegistry(MessageRegistryInterface $registry, MessageBusConfig $config): FlowRegistry
    {
        if ($registry instanceof RuntimePlanRegistry) {
            $flows = $registry->flowRegistry();
        } elseif ($registry instanceof CompiledMessageRegistry) {
            $flows = $registry->definition()->flows;
        } else {
            $flows = new FlowRegistry();
        }

        return $config->useRuntimePlan() ? $this->withRuntimeStrategies($flows) : $flows;
    }

    public function createEnvelopeSerializer(MessageRegistryInterface $registry): EnvelopeSerializerInterface
    {
        if (!$registry instanceof MessageNameResolverInterface) {
            throw new \RuntimeException('Default Spiral envelope serializer requires registry implementing MessageNameResolverInterface.');
        }

        return new DefaultEnvelopeSerializer(new JsonMessageSerializer($registry));
    }

    public function createQueueProvider(
        QueueConnectionProviderInterface $queueProvider,
        ClockInterface $clock,
        MessageBusConfig $config,
    ): QueueProviderInterface {
        return new SpiralQueueProvider($queueProvider, $clock, $config->getQueueJob());
    }

    public function createMessageBus(
        MessageRegistryInterface $registry,
        FlowRegistry $flows,
        QueueProviderInterface $queueProvider,
        EnvelopeSerializerInterface $serializer,
        CallableInvokerInterface $invoker,
        ContainerInterface $container,
        MessageIdGenerator $messageIdGenerator,
        ClockInterface $clock,
    ): MessageBusInterface {
        return new MessageBus(
            registry: $registry,
            flows: $flows,
            container: $container,
            queueProvider: $queueProvider,
            envelopeSerializer: $serializer,
            invoker: $invoker,
            messageIdGenerator: $messageIdGenerator,
            clock: $clock,
        );
    }

    protected function createMessageId(Environment $environment): MessageIdGenerator
    {
        return new RandomMessageIdGenerator();
    }

    private function withRuntimeStrategies(FlowRegistry $flows): FlowRegistry
    {
        $definitions = [];

        foreach ($flows->all() as $flow) {
            if ($flow->isSync() && $flow->strategy === SequentialExecutionStrategy::class) {
                $flow = $flow->strategy(RuntimePlanSequentialExecutionStrategy::class);
            }

            if ($flow->isAsync() && $flow->strategy === QueueExecutionStrategy::class) {
                $flow = $flow->strategy(RuntimePlanQueueExecutionStrategy::class);
            }

            $definitions[] = $flow;
        }

        return new FlowRegistry(...$definitions);
    }
}
