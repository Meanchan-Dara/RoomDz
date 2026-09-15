<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bakong Open API & KHQR Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration options for National Bank of Cambodia (NBC) Bakong Open API
    | and Dynamic KHQR payment generation.
    |
    */

    'api_key' => env('BAKONG_API_KEY', 'cf995da6b1e04dd89f32'),
    'api_token' => env('BAKONG_API_TOKEN', env('BAKONG_API_KEY', 'cf995da6b1e04dd89f32')),
    'base_url' => env('BAKONG_API_BASE_URL', 'https://api-bakong.nbc.gov.kh/v1'),

    /*
    |--------------------------------------------------------------------------
    | Merchant Information
    |--------------------------------------------------------------------------
    |
    | Default receiving Bakong Account ID, merchant name, and city.
    |
    */
    'account_id' => env('BAKONG_ACCOUNT_ID', 'roomdz@nbc'),
    'merchant_name' => env('BAKONG_MERCHANT_NAME', 'RoomDz'),
    'merchant_city' => env('BAKONG_MERCHANT_CITY', 'Phnom Penh'),
    'default_currency' => env('BAKONG_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | QR Expiration
    |--------------------------------------------------------------------------
    |
    | Default expiration time for dynamic KHQR codes (in minutes).
    |
    */
    'qr_expiry_minutes' => env('BAKONG_QR_EXPIRY_MINUTES', 30),
];
