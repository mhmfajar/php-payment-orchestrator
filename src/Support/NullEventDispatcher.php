<?php

namespace Mhmfajar\PaymentOrchestrator\Support;

use Mhmfajar\PaymentOrchestrator\Contracts\EventDispatcherInterface;

/**
 * No-op event dispatcher used when the application does not register listeners.
 */
class NullEventDispatcher implements EventDispatcherInterface
{
    /**
     * Ignore dispatched events.
     *
     * @param object $event Event object.
     * @return null
     */
    public function dispatch($event)
    {
        return null;
    }
}
