<?php

namespace Mhmfajar\PaymentOrchestrator\Managers;

use Mhmfajar\PaymentOrchestrator\Contracts\PaymentGatewayInterface;
use Mhmfajar\PaymentOrchestrator\Exceptions\GatewayNotConfiguredException;
use Mhmfajar\PaymentOrchestrator\Support\Config;
use Mhmfajar\PaymentOrchestrator\Support\StatusMapper;

/**
 * Resolves configured and custom gateway drivers for the payment manager.
 */
class GatewayManager
{
    /**
     * Gateway and fallback configuration.
     *
     * @var Config
     */
    private $config;

    /**
     * Instantiated gateway drivers keyed by gateway name.
     *
     * @var array
     */
    private $drivers = array();

    /**
     * Custom gateway creator callbacks keyed by gateway name.
     *
     * @var array
     */
    private $customCreators = array();

    /**
     * Shared mapper passed to gateway instances.
     *
     * @var StatusMapper
     */
    private $statusMapper;

    /**
     * Create a gateway manager from package configuration.
     *
     * @param Config $config Gateway and fallback configuration.
     * @param StatusMapper|null $statusMapper Optional shared status mapper.
     * @return void
     */
    public function __construct(Config $config, StatusMapper $statusMapper = null)
    {
        $this->config = $config;
        $this->statusMapper = $statusMapper ?: new StatusMapper();
    }

    /**
     * Register a custom gateway creator.
     *
     * @param string $name Gateway name.
     * @param callable $creator Factory receiving gateway config and returning a gateway.
     * @return void
     */
    public function extend($name, callable $creator)
    {
        $this->customCreators[$name] = $creator;
    }

    /**
     * Resolve a configured or custom gateway driver by name.
     *
     * @param string $name Gateway name.
     * @return PaymentGatewayInterface Resolved gateway driver.
     * @throws GatewayNotConfiguredException When the gateway cannot be resolved.
     */
    public function driver($name)
    {
        // Cache driver instances so custom creators and gateway state are stable per orchestrator.
        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        $gatewayConfig = $this->config->get('gateways.' . $name);

        if (! is_array($gatewayConfig)) {
            throw new GatewayNotConfiguredException('Gateway [' . $name . '] is not configured.');
        }

        if (isset($this->customCreators[$name])) {
            $gateway = call_user_func($this->customCreators[$name], $gatewayConfig);
        } else {
            if (! isset($gatewayConfig['driver'])) {
                throw new GatewayNotConfiguredException('Gateway [' . $name . '] driver is not configured.');
            }

            $class = $gatewayConfig['driver'];
            $gateway = new $class($gatewayConfig, $this->statusMapper);
        }

        if (! $gateway instanceof PaymentGatewayInterface) {
            throw new GatewayNotConfiguredException('Gateway [' . $name . '] must implement PaymentGatewayInterface.');
        }

        $this->drivers[$name] = $gateway;

        return $gateway;
    }

    /**
     * Build the primary-plus-fallback gateway sequence for a request.
     *
     * @param string|null $preferredGateway Optional gateway that should be tried first.
     * @return array Ordered gateway names.
     */
    public function sequence($preferredGateway = null)
    {
        // The preferred gateway becomes primary, followed by configured fallbacks without duplicates.
        $primary = $preferredGateway ?: $this->config->get('default');
        $fallbacks = $this->config->get('fallbacks.' . $primary, array());
        $sequence = array($primary);

        foreach ($fallbacks as $fallback) {
            if (! in_array($fallback, $sequence, true)) {
                $sequence[] = $fallback;
            }
        }

        $maxAttempts = (int) $this->config->get('fallback.max_attempts', count($sequence));

        if ($maxAttempts > 0) {
            return array_slice($sequence, 0, $maxAttempts);
        }

        return $sequence;
    }
}
