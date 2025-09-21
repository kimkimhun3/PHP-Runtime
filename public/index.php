<?php

declare(strict_types=1);

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Start output buffering
ob_start();

// Set default timezone
date_default_timezone_set('UTC');

try {
    // Load autoloader
    require_once __DIR__ . '/../vendor/autoload.php';

    // Load environment variables
    if (file_exists(__DIR__ . '/../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    }

    // Initialize configuration
    Blog\Config\Config::init();

    // Set timezone from config
    date_default_timezone_set(Blog\Config\Config::get('app.timezone'));

    // Set CORS headers
    Blog\Utils\Response::setCorsHeaders();

    // Create router instance
    $router = new Blog\Utils\Router();

    // Load routes
    require_once __DIR__ . '/../src/Config/Routes.php';

    // Dispatch request
    $router->dispatch();

} catch (\Dotenv\Exception\InvalidPathException $e) {
    // .env file not found - continue without it for now
    error_log("Environment file not found: " . $e->getMessage());
    
    // Initialize basic config without .env
    Blog\Config\Config::init();
    
    // Create router and dispatch
    $router = new Blog\Utils\Router();
    require_once __DIR__ . '/../src/Config/Routes.php';
    $router->dispatch();

} catch (\Exception $e) {
    // Log error
    error_log("Application error: " . $e->getMessage());

    // Send error response
    if (Blog\Config\Config::isDebug()) {
        Blog\Utils\Response::serverError($e->getMessage() . "\n\nTrace:\n" . $e->getTraceAsString());
    } else {
        Blog\Utils\Response::serverError('Internal server error');
    }

} finally {
    // Clean output buffer
    if (ob_get_level()) {
        ob_end_flush();
    }
}