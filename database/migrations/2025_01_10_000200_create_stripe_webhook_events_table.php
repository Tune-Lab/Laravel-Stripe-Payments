<?php



use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // The de-duplication guarantee. Enforced by the database, because
            // an application-level check loses the race between two workers.
            $table->string('stripe_event_id')->unique();

            $table->string('type')->index();
            $table->json('payload');
            $table->timestamp('processed_at')->nullable()->index();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
