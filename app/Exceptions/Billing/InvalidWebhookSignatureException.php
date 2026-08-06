<?php



namespace App\Exceptions\Billing;

use RuntimeException;
use Throwable;

final class InvalidWebhookSignatureException extends RuntimeException
{
    public static function because(Throwable $previous): self
    {
        return new self('Webhook signature verification failed.', previous: $previous);
    }
}
