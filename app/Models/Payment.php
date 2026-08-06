<?php



namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PaymentStatus;
use App\Exceptions\Billing\InvalidPaymentTransitionException;
use App\ValueObjects\Money;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single payment attempt. The row is created *before* the customer is sent
 * to the provider, so an inbound webhook always has a local record to attach to.
 *
 * @property string $id
 * @property PaymentStatus $status
 * @property Money $amount
 * @property string $idempotency_key
 * @property ?string $provider_session_id
 * @property ?string $provider_payment_intent_id
 * @property array<string, mixed> $metadata
 * @property ?\Illuminate\Support\Carbon $paid_at
 */
final class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'user_id',
        'status',
        'amount',
        'description',
        'idempotency_key',
        'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => MoneyCast::class,
            'metadata' => 'array',
            'paid_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Moves the payment to a new state, refusing illegal transitions.
     *
     * Webhooks arrive out of order and more than once; guarding the transition
     * here means a late `payment_failed` cannot overwrite an already paid order.
     *
     * @throws InvalidPaymentTransitionException
     */
    public function transitionTo(PaymentStatus $status): void
    {
        if ($this->status === $status) {
            return; // Idempotent: replaying the same event is a no-op.
        }

        if (! $this->status->canTransitionTo($status)) {
            throw InvalidPaymentTransitionException::between($this->status, $status, $this->id);
        }

        $this->status = $status;

        if ($status === PaymentStatus::Paid) {
            $this->paid_at = now()->toImmutable();
        }

        $this->save();
    }
}
