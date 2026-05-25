<?php

namespace Mhmfajar\PaymentOrchestrator\Support;

use Mhmfajar\PaymentOrchestrator\Contracts\EventDispatcherInterface;

/**
 * Lightweight native PHP event dispatcher backed by callables.
 */
class CallableEventDispatcher implements EventDispatcherInterface
{
    /**
     * Listener callbacks grouped by event class.
     *
     * @var array
     */
    private $listeners = array();

    /**
     * Register a listener for a specific event class.
     *
     * @param string $eventClass Event class name.
     * @param callable $listener Listener callback.
     * @return void
     */
    public function listen($eventClass, callable $listener)
    {
        if (! isset($this->listeners[$eventClass])) {
            $this->listeners[$eventClass] = array();
        }

        $this->listeners[$eventClass][] = $listener;
    }

    /**
     * Dispatch an event object to matching listeners.
     *
     * @param object $event Event object.
     * @return void
     */
    public function dispatch($event)
    {
        // $eventClass is used as the listener lookup key.
        $eventClass = get_class($event);
        $listeners = isset($this->listeners[$eventClass]) ? $this->listeners[$eventClass] : array();

        foreach ($listeners as $listener) {
            call_user_func($listener, $event);
        }
    }
}
