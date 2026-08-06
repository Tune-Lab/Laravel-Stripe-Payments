<?php



namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Events\PaymentSucceeded;
use App\Models\Payment;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Applies a confirmed `checkout.session.completed` event to the local payment.
 *
 * Two safeguards that production billing code cannot go without:
 *  - a row lock, because Stripe may deliver the same event to two workers at once;
 *  - an amount check, because the source of truth for what was actually charged
 *    is the provider, not our own pending row.
 */
final readonly class MarkPaymentAsPaidAction
{
    public function handle(string $paymentId, Money $paidAmount, ?string $paymentIntentId): void
    {
        DB::transaction(static function () use ($paymentId, $paidAmount, $paymentIntentId): void {
            /** @var Payment $payment */
            $payment = Payment::query()
                ->whereKey($paymentId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->status === PaymentStatus::Paid) {
                return; // Duplicate delivery — already handled.
            }

            if (! $payment->amount->equals($paidAmount)) {
                // Never silently accept a mismatch: it means tampering or a pricing bug.
                Log::critical('Paid amount does not match the expected amount.', [
                    'payment_id' => $payment->id,
                    'expected' => (string) $payment->amount,
                    'received' => (string) $paidAmount,
                ]);

                throw new RuntimeException("Amount mismatch for payment [{$payment->id}].");
            }

            if ($paymentIntentId !== null) {
                $payment->forceFill(['provider_payment_intent_id' => $paymentIntentId]);
            }

            $payment->transitionTo(PaymentStatus::Paid);

            // Fired after the commit so listeners never see a row that later rolls back.
            PaymentSucceeded::dispatch($payment->id);
        });
    }
}
