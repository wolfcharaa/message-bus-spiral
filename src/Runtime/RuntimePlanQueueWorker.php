<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Runtime;

use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Wolfcharaa\MessageBus\Context\MessageContextFactoryInterface;
use Wolfcharaa\MessageBus\Context\MessageContextInterface;
use Wolfcharaa\MessageBus\Envelope\Envelope;
use Wolfcharaa\MessageBus\Envelope\EnvelopeSerializerInterface;
use Wolfcharaa\MessageBus\Envelope\SerializedEnvelope;
use Wolfcharaa\MessageBus\Execution\ExecutionEnvironment;
use Wolfcharaa\MessageBus\Execution\ExecutionRequest;
use Wolfcharaa\MessageBus\Flow\FlowDefinition;
use Wolfcharaa\MessageBus\Flow\FlowRegistry;
use Wolfcharaa\MessageBus\Invoker\CallableInvokerInterface;
use Wolfcharaa\MessageBus\MessageBusInterface;
use Wolfcharaa\MessageBus\PublishOptions;
use Wolfcharaa\MessageBus\Queue\QueueProviderInterface;
use Wolfcharaa\MessageBus\Queue\QueueWorkerInterface;
use Wolfcharaa\MessageBus\Worker\WorkerRuntimeControlScope;

final class RuntimePlanQueueWorker implements QueueWorkerInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly RuntimePlanRegistry $plans,
        private readonly FlowRegistry $flows,
        private readonly EnvelopeSerializerInterface $serializer,
        private readonly CallableInvokerInterface $invoker,
        private readonly ContainerInterface $container,
        private readonly ClockInterface $clock,
        private readonly RuntimePlanSequentialExecutionStrategy $strategy,
        private readonly ?QueueProviderInterface $queueProvider = null,
    ) {
    }

    public function handle(SerializedEnvelope $envelope): mixed
    {
        $envelope = $this->serializer->deserialize($envelope);

        if ($envelope->bindingId === null) {
            throw new RuntimeException('Envelope bindingId is required for worker execution.');
        }

        $binding = $this->plans->binding($envelope->bindingId);
        $flow = $this->flows->get($binding->flow);
        $context = $this->createContext($flow, $envelope);
        $result = $this->strategy->execute(new ExecutionRequest(
            [$binding],
            $context,
            $flow,
            new PublishOptions(),
            new ExecutionEnvironment($this->invoker, $this->serializer, $this->clock, $this->queueProvider),
        ));

        return $result->get($binding->bindingId ?? '');
    }

    private function createContext(FlowDefinition $flow, Envelope $envelope): MessageContextInterface
    {
        if ($flow->contextFactory === null) {
            throw new RuntimeException(\sprintf('Flow `%s` has no context factory.', $flow->key));
        }

        $factory = $this->container->get($flow->contextFactory);

        if (!$factory instanceof MessageContextFactoryInterface) {
            throw new RuntimeException(\sprintf(
                'Flow `%s` context factory `%s` must implement `%s`.',
                $flow->key,
                $flow->contextFactory,
                MessageContextFactoryInterface::class,
            ));
        }

        $context = $factory->create($this->messageBus, $envelope, $flow, WorkerRuntimeControlScope::current());

        if (!$context instanceof $flow->contextInterface) {
            throw new RuntimeException(\sprintf(
                'Flow `%s` context factory returned `%s`, expected `%s`.',
                $flow->key,
                $context::class,
                $flow->contextInterface,
            ));
        }

        return $context;
    }
}
