<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI defaults (non-secret). Override per-user via `settings` table.
    | API keys live in .env (OPENAI_API_KEY) — never commit them.
    |--------------------------------------------------------------------------
    */
    'ai' => [
        'provider' => env('WAREHOUSE_AI_PROVIDER', 'openai'),
        'model' => env('WAREHOUSE_AI_MODEL', 'gpt-4o-mini'),
        'enabled' => (bool) env('WAREHOUSE_AI_ENABLED', false),
        'vision_enabled' => (bool) env('WAREHOUSE_AI_VISION', true),
        'max_tokens' => (int) env('WAREHOUSE_AI_MAX_TOKENS', 2000),
    ],

    'app' => [
        'name' => env('WAREHOUSE_APP_NAME', 'Shaqlin Warehouse'),
        'default_currency' => env('WAREHOUSE_DEFAULT_CURRENCY', 'INR'),
        'timezone' => env('WAREHOUSE_TIMEZONE', 'Asia/Kolkata'),
        'public_profile_enabled' => (bool) env('WAREHOUSE_PUBLIC_PROFILE', true),
    ],

];
