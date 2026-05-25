<?php

namespace Mhmfajar\PaymentOrchestrator\Constants;

/**
 * Gateway failure reasons used to decide whether fallback is safe.
 */
class GatewayFailureReason
{
    /**
     * Gateway request timed out before a payable transaction was created.
     *
     * @var string
     */
    const CONNECTION_TIMEOUT = 'connection_timeout';

    /**
     * Gateway was unavailable before a payable transaction was created.
     *
     * @var string
     */
    const GATEWAY_UNAVAILABLE = 'gateway_unavailable';

    /**
     * Gateway returned a server-side failure.
     *
     * @var string
     */
    const SERVER_ERROR = 'server_error';

    /**
     * Gateway response could not be parsed into a safe payment response.
     *
     * @var string
     */
    const INVALID_GATEWAY_RESPONSE = 'invalid_gateway_response';

    /**
     * Gateway health check failed before payment creation.
     *
     * @var string
     */
    const HEALTH_CHECK_FAILED = 'health_check_failed';

    /**
     * User or merchant data failed gateway validation.
     *
     * @var string
     */
    const VALIDATION_ERROR = 'validation_error';

    /**
     * Callback or request signature verification failed.
     *
     * @var string
     */
    const INVALID_SIGNATURE = 'invalid_signature';

    /**
     * Failure reason could not be classified.
     *
     * @var string
     */
    const UNKNOWN = 'unknown';

    /**
     * Return failure reasons that are safe for fallback.
     *
     * @return array
     */
    public static function fallbackAllowed()
    {
        return array(self::CONNECTION_TIMEOUT, self::GATEWAY_UNAVAILABLE, self::SERVER_ERROR, self::INVALID_GATEWAY_RESPONSE, self::HEALTH_CHECK_FAILED);
    }
}
