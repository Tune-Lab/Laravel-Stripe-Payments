<?php



return [

    /*
    |--------------------------------------------------------------------------
    | Supported currencies
    |--------------------------------------------------------------------------
    | Whitelist used by request validation. Keeping it here rather than in the
    | validation rule means sales can enable a market without touching code.
    */

    'supported_currencies' => ['USD', 'EUR', 'GBP'],

    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

        // Pinned on purpose: an unpinned version means Stripe can change
        // payload shapes under a running deployment.
        'api_version' => env('STRIPE_API_VERSION', '2024-06-20'),

        // Max age of a webhook signature, in seconds.
        'webhook_tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
    ],

];
