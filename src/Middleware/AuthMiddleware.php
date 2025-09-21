<?php

namespace Blog\Middleware;

use Blog\Utils\Response;
use Blog\Config\Config;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class AuthMiddleware
{
    public function handle(): void
    {
        try {
            $token = $this->extractToken();
            
            if (!$token) {
                Response::unauthorized('Authentication token required');
                return;
            }

            $payload = $this->validateToken($token);
            
            if (!$payload) {
                Response::unauthorized('Invalid or expired token');
                return;
            }

            // Store user info for use in controllers
            $_SESSION['user'] = $payload;
            
        } catch (ExpiredException $e) {
            Response::unauthorized('Token has expired');
        } catch (SignatureInvalidException $e) {
            Response::unauthorized('Invalid token signature');
        } catch (\Exception $e) {
            error_log("AuthMiddleware error: " . $e->getMessage());
            Response::unauthorized('Authentication failed');
        }
    }

    /**
     * Extract JWT token from request headers
     */
    private function extractToken(): ?string
    {
        $headers = $this->getRequestHeaders();
        
        // Check Authorization header first
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            
            // Bearer token format: "Bearer <token>"
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }

        // Check alternative header names
        if (isset($headers['HTTP_AUTHORIZATION'])) {
            $authHeader = $headers['HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                return $matches[1];
            }
        }

        // Check query parameter as fallback (not recommended for production)
        if (Config::isDevelopment() && isset($_GET['token'])) {
            return $_GET['token'];
        }

        return null;
    }

    /**
     * Validate JWT token
     */
    private function validateToken(string $token): ?array
    {
        try {
            $secret = Config::get('jwt.secret');
            $algorithm = Config::get('jwt.algorithm', 'HS256');
            
            if (!$secret) {
                throw new \Exception('JWT secret not configured');
            }

            // Decode the token
            $decoded = JWT::decode($token, new Key($secret, $algorithm));
            
            // Convert to array
            $payload = json_decode(json_encode($decoded), true);
            
            // Validate required fields
            if (!isset($payload['user_id']) || !isset($payload['email'])) {
                throw new \Exception('Invalid token payload');
            }

            // Check if token is expired (JWT library handles this, but double check)
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                throw new ExpiredException('Token has expired');
            }

            // Validate issuer if set
            $expectedIssuer = Config::get('jwt.issuer');
            if ($expectedIssuer && (!isset($payload['iss']) || $payload['iss'] !== $expectedIssuer)) {
                throw new \Exception('Invalid token issuer');
            }

            return $payload;
            
        } catch (ExpiredException $e) {
            throw $e;
        } catch (SignatureInvalidException $e) {
            throw $e;
        } catch (\Exception $e) {
            error_log("Token validation error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get all request headers (cross-platform)
     */
    private function getRequestHeaders(): array
    {
        $headers = [];
        
        // Try getallheaders() first (Apache)
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            
            // Normalize header names
            $normalizedHeaders = [];
            foreach ($headers as $name => $value) {
                $normalizedHeaders[ucwords(strtolower($name), '-')] = $value;
            }
            $headers = $normalizedHeaders;
        } else {
            // Fallback for Nginx and other servers
            foreach ($_SERVER as $name => $value) {
                if (substr($name, 0, 5) == 'HTTP_') {
                    $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                    $headers[$headerName] = $value;
                }
            }
        }

        // Add common header variations
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers['Authorization'] = $_SERVER['HTTP_AUTHORIZATION'];
        }
        
        return $headers;
    }

    /**
     * Get current authenticated user
     */
    public static function getAuthenticatedUser(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * Get current user ID
     */
    public static function getCurrentUserId(): ?int
    {
        $user = self::getAuthenticatedUser();
        return $user['user_id'] ?? null;
    }

    /**
     * Get current user email
     */
    public static function getCurrentUserEmail(): ?string
    {
        $user = self::getAuthenticatedUser();
        return $user['email'] ?? null;
    }

    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated(): bool
    {
        return isset($_SESSION['user']);
    }
}