<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => [
        'api/*',           // Your main API endpoints
        'sanctum/csrf-cookie', // If using Laravel Sanctum
        'storage/*'
    ],

    'allowed_methods' => ['*'], // Or specify: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']

    'allowed_origins' => [
        // Development - Vite default port
        // 'http://localhost:5173',
        // 'http://127.0.0.1:5173',

        // Other common development ports (if needed)
        // 'http://localhost:3000',    // React/Next.js
        // 'http://localhost:8080',    // Vue CLI
        // 'http://localhost:4200',    // Angular

        // Production - replace with your actual domains
        "https://kebijakan-spbe.vercel.app",
        // 'https://yourdomain.com',
        // 'https://www.yourdomain.com',
        // 'https://app.yourdomain.com',

        // For development only - remove in production
        "*"

    ],

    'allowed_origins_patterns' => [
        // You can use patterns like:
        // '/^https:\/\/.*\.yourdomain\.com$/',
    ],

    'allowed_headers' => [
        "*"
    ],

    'exposed_headers' => [
        // Headers that the client can access
        'Authorization',       // If you return auth tokens in responses
        // 'X-Total-Count',
        // 'X-Page-Count',
        'Content-Disposition'
    ],



    'max_age' => 86400, // Cache preflight for 24 hours (reduces OPTIONS requests)

    'supports_credentials' => false, // Keep false for Bearer tokens (true is for cookies)
];
