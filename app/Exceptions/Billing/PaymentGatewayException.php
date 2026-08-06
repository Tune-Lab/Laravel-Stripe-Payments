<?php



namespace App\Exceptions\Billing;

use RuntimeException;
use Throwable;

/**
 * Wraps every provider-level failure so the rest of the codebase never has to
 * catch `\Stripe\Exception\*` — swapping providers doesn't ripple through catch blocks.
 */
final class PaymentGatewayException extends RuntimeException
{
    public static function from(Throwable $previous, string $operation): self
    {
        return new self(
            message: "Payment gateway failed during [{$operation}]: {$previous->getMessage()}",
            previous: $previous,
        );
    }
}
