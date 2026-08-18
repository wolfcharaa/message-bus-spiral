<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Runtime;

use Wolfcharaa\MessageBus\Flow\FlowRegistry;
use Wolfcharaa\MessageBus\Queue\QueueDeliveryOptions;
use Wolfcharaa\MessageBus\Registry\BindingNotFound;
use Wolfcharaa\MessageBus\Registry\CompiledMessageRegistry;
use Wolfcharaa\MessageBus\Registry\HandlerBindingDefinition;
use Wolfcharaa\MessageBus\Registry\MessageRegistryDefinition;
use Wolfcharaa\MessageBus\Registry\MessageRegistryInterface;
use Wolfcharaa\MessageBus\Serialization\MessageNameResolverInterface;

final class RuntimePlanRegistry implements MessageRegistryInterface, MessageNameResolverInterface
{
    /** @var array<string, RuntimeHandlerPlan> */
    private array $plans = [];

    /** @var array<string, list<HandlerBindingDefinition>> */
    private array $bindingsByMessage = [];

    public function __construct(private readonly MessageRegistryDefinition $definition)
    {
        $this->buildPlans();
    }

    public static function fromCompiled(CompiledMessageRegistry $registry): self
    {
        return new self($registry->definition());
    }

    public function definition(): MessageRegistryDefinition
    {
        return $this->definition;
    }

    public function flowRegistry(): FlowRegistry
    {
        return $this->definition->flows;
    }

    /** @return list<HandlerBindingDefinition> */
    public function bindingsForMessage(string $messageClass): array
    {
        return $this->bindingsByMessage[$messageClass] ?? [];
    }

    public function binding(string $bindingId): HandlerBindingDefinition
    {
        return $this->plans[$bindingId]->binding ?? throw new BindingNotFound(\sprintf(
            'Message binding `%s` was not found.',
            $bindingId,
        ));
    }

    public function plan(string $bindingId): RuntimeHandlerPlan
    {
        return $this->plans[$bindingId] ?? throw new BindingNotFound(\sprintf(
            'Message binding `%s` was not found.',
            $bindingId,
        ));
    }

    /**
     * @param list<HandlerBindingDefinition> $bindings
     * @return list<RuntimeHandlerPlan>
     */
    public function plansForBindings(array $bindings): array
    {
        $plans = [];

        foreach ($bindings as $binding) {
            if ($binding->bindingId === null) {
                throw new BindingNotFound(\sprintf(
                    'Message binding `%s -> %s` has no bindingId.',
                    $binding->message,
                    $binding->action,
                ));
            }

            $plans[] = $this->plan($binding->bindingId);
        }

        return $plans;
    }

    public function messageName(string $messageClass): ?string
    {
        return $this->definition->messageNames[$messageClass] ?? null;
    }

    public function messageClassForName(string $messageName): ?string
    {
        return $this->definition->aliases[$messageName] ?? null;
    }

    public function nameOf(object|string $message): string
    {
        $messageClass = \is_object($message) ? $message::class : $message;

        return $this->messageName($messageClass) ?? $messageClass;
    }

    public function classOf(string $name): string
    {
        return $this->messageClassForName($name) ?? (\class_exists($name) ? $name : throw new BindingNotFound(\sprintf(
            'Message class for name `%s` was not found.',
            $name,
        )));
    }

    private function buildPlans(): void
    {
        foreach ($this->definition->bindings as $binding) {
            \assert($binding->bindingId !== null);

            $flow = $this->definition->flows->get($binding->flow);
            $this->plans[$binding->bindingId] = new RuntimeHandlerPlan(
                $binding,
                $flow,
                [...$flow->middleware, ...$binding->middleware],
                ($flow->delivery ?? new QueueDeliveryOptions())->merge($binding->delivery),
                $flow->transport,
            );
        }

        foreach ($this->definition->messages as $message => $bindingIds) {
            $bindings = [];

            foreach ($bindingIds as $bindingId) {
                $bindings[] = $this->binding($bindingId);
            }

            \usort($bindings, static fn (HandlerBindingDefinition $a, HandlerBindingDefinition $b): int => $b->priority <=> $a->priority);
            $this->bindingsByMessage[$message] = $bindings;
        }
    }
}
