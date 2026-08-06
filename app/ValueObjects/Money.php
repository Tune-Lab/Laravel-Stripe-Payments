<?php



namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * Immutable money value object.
 *
 * Amounts are always stored as integers in the currency's minor unit
 * (cents, kopecks, pence). Floats are never used for money: 0.1 + 0.2 !== 0.3
 * in IEEE-754, and payment providers reject mismatched totals.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    /** ISO 4217 currencies without a minor unit. */
    private const ZERO_DECIMAL_CURRENCIES = ['JPY', 'KRW', 'VND', 'CLP', 'ISK'];

    /**
     * @param  int  $minorUnits  Amount in the smallest currency unit (e.g. 1050 = 10.50 USD)
     * @param  string  $currency  Uppercase ISO 4217 code
     */
    private function __construct(
        public int $minorUnits,
        public string $currency,
    ) {}

    public static function fromMinorUnits(int $minorUnits, string $currency): self
    {
        $currency = strtoupper($currency);

        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException("Currency must be an ISO 4217 code, got [{$currency}].");
        }

        if ($minorUnits < 0) {
            throw new InvalidArgumentException('Money cannot be negative. Use a credit note instead.');
        }

        return new self($minorUnits, $currency);
    }

    /**
     * Build from a human-readable decimal string ("10.50").
     * A string — not a float — is accepted on purpose: the caller keeps full precision.
     */
    public static function fromDecimalString(string $amount, string $currency): self
    {
        if (preg_match('/^\d+(\.\d{1,2})?$/', $amount) !== 1) {
            throw new InvalidArgumentException("Amount must look like \"10\" or \"10.50\", got [{$amount}].");
        }

        $factor = 10 ** self::fractionDigits($currency);

        return self::fromMinorUnits((int) round(((float) $amount) * $factor), $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minorUnits + $other->minorUnits, $this->currency);
    }

    public function multiply(int $quantity): self
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        return new self($this->minorUnits * $quantity, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->minorUnits === $other->minorUnits
            && $this->currency === $other->currency;
    }

    public function isZero(): bool
    {
        return $this->minorUnits === 0;
    }

    /** Decimal representation for UI and invoices: "1050" -> "10.50". */
    public function toDecimalString(): string
    {
        return number_format(
            $this->minorUnits / (10 ** self::fractionDigits($this->currency)),
            self::fractionDigits($this->currency),
            '.',
            '',
        );
    }

    public function __toString(): string
    {
        return "{$this->toDecimalString()} {$this->currency}";
    }

    /** @return array{amount: string, minor_units: int, currency: string} */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->toDecimalString(),
            'minor_units' => $this->minorUnits,
            'currency' => $this->currency,
        ];
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot operate on {$this->currency} and {$other->currency} without an exchange rate.",
            );
        }
    }

    private static function fractionDigits(string $currency): int
    {
        return in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true) ? 0 : 2;
    }
}
