<?php



namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Carries an id, not a model instance.
 *
 * Queued listeners deserialize the model fresh from the database, so they can
 * never act on a stale copy captured at dispatch time.
 */
final readonly class PaymentSucceeded
{
    use Dispatchable;

    public function __construct(public string $paymentId) {}
}
