<?php



use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Tests\Support\FakePaymentGateway;

beforeEach(function (): void {
    $this->gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $this->gateway);
});

it('creates a pending payment and returns a checkout url', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('billing.checkout'), [
        'amount_minor_units' => 1999,
        'currency' => 'USD',
        'description' => 'Pro plan — one month',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.checkout_url', 'https://checkout.stripe.test/session');

    $payment = Payment::query()->sole();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->amount->minorUnits)->toBe(1999)
        ->and($payment->amount->currency)->toBe('USD')
        ->and($payment->provider_session_id)->not->toBeNull();
});

it('passes the payment id to the gateway so webhooks can be matched back', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('billing.checkout'), [
        'amount_minor_units' => 5000,
        'currency' => 'EUR',
        'description' => 'Consulting hour',
    ])->assertCreated();

    $payment = Payment::query()->sole();

    expect($this->gateway->lastSession()->metadata)->toBe(['payment_id' => $payment->id]);
});

it('returns 503 without creating a session when the provider is down', function (): void {
    $this->gateway->shouldFail = true;

    $this->actingAs(User::factory()->create())
        ->postJson(route('billing.checkout'), [
            'amount_minor_units' => 1000,
            'currency' => 'USD',
            'description' => 'Anything',
        ])
        ->assertServiceUnavailable();
});

it('rejects unsupported currencies', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson(route('billing.checkout'), [
            'amount_minor_units' => 1000,
            'currency' => 'XYZ',
            'description' => 'Anything',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('currency');
});

it('requires authentication', function (): void {
    $this->postJson(route('billing.checkout'), [
        'amount_minor_units' => 1000,
        'currency' => 'USD',
        'description' => 'Anything',
    ])->assertUnauthorized();
});
