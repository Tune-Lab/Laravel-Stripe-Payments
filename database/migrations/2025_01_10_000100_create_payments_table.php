<?php



use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            $table->string('status', 20)->index();

            // Money as an integer + ISO code. Never DECIMAL, never FLOAT.
            $table->unsignedBigInteger('amount_minor_units');
            $table->char('currency', 3);

            $table->string('description');

            // Guarantees one provider session per intent, even if the client
            // fires the request twice.
            $table->uuid('idempotency_key')->unique();

            $table->string('provider_session_id')->nullable()->unique();
            $table->string('provider_payment_intent_id')->nullable()->index();

            $table->json('metadata');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            // Supports the most common query: "this customer's recent payments".
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
