<?php



namespace App\Exceptions\Billing;

use App\Enums\PaymentStatus;
use DomainException;

final class InvalidPaymentTransitionException extends DomainException
{
    public static function between(PaymentStatus $from, PaymentStatus $to, string $paymentId): self
    {
        return new self(
            "Payment [{$paymentId}] cannot move from [{$from->value}] to [{$to->value}].",
        );
    }
}
