<?php



namespace App\Jobs;

use App\Actions\Billing\MarkPaymentAsPaidAction;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\StripeWebhookEvent;
use App\ValueObjects\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Webhook handling runs on the queue, not in the HTTP request.
 *
 * The endpoint's only job is to answer Stripe within a few seconds; anything
 * slower gets retried by the provider and eventually disables the endpoint.
 * Business logic that can be slow (mail, invoices, third-party calls) lives here,
 * where retries and backoff are free.
 */
final class ProcessStripeWebhookEvent implements ShouldQueue
{
    use Queueable;

    /** @var int Total attempts before the job is marked failed. */
    public int $tries = 5;

    /** @var list<int> Exponential backoff in seconds. */
    public array $backoff = [10, 30, 120, 600];

    public function __construct(private readonly string $webhookEventId) {}

    /** @return list<object> */
    public function middleware(): array
    {
        // Two events for the same payment must not be applied concurrently.
        return [new WithoutOverlapping($this->webhookEventId)];
    }

    public function handle(MarkPaymentAsPaidAction $markAsPaid): void
    {
        $event = StripeWebhookEvent::query()->findOrFail($this->webhookEventId);

        if ($event->processed_at !== null) {
            return;
        }

        $object = $event->payload['data']['object'] ?? [];

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($object, $markAsPaid),
            'checkout.session.expired' => $this->transition($object, PaymentStatus::Expired),
            'payment_intent.payment_failed' => $this->transition($object, PaymentStatus::Failed),
            'charge.refunded' => $this->transition($object, PaymentStatus::Refunded),
            // Unknown types are stored and acknowledged, never fatal:
            // Stripe adds event types without asking.
            default => Log::info('Unhandled Stripe event type.', ['type' => $event->type]),
        };

        $event->markProcessed();
    }

    public function failed(Throwable $exception): void
    {
        StripeWebhookEvent::query()
            ->find($this->webhookEventId)
            ?->markFailed($exception->getMessage());
    }

    /** @param  array<string, mixed>  $object */
    private function handleCheckoutCompleted(array $object, MarkPaymentAsPaidAction $markAsPaid): void
    {
        $paymentId = $object['metadata']['payment_id'] ?? $object['client_reference_id'] ?? null;

        if (! is_string($paymentId)) {
            Log::warning('Checkout session without a payment reference.', ['object' => $object]);

            return;
        }

        $markAsPaid->handle(
            paymentId: $paymentId,
            paidAmount: Money::fromMinorUnits(
                minorUnits: (int) ($object['amount_total'] ?? 0),
                currency: (string) ($object['currency'] ?? 'usd'),
            ),
            paymentIntentId: is_string($object['payment_intent'] ?? null) ? $object['payment_intent'] : null,
        );
    }

    /** @param  array<string, mixed>  $object */
    private function transition(array $object, PaymentStatus $status): void
    {
        $paymentId = $object['metadata']['payment_id'] ?? null;

        if (! is_string($paymentId)) {
            return;
        }

        Payment::query()->find($paymentId)?->transitionTo($status);
    }
}
