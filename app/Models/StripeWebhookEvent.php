<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Append-only log of every accepted provider event.
 *
 * Serves three jobs at once:
 *  1. de-duplication (unique index on `stripe_event_id`),
 *  2. an audit trail for finance and support,
 *  3. a replay source — a failed handler can be re-run from the stored payload.
 *
 * @property string $id
 * @property string $stripe_event_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property ?Carbon $processed_at
 * @property ?string $failure_reason
 */
final class StripeWebhookEvent extends Model
{
    use HasUuids;

    protected $fillable = [
        'stripe_event_id',
        'type',
        'payload',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function markProcessed(): void
    {
        $this->forceFill([
            'processed_at' => now()->toImmutable(),
            'failure_reason' => null,
        ])->save();
    }

    public function markFailed(string $reason): void
    {
        // Truncated: provider exception messages can contain very long payload dumps.
        $this->forceFill(['failure_reason' => mb_substr($reason, 0, 1000)])->save();
    }
}
