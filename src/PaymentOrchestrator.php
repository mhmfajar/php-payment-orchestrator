<?php

namespace Mhmfajar\PaymentOrchestrator;

use Mhmfajar\PaymentOrchestrator\Contracts\EventDispatcherInterface;
use Mhmfajar\PaymentOrchestrator\Contracts\PaymentStoreInterface;
use Mhmfajar\PaymentOrchestrator\Managers\GatewayManager;
use Mhmfajar\PaymentOrchestrator\Managers\PaymentManager;
use Mhmfajar\PaymentOrchestrator\Storage\InMemoryPaymentStore;
use Mhmfajar\PaymentOrchestrator\Support\CallableEventDispatcher;
use Mhmfajar\PaymentOrchestrator\Support\Config;
use Mhmfajar\PaymentOrchestrator\Support\FallbackPolicy;
use Mhmfajar\PaymentOrchestrator\Support\NullLogger;
use Mhmfajar\PaymentOrchestrator\Support\StatusMapper;
use Psr\Log\LoggerInterface;

/**
 * Public facade for creating payments, registering gateways, and handling callbacks.
 */
class PaymentOrchestrator
{
    /**
     * Framework-free configuration wrapper.
     *
     * @var Config
     */
    private $config;

    /**
     * Store used for payments and attempts.
     *
     * @var PaymentStoreInterface
     */
    private $store;

    /**
     * Gateway resolver and registry.
     *
     * @var GatewayManager
     */
    private $gatewayManager;

    /**
     * Lifecycle event dispatcher.
     *
     * @var EventDispatcherInterface
     */
    private $events;

    /**
     * Logger used by gateway exception handling.
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Create an orchestrator with default in-memory services.
     *
     * @param array $config Package configuration.
     * @return void
     */
    public function __construct(array $config)
    {
        // Defaults keep native PHP usage working without requiring a framework container.
        $this->config = new Config($config);
        $this->store = new InMemoryPaymentStore();
        $this->events = new CallableEventDispatcher();
        $this->logger = new NullLogger();
        $this->gatewayManager = new GatewayManager($this->config, new StatusMapper());
    }

    /**
     * Static constructor for fluent native PHP usage.
     *
     * @param array $config Package configuration.
     * @return self New orchestrator instance.
     */
    public static function make(array $config)
    {
        return new self($config);
    }

    /**
     * Replace the default store with an application persistence implementation.
     *
     * @param PaymentStoreInterface $store Store implementation.
     * @return $this
     */
    public function setStore(PaymentStoreInterface $store)
    {
        $this->store = $store;
        return $this;
    }

    /**
     * Attach a PSR-3 logger.
     *
     * @param LoggerInterface $logger Logger implementation.
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Replace the default event dispatcher.
     *
     * @param EventDispatcherInterface $events Event dispatcher implementation.
     * @return $this
     */
    public function setEventDispatcher(EventDispatcherInterface $events)
    {
        $this->events = $events;
        return $this;
    }

    /**
     * Register a callable listener when using the native callable dispatcher.
     *
     * @param string $eventClass Event class name.
     * @param callable $listener Listener callback.
     * @return $this
     */
    public function on($eventClass, callable $listener)
    {
        if ($this->events instanceof CallableEventDispatcher) {
            $this->events->listen($eventClass, $listener);
        }

        return $this;
    }

    /**
     * Register or override a gateway driver factory.
     *
     * @param string $name Gateway name.
     * @param callable $creator Gateway factory.
     * @return $this
     */
    public function extend($name, callable $creator)
    {
        $this->gatewayManager->extend($name, $creator);
        return $this;
    }

    /**
     * Create a payment from an array payload or PaymentRequest.
     *
     * @param array|PaymentRequest $payload Payment request payload.
     * @return PaymentResponse Normalized payment response.
     */
    public function create($payload)
    {
        return $this->manager()->create($payload);
    }

    /**
     * Process a gateway callback payload.
     *
     * @param string $gateway Gateway name.
     * @param array $payload Raw callback payload.
     * @return \Mhmfajar\PaymentOrchestrator\DTO\CallbackResponse Normalized callback response.
     */
    public function handleCallback($gateway, array $payload)
    {
        return $this->manager()->handleCallback($gateway, $payload);
    }

    /**
     * Expose the gateway manager for advanced integrations.
     *
     * @return GatewayManager Gateway manager instance.
     */
    public function gatewayManager()
    {
        return $this->gatewayManager;
    }

    /**
     * Build a fresh payment manager with current mutable dependencies.
     *
     * @return PaymentManager Payment manager instance.
     */
    private function manager()
    {
        return new PaymentManager(
            $this->store,
            $this->gatewayManager,
            new FallbackPolicy($this->config->get('fallback', array())),
            $this->events,
            $this->logger
        );
    }
}
