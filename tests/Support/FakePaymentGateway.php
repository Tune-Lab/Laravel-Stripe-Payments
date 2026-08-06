<?php



namespace Tests\Support;

use App\Contracts\PaymentGateway;
use App\DataTransferObjects\CheckoutSession;
use App\DataTransferObjects\CheckoutSessionData;
use App\Exceptions\Billing\PaymentGatewayException;
use RuntimeException;

/**
 * In-memory gateway for tests: no HTTP, no API keys, no flakiness.
 *
 * It also records what it was asked to do, so tests can assert on the
 * request the application *would* have sent to Stripe.
 */
final class FakePaymentGateway implements PaymentGateway
{
    /** @var list<CheckoutSessionData> */
    public array $createdSessions = [];

    public bool $shouldFail = false;

    public function createCheckoutSession(CheckoutSessionData $data): CheckoutSession
    {
        if ($this->shouldFail) {
            throw PaymentGatewayException::from(new RuntimeException('Simulated outage'), 'checkout.sessions.create');
        }

        $this->createdSessions[] = $data;

        return new CheckoutSession(
            id: 'cs_test_'.substr(md5($data->idempotencyKey), 0, 24),
            url: 'https://checkout.stripe.test/session',
            paymentIntentId: 'pi_test_'.substr(md5($data->paymentId), 0, 24),
        );
    }

    public function refund(string $paymentIntentId, ?int $amountMinorUnits = null): void
    {
        // No-op for tests.
    }

    public function lastSession(): CheckoutSessionData
    {
        return $this->createdSessions[array_key_last($this->createdSessions)];
    }
}
