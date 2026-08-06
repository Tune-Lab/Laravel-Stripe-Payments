<?php



namespace App\Services\Stripe;

use App\Exceptions\Billing\InvalidWebhookSignatureException;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Verifies the `Stripe-Signature` header against the raw request body.
 *
 * Without this check the webhook endpoint is a public, unauthenticated way to
 * mark any order as paid. The timestamp tolerance additionally blocks replays
 * of a genuine, previously captured request.
 */
final readonly class StripeEventVerifier
{
    public function __construct(
        private string $signingSecret,
        private int $toleranceSeconds = 300,
    ) {}

    /**
     * @param  string  $payload  Raw body — never the parsed array: re-encoding changes the bytes
     *                           and the signature no longer matches.
     *
     * @throws InvalidWebhookSignatureException
     */
    public function verify(string $payload, string $signatureHeader): Event
    {
        try {
            return Webhook::constructEvent(
                $payload,
                $signatureHeader,
                $this->signingSecret,
                $this->toleranceSeconds,
            );
        } catch (SignatureVerificationException|UnexpectedValueException $e) {
            throw InvalidWebhookSignatureException::because($e);
        }
    }
}
