<?php



namespace App\DataTransferObjects;

/**
 * Provider-agnostic result of opening a checkout session.
 * Deliberately minimal: the rest of the app has no reason to see the raw Stripe object.
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $id,
        public string $url,
        public ?string $paymentIntentId = null,
    ) {}
}
