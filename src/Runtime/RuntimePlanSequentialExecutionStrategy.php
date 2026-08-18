<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Runtime;

use Wolfcharaa\MessageBus\Execution\ExecutionRequest;
use Wolfcharaa\MessageBus\Execution\HandlerExecutionResult;
use Wolfcharaa\MessageBus\Execution\HandlerExecutionResultInterface;
use Wolfcharaa\MessageBus\Execution\HandlerExecutionStrategyInterface;
use Wolfcharaa\MessageBus\Execution\HandlerResult;
use Wolfcharaa\MessageBus\Middleware\Pipeline;

final class RuntimePlanSequentialExecutionStrategy implements HandlerExecutionStrategyInterface
{
    public function __construct(
        private readonly RuntimePlanRegistry $plans,
        private readonly bool $failFast = true,
    ) {
    }

    public function execute(ExecutionRequest $request): HandlerExecutionResultInterface
    {
        $results = [];

        foreach ($this->plans->plansForBindings($request->bindings) as $plan) {
            try {
                $pipeline = new Pipeline(
                    $plan->binding,
                    $request->context,
                    $request->environment->invoker,
                    $plan->middleware,
                );
                $results[] = HandlerResult::success(
                    $plan->binding->bindingId ?? '',
                    $plan->binding->action,
                    $pipeline->continue(),
                );
            } catch (\Throwable $e) {
                if ($this->failFast) {
                    throw $e;
                }

                $results[] = HandlerResult::failure($plan->binding->bindingId ?? '', $plan->binding->action, $e);
            }
        }

        return new HandlerExecutionResult(...$results);
    }
}
