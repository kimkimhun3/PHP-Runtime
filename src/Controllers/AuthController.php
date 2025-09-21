<?php

namespace Blog\Controllers;

use Blog\Models\User;
use Blog\Utils\Response;
use Blog\Config\Config;
use Blog\Middleware\AuthMiddleware;
use Firebase\JWT\JWT;

class AuthController
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Login user
     * POST /api/auth/login
     */
    public function login(): void
    {
        try {
            $input = $this->getJsonInput();
            $errors = $this->validateLoginData($input);

            if (!empty($errors)) {
                Response::validationError($errors);
                return;
            }

            $user = $this->userModel->verifyCredentials($input['email'], $input['password']);

            if (!$user) {
                Response::unauthorized('Invalid email or password');
                return;
            }

            // Generate JWT token
            $token = $this->generateToken($user);

            Response::success([
                'user' => $user,
                'token' => $token,
                'expires_in' => Config::get('jwt.expiry')
            ], 'Login successful');

        } catch (\Exception $e) {
            error_log("AuthController::login error: " . $e->getMessage());
            Response::serverError('Login failed');
        }
    }

    /**
     * Logout user
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        // For JWT, logout is typically handled on the client side
        // by removing the token. We can add token blacklisting later if needed.
        
        unset($_SESSION['user']);
        Response::success(null, 'Logout successful');
    }

    /**
     * Verify token and get current user
     * GET /api/auth/verify
     */
    public function verify(): void
    {
        try {
            $user = AuthMiddleware::getAuthenticatedUser();
            
            if (!$user) {
                Response::unauthorized('No authenticated user');
                return;
            }

            // Get fresh user data from database
            $freshUser = $this->userModel->find($user['user_id']);
            
            if (!$freshUser || !$freshUser['is_active']) {
                Response::unauthorized('User account is inactive');
                return;
            }

            $freshUser = $this->userModel->hideFields($freshUser);

            Response::success([
                'user' => $freshUser,
                'token_valid' => true
            ], 'Token is valid');

        } catch (\Exception $e) {
            error_log("AuthController::verify error: " . $e->getMessage());
            Response::unauthorized('Token verification failed');
        }
    }

    /**
     * Refresh JWT token
     * POST /api/auth/refresh
     */
    public function refresh(): void
    {
        try {
            $user = AuthMiddleware::getAuthenticatedUser();
            
            if (!$user) {
                Response::unauthorized('No authenticated user');
                return;
            }

            // Get fresh user data
            $freshUser = $this->userModel->find($user['user_id']);
            
            if (!$freshUser || !$freshUser['is_active']) {
                Response::unauthorized('User account is inactive');
                return;
            }

            $freshUser = $this->userModel->hideFields($freshUser);

            // Generate new token
            $newToken = $this->generateToken($freshUser);

            Response::success([
                'user' => $freshUser,
                'token' => $newToken,
                'expires_in' => Config::get('jwt.expiry')
            ], 'Token refreshed successfully');

        } catch (\Exception $e) {
            error_log("AuthController::refresh error: " . $e->getMessage());
            Response::serverError('Token refresh failed');
        }
    }

    /**
     * Generate JWT token for user
     */
    private function generateToken(array $user): string
    {
        $now = time();
        $expiry = $now + Config::get('jwt.expiry');

        $payload = [
            'iss' => Config::get('jwt.issuer'), // Issuer
            'iat' => $now, // Issued at
            'exp' => $expiry, // Expiration
            'user_id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role']
        ];

        return JWT::encode($payload, Config::get('jwt.secret'), Config::get('jwt.algorithm'));
    }

    /**
     * Get JSON input from request body
     */
    private function getJsonInput(): array
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Response::validationError(['json' => ['Invalid JSON format']]);
            exit;
        }

        return $data ?? [];
    }

    /**
     * Validate login data
     */
    private function validateLoginData(array $data): array
    {
        $errors = [];

        if (empty($data['email'])) {
            $errors['email'] = ['Email is required'];
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['Invalid email format'];
        }

        if (empty($data['password'])) {
            $errors['password'] = ['Password is required'];
        }

        return $errors;
    }
}