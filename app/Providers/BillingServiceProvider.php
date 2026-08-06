<?php



namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\Stripe\StripeEventVerifier;
use App\Services\Stripe\StripeGateway;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

final class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StripeClient::class, static fn (): StripeClient => new StripeClient([
            'api_key' => config('billing.stripe.secret'),
            'stripe_version' => config('billing.stripe.api_version'),
        ]));

        // Binding the interface — not the concrete class — is what makes the
        // gateway swappable in tests and replaceable in production.
        $this->app->bind(PaymentGateway::class, StripeGateway::class);

        $this->app->singleton(StripeEventVerifier::class, static fn (): StripeEventVerifier => new StripeEventVerifier(
            signingSecret: (string) config('billing.stripe.webhook_secret'),
            toleranceSeconds: (int) config('billing.stripe.webhook_tolerance'),
        ));
    }
}
