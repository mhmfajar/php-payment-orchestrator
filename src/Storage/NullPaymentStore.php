<?php

namespace Mhmfajar\PaymentOrchestrator\Storage;

/**
 * Alias store for callers that want no external persistence in early integration.
 *
 * This class intentionally inherits all in-memory behavior while naming the no-external-storage use case.
 */
class NullPaymentStore extends InMemoryPaymentStore
{
}
