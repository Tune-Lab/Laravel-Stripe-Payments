<?php



namespace App\Actions\Billing;

use App\Contracts\PaymentGateway;
use App\DataTransferObjects\CheckoutSession;
use App\DataTransferObjects\CheckoutSessionData;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single-purpose use case: turn an intent to pay into a redirect URL.
 *
 * The local Payment row is committed *before* the provider call, so that a
 * webhook that arrives while we are still reading the HTTP response
 * (it happens — Stripe is fast) already finds a record to update.
 */
final readonly class CreateCheckoutSessionAction
{
    public function __construct(private PaymentGateway $gateway) {}

    public function handle(User $user, Money $amount, string $description): CheckoutSession
    {
        $payment = DB::transaction(static fn (): Payment => Payment::create([
            'user_id' => $user->id,
            'status' => PaymentStatus::Pending,
            'amount' => $amount,
            'description' => $description,
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => [],
        ]));

        $session = $this->gateway->createCheckoutSession(
            CheckoutSessionData::forPayment(
                payment: $payment->setRelation('user', $user),
                successUrl: route('billing.success', ['payment' => $payment->id]),
                cancelUrl: route('billing.cancel', ['payment' => $payment->id]),
            ),
        );

        $payment->forceFill([
            'provider_session_id' => $session->id,
            'provider_payment_intent_id' => $session->paymentIntentId,
        ])->save();

        return $session;
    }
}
