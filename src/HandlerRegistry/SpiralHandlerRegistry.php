<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\HandlerRegistry;

use ReflectionAttribute;
use Spiral\Core\Attribute\Singleton;
use Spiral\Tokenizer\Attribute\TargetAttribute;
use Spiral\Tokenizer\TokenizationListenerInterface;
use Wolfcharaa\MessageBus\Builder\HandlerBuilderInterface;
use Wolfcharaa\MessageBus\Handler\EventHandlers;
use Wolfcharaa\MessageBus\HandlerRegistry\HandlerMessageExists;
use Wolfcharaa\MessageBus\HandlerRegistry\HandlerRegistry;
use Wolfcharaa\MessageBus\HandlerRegistry\HandlerRegistryInterface;
use Wolfcharaa\MessageBus\HandlerRegistry\MessageDefinition;
use Wolfcharaa\MessageBus\Spiral\Application\Config\MessageBusConfig;
use Wolfcharaa\MessageBus\Spiral\Attribute\Handler;
use Wolfcharaa\MessageBus\Spiral\Attribute\Message;

#[Singleton]
#[TargetAttribute(Handler::class)]
final class SpiralHandlerRegistry extends HandlerRegistry implements TokenizationListenerInterface
{
    /**
     * @param array<class-string<Message>|string, MessageDefinition> $definitions
     */
    private array $definitions = [];

    /** @var array<string, class-string<Message|object>> $aliases */
    private array $aliases = [];

    public function __construct(
        private readonly HandlerBuilderInterface $builder,
        private readonly MessageBusConfig $messageBusConfig,
    ) {
    }

    /**
     * @template TResult
     * @template TMessage of Message<TResult>
     * @param class-string<TMessage|object> $messageClass
     * @return ?MessageDefinition
     */
    #[\Override]
    public function find(string $messageClass): MessageDefinition
    {
        if (isset($this->aliases[$messageClass])) {
            $messageClass = $this->aliases[$messageClass];
        }

        return $this->definitions[$messageClass]
            ?? (new MessageDefinition($messageClass))->setIsEvent(true);
    }

    /**
     * @throws HandlerMessageExists
     */
    public function listen(\ReflectionClass $class): void
    {
        if (!$class->isInstantiable()) {
            return;
        }

        $reflHandlerAttribute = current($class->getAttributes(Handler::class));
        \assert($reflHandlerAttribute instanceof \ReflectionAttribute);
        /** @var Handler $handlerAttribute */
        $handlerAttribute = $reflHandlerAttribute->newInstance();

        $reflectionMethod = $class->getMethod('__invoke');
        $parameters = $reflectionMethod->getParameters();

        if (\count($parameters) === 0) {
            throw new \RuntimeException(\sprintf(
                'The `%s` command handler must have at least'
                . ' one object parameter implement `%s` attribute.',
                $class->getName(),
                Message::class,
            ));
        }

        $parameter = $parameters[0];

        /** @var \ReflectionUnionType $type */
        $type = $parameter->getType();

        if ($type instanceof \ReflectionUnionType) {
            $type = $type->getTypes();
        }

        /**
         * @var null|\ReflectionType|\ReflectionNamedType $typeReflection
         */
        foreach (is_array($type) ? $type : [$type] as $typeReflection) {
            if ($typeReflection === null) {
                continue;
            }

            $messageClass = $typeReflection->getName();
            $messageDefinition = (new MessageDefinition($messageClass))
                ->setHandlerFactory([$class->getName(), $reflectionMethod->getName()]);

            /** @var ReflectionAttribute|false $reflMessageAttribute */
            $reflMessageAttribute = current($class->getAttributes(Message::class));

            if ($reflMessageAttribute) {
                /** @var Message $messageAttribute */
                $messageAttribute = $reflMessageAttribute->newInstance();

                if ($messageAttribute->alias !== null) {
                    $messageDefinition->setAlias($messageAttribute->alias);
                }

                $messageDefinition->setIsEvent($messageAttribute->isEvent);
            }

            $middleware = $handlerAttribute->group !== null
                ? [
                    ...$this->messageBusConfig->getMiddlewareGroup($handlerAttribute->group),
                    ...$handlerAttribute->middleware
                ]
                : $handlerAttribute->middleware;

            $messageDefinition->setMiddleware(...$middleware);

            $this->addHandler($messageDefinition);
        }
    }

    public function finalize(): void
    {
        // do nothing
    }

    public function addHandler(MessageDefinition $definition): HandlerRegistryInterface
    {
        if ($definition->getAlias() !== null) {
            $this->aliases[$definition->getAlias()] = $definition->getMessageClass();
        }

        if ($definition->isEvent()) {
            $eventHandler = new EventHandlers();

            /** @var ?MessageDefinition $prevMessageDefinition */
            $prevMessageDefinition = $this->definitions[$definition->getMessageClass()] ?? null;

            if ($prevMessageDefinition !== null && $prevMessageDefinition->getHandler() !== null) {
                $prevHandler = $prevMessageDefinition->getHandler();
                $eventHandler = $prevHandler instanceof EventHandlers
                    ? $prevHandler
                    : $eventHandler->withHandler($prevHandler);
            }

            $definition->setHandler($eventHandler->withHandler(
                (clone $this->builder)
                    ->withMiddleware(...array_unique($definition->getMiddleware()))
                    ->build($definition->getFactoryHandlers()[0])
            ));
        } else {
            $definition->setHandler((clone $this->builder)
                ->withMiddleware(...array_unique($definition->getMiddleware()))
                ->build($definition->getFactoryHandlers()[0]));
        }

        $this->definitions[$definition->getMessageClass()] = $definition;

        return $this;
    }
}
