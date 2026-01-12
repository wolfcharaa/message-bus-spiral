<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\HandlerRegistry;

use Wolfcharaa\MessageBus\Attribute\Async;
use Wolfcharaa\MessageBus\Attribute\Handler as AttributeHandler;
use Wolfcharaa\MessageBus\Builder\HandlerBuilderInterface;
use Wolfcharaa\MessageBus\Handler\EventHandlers;
use Wolfcharaa\MessageBus\Handler\Handler;
use Wolfcharaa\MessageBus\HandlerRegistry\HandlerMessageExists;
use Wolfcharaa\MessageBus\HandlerRegistry\HandlerRegistry;
use Wolfcharaa\MessageBus\HandlerRegistry\HandlerRegistryInterface;
use Wolfcharaa\MessageBus\Message\Event;
use Wolfcharaa\MessageBus\Message\Message;
use Wolfcharaa\MessageBus\Spiral\Application\Config\MessageBusConfig;
use Wolfcharaa\MessageBus\Spiral\Middleware\SpiralAsyncMessageMiddleware;
use ReflectionClass;
use Spiral\Core\Attribute\Singleton;
use Spiral\Tokenizer\Attribute\TargetAttribute;
use Spiral\Tokenizer\TokenizationListenerInterface;

#[Singleton]
#[TargetAttribute(AttributeHandler::class)]
final class SpiralHandlerRegistry extends HandlerRegistry implements TokenizationListenerInterface
{
    /**
     * @param array<class-string<Message>, Handler> $handlersByMessageClass
     */
    private array $handlersByMessageClass = [];

    public function __construct(
        private readonly HandlerBuilderInterface $builder,
        private readonly MessageBusConfig $messageBusConfig,
    ) {
    }

    /**
     * @template TResult
     * @template TMessage of Message<TResult>
     * @param class-string<TMessage> $messageClass
     * @return ?Handler<TResult, TMessage>
     */
    public function find(string $messageClass): ?Handler
    {
        return $this->handlersByMessageClass[$messageClass] ?? null;
    }

    /**
     * @throws HandlerMessageExists
     */
    public function listen(\ReflectionClass $class): void
    {
        if (!$class->isInstantiable()) {
            return;
        }

        foreach ($class->getMethods() as $method) {
            if (!$method->isPublic()) {
                continue;
            }

            $attributes = $method->getAttributes(AttributeHandler::class);
            if (empty($attributes)) {
                continue;
            }

            $parameters = $method->getParameters();

            if (\count($parameters) === 0) {
                throw new \RuntimeException(sprintf(
                    'The `%s` command handler must have at least one `%s` parameter.',
                    $class->getName(),
                    Message::class,
                ));
            }

            foreach ($method->getParameters() as $parameter) {
                /** @var \ReflectionUnionType $type */
                $type = $parameter->getType();

                if ($type instanceof \ReflectionUnionType) {
                    $type = $type->getTypes();
                }

                /**
                 * @var null|\ReflectionType|\ReflectionNamedType $typeReflection
                 */
                foreach (is_array($type) ? $type : [$type] as $typeReflection) {
                    $messageClass = $typeReflection?->getName();
                    if (!is_a($messageClass, Message::class, true)) {
                        throw new \RuntimeException(sprintf(
                            'The command handler `%s%s%s` must implement `%s` for the first argument',
                            $class->getName(),
                            $method->isStatic() ? '::' : '->',
                            $method->getName(),
                            Message::class
                        ));
                    }

                    $reflectionMessage = new ReflectionClass($messageClass);
                    $attributeAsync = $reflectionMessage->getAttributes(Async::class)[0] ?? null;
                    /** @var AttributeHandler $attribute */
                    $attribute = $attributes[0]->newInstance();
                    $middleware = $attributeAsync === null
                        ? [
                            ...($this->messageBusConfig->getMiddlewareGroup($attribute->group)),
                            ...$attribute->middleware
                        ]
                        : [
                            SpiralAsyncMessageMiddleware::class,
                            ...($this->messageBusConfig->getMiddlewareGroup($attribute->group)),
                            ...$attribute->middleware
                        ];

                    $this->addHandler(
                        $messageClass,
                        (clone $this->builder)
                            ->withMiddleware(...array_unique($middleware))
                            ->build([$class->getName(), $method->getName()])
                    );
                }

                break;
            }
        }
    }

    public function finalize(): void
    {
        // do nothing
    }

    public function addHandler(string $messageClass, Handler $handler): HandlerRegistryInterface
    {
        if (is_a($messageClass, Event::class, true)) {
            $handler = ($this->handlers[$messageClass] ?? new EventHandlers())
                ->withHandler($handler);
        } elseif (isset($this->handlers[$messageClass])) {
            throw new HandlerMessageExists($messageClass);
        }

        $this->handlersByMessageClass[$messageClass] = $handler;

        return $this;
    }


    public function addHandlers(array $handlers): HandlerRegistryInterface
    {
        foreach ($handlers as $messageClass => $handler) {
            $this->addHandler($messageClass, $handler);
        }

        return $this;
    }
}
