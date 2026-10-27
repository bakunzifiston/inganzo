<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment providers
    |--------------------------------------------------------------------------
    |
    | Each provider implements App\Services\Payments\PaymentGateway. A provider is only
    | offered at checkout when it is enabled here, configured, and supports the order's
    | country and currency. See docs/PAYMENTS.md for onboarding steps.
    |
    */

    'providers' => [

        'mtn_momo' => [
            'enabled' => (bool) env('MTN_MOMO_ENABLED', false),
            'base_url' => env('MTN_MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
            'target_environment' => env('MTN_MOMO_TARGET_ENVIRONMENT', 'sandbox'),
            'subscription_key' => env('MTN_MOMO_COLLECTION_SUBSCRIPTION_KEY'),
            'api_user' => env('MTN_MOMO_API_USER'),
            'api_key' => env('MTN_MOMO_API_KEY'),
            'callback_host' => env('MTN_MOMO_CALLBACK_HOST'),
            'countries' => ['RW'],
            'currencies' => ['RWF'],
            // The MTN sandbox only accepts EUR. Never enable this in production.
            'sandbox_currency_override' => env('MTN_MOMO_SANDBOX_CURRENCY'),
            'timeout_minutes' => 15,
        ],

        'airtel_money' => [
            'enabled' => (bool) env('AIRTEL_MONEY_ENABLED', false),
            'base_url' => env('AIRTEL_MONEY_BASE_URL', 'https://openapiuat.airtel.africa'),
            'client_id' => env('AIRTEL_MONEY_CLIENT_ID'),
            'client_secret' => env('AIRTEL_MONEY_CLIENT_SECRET'),
            'callback_secret' => env('AIRTEL_MONEY_CALLBACK_SECRET'),
            'countries' => ['RW'],
            'currencies' => ['RWF'],
            'timeout_minutes' => 15,
        ],

        'cash_on_delivery' => [
            // Also requires the "checkout.cod_enabled" platform setting and the country flag.
            'enabled' => (bool) env('COD_ENABLED', true),
            'countries' => ['RW'],
            'currencies' => ['RWF'],
        ],

        'simulator' => [
            // Development-only. Hard-disabled when APP_ENV=production regardless of this flag.
            'enabled' => (bool) env('PAYMENT_SIMULATOR_ENABLED', false),
            'secret' => env('PAYMENT_SIMULATOR_SECRET', 'local-simulator-secret'),
            'countries' => ['RW', 'UG', 'KE', 'TZ'],
            'currencies' => ['RWF', 'UGX', 'KES', 'TZS'],
        ],

    ],

    'checkout_order' => ['mtn_momo', 'airtel_money', 'cash_on_delivery', 'simulator'],

];
