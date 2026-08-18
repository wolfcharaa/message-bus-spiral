<?php

declare(strict_types=1);

namespace Wolfcharaa\MessageBus\Spiral\Runtime;

use Wolfcharaa\MessageBus\Flow\FlowDefinition;
use Wolfcharaa\MessageBus\Flow\TransportDefinition;
use Wolfcharaa\MessageBus\Queue\QueueDeliveryOptions;
use Wolfcharaa\MessageBus\Registry\HandlerBindingDefinition;

final readonly class RuntimeHandlerPlan
{
    /**
     * @param list<class-string> $middleware
     */
    public function __construct(
        public HandlerBindingDefinition $binding,
        public FlowDefinition $flow,
        public array $middleware,
        public QueueDeliveryOptions $delivery,
        public ?TransportDefinition $transport,
    ) {
    }
}
