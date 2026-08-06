<?php



namespace App\Casts;

use App\ValueObjects\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Maps a pair of columns (`{key}_minor_units`, `currency`) to a Money object.
 *
 * Usage:  protected function casts(): array { return ['amount' => MoneyCast::class]; }
 *
 * @implements CastsAttributes<Money, Money>
 */
final class MoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        $minorUnits = $attributes["{$key}_minor_units"] ?? null;
        $currency = $attributes['currency'] ?? null;

        if ($minorUnits === null || $currency === null) {
            return null;
        }

        return Money::fromMinorUnits((int) $minorUnits, (string) $currency);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return ["{$key}_minor_units" => null, 'currency' => null];
        }

        if (! $value instanceof Money) {
            throw new InvalidArgumentException(
                sprintf('Attribute [%s] expects %s, got %s.', $key, Money::class, get_debug_type($value)),
            );
        }

        return [
            "{$key}_minor_units" => $value->minorUnits,
            'currency' => $value->currency,
        ];
    }
}
