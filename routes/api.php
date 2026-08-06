<?php


use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(static function (): void {
    Route::post('/checkout-sessions', CheckoutController::class)
        ->middleware('throttle:10,1') // Payment endpoints are a favourite target for abuse.
        ->name('billing.checkout');
});

// Public by necessity — authenticated by signature, not by session.
Route::post('/webhooks/stripe', StripeWebhookController::class)
    ->withoutMiddleware(['throttle'])
    ->name('webhooks.stripe');
