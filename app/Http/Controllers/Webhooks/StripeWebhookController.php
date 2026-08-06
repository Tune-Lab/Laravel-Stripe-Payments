<?php



namespace App\Http\Controllers\Webhooks;

use App\Exceptions\Billing\InvalidWebhookSignatureException;
use App\Jobs\ProcessStripeWebhookEvent;
use App\Models\StripeWebhookEvent;
use App\Services\Stripe\StripeEventVerifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Receives Stripe events. Does four things and nothing more:
 *   verify signature -> store once -> queue -> answer 202.
 *
 * Route must be excluded from CSRF and from `auth` middleware,
 * and must keep the raw request body intact.
 */
final readonly class StripeWebhookController
{
    public function __construct(private StripeEventVerifier $verifier) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $event = $this->verifier->verify(
                payload: $request->getContent(),
                signatureHeader: $request->header('Stripe-Signature', ''),
            );
        } catch (InvalidWebhookSignatureException $e) {
            report($e);

            return new JsonResponse(['message' => 'Invalid signature.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $stored = StripeWebhookEvent::create([
                'stripe_event_id' => $event->id,
                'type' => $event->type,
                'payload' => $event->toArray(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // At-least-once delivery is normal, not an error. Acknowledge and stop.
            return new JsonResponse(['message' => 'Event already received.'], Response::HTTP_OK);
        }

        ProcessStripeWebhookEvent::dispatch($stored->id);

        return new JsonResponse(['message' => 'Accepted.'], Response::HTTP_ACCEPTED);
    }
}
