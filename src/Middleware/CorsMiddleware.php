<?php

namespace Blog\Middleware;

use Blog\Config\Config;

class CorsMiddleware
{
    public function handle(): void
    {
        $allowedOrigins = Config::get('cors.allowed_origins', ['*']);
        $allowedMethods = Config::get('cors.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS']);
        $allowedHeaders = Config::get('cors.allowed_headers', ['Content-Type', 'Authorization', 'X-Requested-With']);
        $maxAge = Config::get('cors.max_age', 86400);

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Set allowed origin
        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
        }

        // Set allowed methods
        header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));

        // Set allowed headers
        header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));

        // Set max age for preflight requests
        header('Access-Control-Max-Age: ' . $maxAge);

        // Allow credentials if needed
        header('Access-Control-Allow-Credentials: true');

        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}