<?php

return [
    'public'       => env('PAYSTACK_PUBLIC_KEY'),
    'secret'       => env('PAYSTACK_SECRET_KEY'),
    'base_url'     => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    'callback_url' => env('PAYSTACK_CALLBACK_URL'), // e.g. https://icrawn-backend.onrender.com/api/public/paystack/verify
];
    