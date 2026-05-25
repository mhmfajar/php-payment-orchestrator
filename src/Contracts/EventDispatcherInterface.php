<?php

namespace Mhmfajar\PaymentOrchestrator\Contracts;

/**
 * Allows lifecycle events to be dispatched without depending on a framework event bus.
 */
interface EventDispatcherInterface
{
    /**
     * Dispatch a payment lifecycle event.
     *
     * @param object $event Event object.
     * @return void|null Dispatcher-specific result.
     */
    public function dispatch($event);
}
