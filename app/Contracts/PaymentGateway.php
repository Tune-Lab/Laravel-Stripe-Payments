<?php



namespace App\Contracts;

use App\DataTransferObjects\CheckoutSession;
use App\DataTransferObjects\CheckoutSessionData;
use App\Exceptions\Billing\PaymentGatewayException;

/**
 * The application talks to this interface, never to the Stripe SDK directly.
 *
 * Two payoffs: tests swap in an in-memory fake with no HTTP at all, and adding
 * a second provider (PayPal, YooKassa, Adyen) means one new class, not a rewrite.
 */
interface PaymentGateway
{
    /**
     * Creates a hosted checkout session and returns the URL to redirect the customer to.
     *
     * @throws PaymentGatewayException When the provider is unreachable or rejects the request.
     */
    public function createCheckoutSession(CheckoutSessionData $data): CheckoutSession;

    /**
     * @throws PaymentGatewayException
     */
    public function refund(string $paymentIntentId, ?int $amountMinorUnits = null): void;
}
