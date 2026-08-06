<?php

use App\Enums\PaymentStatus;
use App\Jobs\ProcessStripeWebhookEvent;
use App\Models\Payment;
use App\Models\StripeWebhookEvent;
use App\ValueObjects\Money;
use Illuminate\Support\Facades\Queue;

/** Signs a payload exactly the way Stripe does, so the real verifier is exercised. */
function stripeSignature(string $payload, ?int $timestamp = null): string
{
    $timestamp ??= time();
    $secret = (string) config('billing.stripe.webhook_secret');
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    return "t={$timestamp},v1={$signature}";
}

function checkoutCompletedPayload(string $paymentId, int $amount = 1999, string $eventId = 'evt_test_1'): string
{
    return json_encode([
        'id' => $eventId,
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'data' => ['object' => [
            'id' => 'cs_test_1',
            'object' => 'checkout.session',
            'amount_total' => $amount,
            'currency' => 'usd',
            'payment_intent' => 'pi_test_1',
            'metadata' => ['payment_id' => $paymentId],
        ]],
    ], JSON_THROW_ON_ERROR);
}

it('rejects a payload with an invalid signature', function (): void {
    Queue::fake();

    $this->call('POST', route('webhooks.stripe'), server: [
        'HTTP_STRIPE_SIGNATURE' => 't=1,v1=deadbeef',
        'CONTENT_TYPE' => 'application/json',
    ], content: checkoutCompletedPayload('any'))
        ->assertBadRequest();

    expect(StripeWebhookEvent::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('rejects a replayed request older than the tolerance window', function (): void {
    $payload = checkoutCompletedPayload('any');

    $this->call('POST', route('webhooks.stripe'), server: [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($payload, timestamp: time() - 3600),
        'CONTENT_TYPE' => 'application/json',
    ], content: $payload)
        ->assertBadRequest();
});

it('stores the event once and queues it', function (): void {
    Queue::fake();

    $payment = Payment::factory()->pending()->create();
    $payload = checkoutCompletedPayload($payment->id);

    $send = fn (): \Illuminate\Testing\TestResponse => $this->call('POST', route('webhooks.stripe'), server: [
        'HTTP_STRIPE_SIGNATURE' => stripeSignature($payload),
        'CONTENT_TYPE' => 'application/json',
    ], content: $payload);

    $send()->assertAccepted();
    $send()->assertOk(); // Duplicate delivery is acknowledged, not processed again.

    expect(StripeWebhookEvent::query()->count())->toBe(1);
    Queue::assertPushed(ProcessStripeWebhookEvent::class, 1);
});

it('marks the payment as paid when the job runs', function (): void {
    $payment = Payment::factory()->pending()->create([
        'amount' => Money::fromMinorUnits(1999, 'USD'),
    ]);

    $event = StripeWebhookEvent::create([
        'stripe_event_id' => 'evt_test_ok',
        'type' => 'checkout.session.completed',
        'payload' => json_decode(checkoutCompletedPayload($payment->id), true, 512, JSON_THROW_ON_ERROR),
    ]);

    app()->call([new ProcessStripeWebhookEvent($event->id), 'handle']);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($event->refresh()->processed_at)->not->toBeNull();
});

it('refuses to mark a payment paid for a different amount', function (): void {
    $payment = Payment::factory()->pending()->create([
        'amount' => Money::fromMinorUnits(1999, 'USD'),
    ]);

    $event = StripeWebhookEvent::create([
        'stripe_event_id' => 'evt_test_mismatch',
        'type' => 'checkout.session.completed',
        'payload' => json_decode(checkoutCompletedPayload($payment->id, amount: 1), true, 512, JSON_THROW_ON_ERROR),
    ]);

    expect(fn () => app()->call([new ProcessStripeWebhookEvent($event->id), 'handle']))
        ->toThrow(RuntimeException::class);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});
