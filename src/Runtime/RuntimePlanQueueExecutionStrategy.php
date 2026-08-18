<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Runtime;

use RuntimeException;
use Wolfcharaa\MessageBus\Execution\ExecutionRequest;
use Wolfcharaa\MessageBus\Execution\HandlerExecutionResult;
use Wolfcharaa\MessageBus\Execution\HandlerExecutionResultInterface;
use Wolfcharaa\MessageBus\Execution\HandlerExecutionStrategyInterface;
use Wolfcharaa\MessageBus\Execution\HandlerResult;
use Wolfcharaa\MessageBus\Queue\QueueMessage;

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
            );

            $result = $provider->enqueue($queueMessage);
            $results[] = HandlerResult::success($binding->bindingId, $binding->action, $result);
        }

        return new HandlerExecutionResult(...$results);
    }
}
