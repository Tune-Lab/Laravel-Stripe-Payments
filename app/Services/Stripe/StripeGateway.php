<?php



namespace App\Services\Stripe;

use App\Contracts\PaymentGateway;
use App\DataTransferObjects\CheckoutSession;
use App\DataTransferObjects\CheckoutSessionData;
use App\Exceptions\Billing\PaymentGatewayException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * The only class in the application that imports the Stripe SDK.
 */
final readonly class StripeGateway implements PaymentGateway
{
    public function __construct(private StripeClient $stripe) {}

    public function createCheckoutSession(CheckoutSessionData $data): CheckoutSession
    {
        try {
            $session = $this->stripe->checkout->sessions->create(
                params: [
                    'mode' => 'payment',
                    'customer_email' => $data->customerEmail,
                    'client_reference_id' => $data->paymentId,
                    'success_url' => $data->successUrl,
                    'cancel_url' => $data->cancelUrl,
                    'metadata' => $data->metadata,
                    'payment_intent_data' => ['metadata' => $data->metadata],
                    'line_items' => [[
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => strtolower($data->amount->currency),
                            'unit_amount' => $data->amount->minorUnits,
                            'product_data' => ['name' => $data->description],
                        ],
                    ]],
                ],
                // Stripe returns the original session for a repeated key within 24h.
                opts: ['idempotency_key' => $data->idempotencyKey],
            );
        } catch (ApiErrorException $e) {
            throw PaymentGatewayException::from($e, 'checkout.sessions.create');
        }

        return new CheckoutSession(
            id: $session->id,
            url: (string) $session->url,
            paymentIntentId: is_string($session->payment_intent) ? $session->payment_intent : null,
        );
    }

    public function refund(string $paymentIntentId, ?int $amountMinorUnits = null): void
    {
        try {
            $this->stripe->refunds->create(array_filter([
                'payment_intent' => $paymentIntentId,
                'amount' => $amountMinorUnits,
            ], static fn (mixed $value): bool => $value !== null));
        } catch (ApiErrorException $e) {
            throw PaymentGatewayException::from($e, 'refunds.create');
        }
    }
}
