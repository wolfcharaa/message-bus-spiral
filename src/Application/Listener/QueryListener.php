<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Application\Listener;

use ReflectionClass;
use Spiral\Core\Attribute\Singleton;
use Spiral\Tokenizer\Attribute\TargetAttribute;
use Spiral\Tokenizer\TokenizationListenerInterface;
use Wolfcharaa\MessageBus\Attribute\Async;
use Wolfcharaa\MessageBus\Attribute\Handler as AttributeHandler;
use Wolfcharaa\MessageBus\Message\Message;
use Wolfcharaa\MessageBus\Message\Query;

#[Singleton]
#[TargetAttribute(AttributeHandler::class)]
class QueryListener implements TokenizationListenerInterface
{
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

                /** @var array<null|\ReflectionType|\ReflectionNamedType> $types */
                $types = is_array($type) ? $type : [$type];

                foreach ($types as $typeReflection) {
                    $messageClass = $typeReflection?->getName();
                    if (!is_subclass_of($messageClass, Query::class)) {
                        return;
                    }

                    $reflectionMessage = new ReflectionClass($messageClass);

                    $attributes = $reflectionMessage->getAttributes(Async::class);

                    if (empty($attributes)) {
                        return;
                    }

                    throw new \RuntimeException(sprintf(
                        'The handler of the `%s%s%s` command contains query class `%s`,'
                        . ' which cannot be processed in async',
                        $class->getName(),
                        $method->isStatic() ? '::' : '->',
                        $method->getName(),
                        $reflectionMessage->getName()
                    ));
                }

                break;
            }
        }
    }

    public function finalize(): void
    {
        // do nothing
    }
}
