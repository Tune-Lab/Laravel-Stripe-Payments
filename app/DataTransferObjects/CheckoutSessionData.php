<?php



namespace App\DataTransferObjects;

use App\Models\Payment;
use App\ValueObjects\Money;

/**
 * Everything the gateway needs to open a checkout session — and nothing else.
 *
 * Passing a DTO instead of the HTTP request keeps the gateway free of any
 * knowledge about controllers, and makes the call site self-documenting.
 */
final readonly class CheckoutSessionData
{
    /**
     * @param  string  $idempotencyKey  Replaying the same key returns the original session
     *                                  instead of double-charging (network retries, double clicks).
     * @param  array<string, string>  $metadata  Echoed back on every related webhook.
     */
    public function __construct(
        public string $paymentId,
        public Money $amount,
        public string $description,
        public string $customerEmail,
        public string $successUrl,
        public string $cancelUrl,
        public string $idempotencyKey,
        public array $metadata = [],
    ) {}

    public static function forPayment(Payment $payment, string $successUrl, string $cancelUrl): self
    {
        return new self(
            paymentId: $payment->id,
            amount: $payment->amount,
            description: $payment->description,
            customerEmail: $payment->user->email,
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
            idempotencyKey: $payment->idempotency_key,
            metadata: ['payment_id' => $payment->id],
        );
    }
}
