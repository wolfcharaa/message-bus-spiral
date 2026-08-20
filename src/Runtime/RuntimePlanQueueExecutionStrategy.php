<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Runtime;

use RuntimeException;
use Wolfcharaa\MessageBus\Execution\ExecutionRequest;
use Wolfcharaa\MessageBus\Execution\HandlerExecutionResult;
use Wolfcharaa\MessageBus\Execution\HandlerExecutionResultInterface;
use Wolfcharaa\MessageBus\Execution\HandlerExecutionStrategyInterface;
use Wolfcharaa\MessageBus\Execution\HandlerResult;
use Wolfcharaa\MessageBus\PublishedExecution;
use Wolfcharaa\MessageBus\Queue\QueueEnqueueFailed;
use Wolfcharaa\MessageBus\Queue\QueueMessage;
use Wolfcharaa\MessageBus\Queue\RetryPolicy;
use Wolfcharaa\MessageBus\Queue\RetryPolicyRegistryInterface;
use Wolfcharaa\MessageBus\Queue\RetryPolicySnapshot;

final class RuntimePlanQueueExecutionStrategy implements HandlerExecutionStrategyInterface
{
    public function __construct(private readonly RuntimePlanRegistry $plans)
    {
    }

    public function execute(ExecutionRequest $request): HandlerExecutionResultInterface
    {
        $provider = $request->environment->queueProvider
            ?? throw new RuntimeException('Queue provider is required for async flow execution.');

        $results = [];

        foreach ($this->plans->plansForBindings($request->bindings) as $plan) {
            $binding = $plan->binding;
            if ($binding->bindingId === null) {
                throw new RuntimeException('Async binding must have stable bindingId.');
            }

            $transport = $plan->transport
                ?? throw new RuntimeException(\sprintf('Async flow `%s` has no transport configuration.', $binding->flow));

            $envelope = $request->context->envelope()->withFlowBinding($binding->flow, $binding->bindingId);
            $serialized = $request->environment->envelopeSerializer->serialize($envelope);
            $delivery = $plan->delivery->merge($request->options->delivery);
            $retryPolicyKey = $delivery->retryPolicy ?? RetryPolicySnapshot::DEFAULT_KEY;
            $queueMessage = new QueueMessage(
                $transport->transport,
                $transport->queue,
                $serialized,
                $envelope->messageId,
                $envelope->correlationId,
                $binding->flow,
                $binding->bindingId,
                $request->environment->clock->now()->modify('+' . ($delivery->delaySeconds ?? 0) . ' seconds'),
                $delivery->priority ?? 0,
                $retryPolicyKey,
                $this->retryPolicySnapshot($retryPolicyKey, $request->environment->retryPolicyRegistry),
            );

            try {
                $result = $provider->enqueue($queueMessage);
                $results[] = HandlerResult::success(
                    $binding->bindingId,
                    $binding->action,
                    PublishedExecution::queued($queueMessage, $result),
                );
            } catch (\Throwable $e) {
                $results[] = HandlerResult::failure($binding->bindingId, $binding->action, new QueueEnqueueFailed($queueMessage, $e));
            }
        }

        return new HandlerExecutionResult(...$results);
    }

    private function retryPolicySnapshot(string $key, ?RetryPolicyRegistryInterface $registry): RetryPolicySnapshot
    {
        if ($registry !== null) {
            return RetryPolicySnapshot::fromPolicy($registry->get($key));
        }

        if ($key !== RetryPolicySnapshot::DEFAULT_KEY) {
            throw new RuntimeException(\sprintf(
                'Retry policy `%s` requires retry policy registry.',
                $key,
            ));
        }

        return RetryPolicySnapshot::fromPolicy(RetryPolicy::exponential(3, 30, 2.0, 300));
    }
}
