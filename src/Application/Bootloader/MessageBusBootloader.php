<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Application\Bootloader;

use Wolfcharaa\MessageBus\Builder\HandlerBuilderInterface;
use Wolfcharaa\MessageBus\HandlerRegistry\HandlerRegistryInterface;
use Wolfcharaa\MessageBus\Message\MessageId\IncrementalMessageIdGenerator;
use Wolfcharaa\MessageBus\Message\MessageId\MessageIdGenerator;
use Wolfcharaa\MessageBus\Message\MessageId\RandomMessageIdGenerator;
use Wolfcharaa\MessageBus\MessageBus;
use Wolfcharaa\MessageBus\MessageBusInterface;
use Wolfcharaa\MessageBus\Spiral\Application\Listener\QueryListener;
use Wolfcharaa\MessageBus\Spiral\HandlerRegistry\SpiralHandlerRegistry;
use Wolfcharaa\MessageBus\Spiral\Builder\SpiralBuilder;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\Environment;
use Spiral\Core\Attribute\Singleton;
use Spiral\Queue\Bootloader\QueueBootloader;
use Spiral\Tokenizer\Bootloader\TokenizerListenerBootloader;

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
        HandlerBuilderInterface::class => SpiralBuilder::class,
        HandlerRegistryInterface::class => SpiralHandlerRegistry::class,
        MessageBusInterface::class => MessageBus::class,
        MessageIdGenerator::class => [self::class, 'createMessageId'],
    ];

    public function boot(
        TokenizerListenerBootloader $tokenizer,
        SpiralHandlerRegistry $listener,
        QueryListener $queryListener,
    ): void {
        $tokenizer->addListener($listener);
        $tokenizer->addListener($queryListener);
    }

    protected function createMessageId(Environment $environment): MessageIdGenerator
    {
        if ($environment->get('APP_ENV') === 'prod') {
            return new RandomMessageIdGenerator();
        }

        return new IncrementalMessageIdGenerator();
    }
}
