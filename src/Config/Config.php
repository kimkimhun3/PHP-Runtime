<?php

namespace Blog\Config;

class Config
{
    private static $config = [];

    /**
     * Initialize configuration
     */
    public static function init(): void
    {
        self::$config = [
            // Application settings
            'app' => [
                'name' => $_ENV['APP_NAME'] ?? 'Personal Blog',
                'url' => $_ENV['APP_URL'] ?? 'http://localhost:8000',
                'env' => $_ENV['APP_ENV'] ?? 'development',
                'debug' => filter_var($_ENV['APP_DEBUG'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC'
            ],

            // Database settings
            'database' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => $_ENV['DB_PORT'] ?? '5432',
                'name' => $_ENV['DB_NAME'] ?? 'blog_db',
                'user' => $_ENV['DB_USER'] ?? 'blog_user',
                'password' => $_ENV['DB_PASSWORD'] ?? ''
            ],

            // JWT settings
            'jwt' => [
                'secret' => $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key',
                'expiry' => (int)($_ENV['JWT_EXPIRY'] ?? 86400), // 24 hours
                'algorithm' => 'HS256',
                'issuer' => $_ENV['APP_URL'] ?? 'http://localhost:8000'
            ],

            // File upload settings
            'upload' => [
                'max_size' => (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 5242880), // 5MB
                'allowed_types' => explode(',', $_ENV['UPLOAD_ALLOWED_TYPES'] ?? 'jpg,jpeg,png,gif,webp'),
                'path' => $_ENV['UPLOAD_PATH'] ?? 'uploads/',
                'url' => $_ENV['UPLOAD_URL'] ?? '/uploads/'
            ],

            // Pagination settings
            'pagination' => [
                'default_limit' => 10,
                'max_limit' => 100
            ],

            // API settings
            'api' => [
                'rate_limit' => (int)($_ENV['API_RATE_LIMIT'] ?? 1000), // requests per hour
                'version' => 'v1'
            ],

            // Security settings
            'security' => [
                'password_min_length' => 8,
                'max_login_attempts' => 5,
                'lockout_duration' => 900, // 15 minutes
            ],

            // CORS settings
            'cors' => [
                'allowed_origins' => explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*'),
                'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
                'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
                'max_age' => 86400 // 24 hours
            ]
        ];

        // Set timezone
        date_default_timezone_set(self::get('app.timezone'));
    }

    /**
     * Get configuration value using dot notation
     */
    public static function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Set configuration value using dot notation
     */
    public static function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * Check if configuration key exists
     */
    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    /**
     * Get all configuration
     */
    public static function all(): array
    {
        return self::$config;
    }

    /**
     * Get environment
     */
    public static function env(): string
    {
        return self::get('app.env');
    }

    /**
     * Check if in debug mode
     */
    public static function isDebug(): bool
    {
        return self::get('app.debug');
    }

    /**
     * Check if in production
     */
    public static function isProduction(): bool
    {
        return self::get('app.env') === 'production';
    }

    /**
     * Check if in development
     */
    public static function isDevelopment(): bool
    {
        return self::get('app.env') === 'development';
    }
}