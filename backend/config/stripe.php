<?php

return [
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'frontend_url' => env('STRIPE_FRONTEND_URL', 'http://localhost:5173'),
];
