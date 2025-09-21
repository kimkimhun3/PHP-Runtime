<?php

namespace Blog\Utils;

class Response
{
    /**
     * Send JSON response
     */
    public static function json(array $data, int $statusCode = 200, array $headers = []): void
    {
        http_response_code($statusCode);
        
        // Set default headers
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        // Set custom headers
        foreach ($headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Send success response
     */
    public static function success($data = null, string $message = 'Success', array $meta = [], int $statusCode = 200): void
    {
        $response = [
            'success' => true,
            'message' => $message
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        self::json($response, $statusCode);
    }

    /**
     * Send error response
     */
    public static function error(string $message = 'Error', $details = null, int $statusCode = 400, ?string $code = null): void
    {
        $response = [
            'success' => false,
            'error' => [
                'message' => $message
            ]
        ];

        if ($code) {
            $response['error']['code'] = $code;
        }

        if ($details !== null) {
            $response['error']['details'] = $details;
        }

        self::json($response, $statusCode);
    }

    /**
     * Send validation error response
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): void
    {
        self::error($message, $errors, 422, 'VALIDATION_ERROR');
    }

    /**
     * Send unauthorized response
     */
    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        self::error($message, null, 401, 'UNAUTHORIZED');
    }

    /**
     * Send forbidden response
     */
    public static function forbidden(string $message = 'Forbidden'): void
    {
        self::error($message, null, 403, 'FORBIDDEN');
    }

    /**
     * Send not found response
     */
    public static function notFound(string $message = 'Resource not found'): void
    {
        self::error($message, null, 404, 'NOT_FOUND');
    }

    /**
     * Send server error response
     */
    public static function serverError(string $message = 'Internal server error'): void
    {
        self::error($message, null, 500, 'SERVER_ERROR');
    }

    /**
     * Send created response
     */
    public static function created($data = null, string $message = 'Created successfully'): void
    {
        self::success($data, $message, [], 201);
    }

    /**
     * Send no content response
     */
    public static function noContent(): void
    {
        http_response_code(204);
        exit;
    }

    /**
     * Send paginated response
     */
    public static function paginated(array $data, int $page, int $limit, int $total, string $message = 'Success'): void
    {
        $meta = [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => (int)ceil($total / $limit),
            'has_next' => ($page * $limit) < $total,
            'has_prev' => $page > 1
        ];

        self::success($data, $message, $meta);
    }

    /**
     * Send CORS preflight response
     */
    public static function corsOptions(): void
    {
        http_response_code(200);
        
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400'); // 24 hours
        
        exit;
    }

    /**
     * Set CORS headers
     */
    public static function setCorsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    }

    /**
     * Get HTTP status text
     */
    public static function getStatusText(int $statusCode): string
    {
        $statusTexts = [
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error'
        ];

        return $statusTexts[$statusCode] ?? 'Unknown Status';
    }
}