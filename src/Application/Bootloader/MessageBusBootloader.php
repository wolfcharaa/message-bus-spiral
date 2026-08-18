<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Application\Bootloader;

use Psr\Clock\ClockInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\Environment;
use Spiral\Core\Attribute\Singleton;
use Spiral\Queue\Bootloader\QueueBootloader;
use Spiral\Queue\QueueConnectionProviderInterface;
use Wolfcharaa\MessageBus\Clock\WallClock;
use Wolfcharaa\MessageBus\Envelope\DefaultEnvelopeSerializer;
use Wolfcharaa\MessageBus\Envelope\EnvelopeSerializerInterface;
use Wolfcharaa\MessageBus\Flow\FlowRegistry;
use Wolfcharaa\MessageBus\Invoker\CallableInvokerInterface;
use Wolfcharaa\MessageBus\Invoker\ServiceResolverInterface;
use Wolfcharaa\MessageBus\Message\IncrementalMessageIdGenerator;
use Wolfcharaa\MessageBus\Message\MessageIdGenerator;
use Wolfcharaa\MessageBus\Message\RandomMessageIdGenerator;
use Wolfcharaa\MessageBus\MessageBus;
use Wolfcharaa\MessageBus\MessageBusInterface;
use Wolfcharaa\MessageBus\Queue\MessageBusQueueWorker;
use Wolfcharaa\MessageBus\Queue\QueueProviderInterface;
use Wolfcharaa\MessageBus\Queue\QueueWorkerInterface;
use Wolfcharaa\MessageBus\Registry\CompiledMessageRegistry;
use Wolfcharaa\MessageBus\Registry\MessageRegistryInterface;
use Wolfcharaa\MessageBus\Serialization\JsonMessageSerializer;
use Wolfcharaa\MessageBus\Spiral\Application\Config\MessageBusConfig;
use Wolfcharaa\MessageBus\Spiral\Application\Job\QueueHandlerJob;
use Wolfcharaa\MessageBus\Spiral\Invoker\SpiralCallableInvoker;
use Wolfcharaa\MessageBus\Spiral\Invoker\SpiralServiceResolver;
use Wolfcharaa\MessageBus\Spiral\Queue\SpiralQueueProvider;

/**
 * @link https://spiral.dev/docs/http-interceptors
 */
#[Singleton]
final class MessageBusBootloader extends Bootloader
{
    protected const DEPENDENCIES = [
        QueueBootloader::class,
        \Spiral\RoadRunnerBridge\Bootloader\QueueBootloader::class,
    ];

    protected const SINGLETONS = [
        ClockInterface::class => WallClock::class,
        CallableInvokerInterface::class => SpiralCallableInvoker::class,
        ServiceResolverInterface::class => SpiralServiceResolver::class,
        QueueProviderInterface::class => [self::class, 'createQueueProvider'],
        QueueWorkerInterface::class => MessageBusQueueWorker::class,
        MessageRegistryInterface::class => [self::class, 'createRegistry'],
        FlowRegistry::class => [self::class, 'createFlowRegistry'],
        EnvelopeSerializerInterface::class => [self::class, 'createEnvelopeSerializer'],
        MessageBusInterface::class => [self::class, 'createMessageBus'],
        MessageIdGenerator::class => [self::class, 'createMessageId'],
    ];

    public function createRegistry(MessageBusConfig $config): MessageRegistryInterface
    {
        $file = $config->getRegistryFile();
        if ($file === null) {
            throw new \RuntimeException(
                'MessageBus registry file is not configured. Set `message_bus.registryFile` to compiled registry path.'
            );
        }

        return CompiledMessageRegistry::fromFile($file);
    }

    public function createFlowRegistry(MessageRegistryInterface $registry): FlowRegistry
    {
        if (!$registry instanceof CompiledMessageRegistry) {
            return new FlowRegistry();
        }

        return $registry->definition()->flows;
    }

    public function createEnvelopeSerializer(MessageRegistryInterface $registry): EnvelopeSerializerInterface
    {
        if (!$registry instanceof CompiledMessageRegistry) {
            throw new \RuntimeException('Default Spiral envelope serializer requires CompiledMessageRegistry.');
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
        ServiceResolverInterface $resolver,
        MessageIdGenerator $messageIdGenerator,
        ClockInterface $clock,
    ): MessageBusInterface {
        return new MessageBus(
            $registry,
            $flows,
            $queueProvider,
            $serializer,
            $invoker,
            $resolver,
            $messageIdGenerator,
            $clock,
        );
    }

    protected function createMessageId(Environment $environment): MessageIdGenerator
    {
        if ($environment->get('APP_ENV') === 'prod') {
            return new RandomMessageIdGenerator();
        }

        return new IncrementalMessageIdGenerator();
    }
}
