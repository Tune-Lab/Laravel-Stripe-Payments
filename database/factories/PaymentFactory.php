<?php



namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => PaymentStatus::Pending,
            'amount' => Money::fromMinorUnits($this->faker->numberBetween(500, 50_000), 'USD'),
            'description' => $this->faker->sentence(3),
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => [],
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (): array => ['status' => PaymentStatus::Pending]);
    }

    public function paid(): self
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
