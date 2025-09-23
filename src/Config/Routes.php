<?php

/**
 * API Routes Configuration
 */

use Blog\Controllers\PostController;
use Blog\Controllers\AuthController;
use Blog\Controllers\ImageController;
use Blog\Middleware\AuthMiddleware;

// Health check endpoint
$router->get('/api/health', function() {
    Blog\Utils\Response::success([
        'status' => 'OK',
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '1.0.0'
    ], 'API is running');
});

// Debug route to see all registered routes (development only)
if (Blog\Config\Config::isDevelopment()) {
    $router->get('/api/debug/routes', function() use ($router) {
        $routes = array_map(function($route) {
            return [
                'method' => $route['method'],
                'path' => $route['path'],
                'pattern' => $route['pattern'],
                'params' => $route['params']
            ];
        }, $router->getRoutes());
        
        Blog\Utils\Response::success($routes, 'Registered routes');
    });
}

// Authentication routes
$router->group('/api/auth', [], function($router) {
    $router->post('/login', [AuthController::class, 'login']);
    $router->post('/logout', [AuthController::class, 'logout']);
    $router->get('/verify', [AuthController::class, 'verify'], [AuthMiddleware::class]);
    $router->post('/refresh', [AuthController::class, 'refresh'], [AuthMiddleware::class]);
});

// Public post routes (no authentication required)
// Order matters: specific routes before parameterized routes!

// Get all available tags
$router->get('/api/posts/tags', [PostController::class, 'getTags']);

// Get posts by tag  
$router->get('/api/posts/tag/{tag}', [PostController::class, 'getByTag']);

// Get all published posts with pagination
$router->get('/api/posts', [PostController::class, 'index']);

// Get single post by slug (must be last!)
$router->get('/api/posts/{slug}', [PostController::class, 'show']);

// Admin post routes (authentication required)
$router->group('/api/admin/posts', [AuthMiddleware::class], function($router) {
    // Get all posts (including drafts)
    $router->get('', [PostController::class, 'adminIndex']);
    
    // Get single post by ID for editing
    $router->get('/{id}', [PostController::class, 'adminShow']);
    
    // Create new post
    $router->post('', [PostController::class, 'store']);
    
    // Update existing post
    $router->put('/{id}', [PostController::class, 'update']);
    
    // Delete post
    $router->delete('/{id}', [PostController::class, 'destroy']);
    
    // Publish/unpublish post
    $router->patch('/{id}/publish', [PostController::class, 'togglePublish']);
});

// Enhanced Image Management Routes
$router->group('/api/admin', [AuthMiddleware::class], function($router) {
    // Basic upload endpoints (existing)
    $router->post('/upload', [ImageController::class, 'upload']);
    $router->delete('/upload/{filename}', [ImageController::class, 'delete']);
    
    // Enhanced image management endpoints (new)
    $router->post('/upload/tutorial', [ImageController::class, 'uploadTutorial']);
    $router->get('/images', [ImageController::class, 'index']);
    $router->get('/images/{id}', [ImageController::class, 'show']);
    $router->put('/images/{id}', [ImageController::class, 'update']);
    $router->get('/images/stats', [ImageController::class, 'stats']);
    $router->get('/images/orphaned', [ImageController::class, 'orphaned']);
    
    // Post-specific image endpoints (new)
    $router->get('/posts/{postId}/images', [ImageController::class, 'getPostImages']);
    $router->get('/posts/{postId}/steps', [ImageController::class, 'getTutorialSteps']);
    $router->put('/posts/{postId}/images/reorder', [ImageController::class, 'reorderImages']);
});

// Serve uploaded files (public access)
$router->get('/uploads/{filename}', [ImageController::class, 'serve']);

// Blog statistics endpoint
$router->get('/api/stats', function() {
    try {
        $postModel = new Blog\Models\Post();
        
        $totalPosts = $postModel->count(['status' => 'published']);
        $draftPosts = $postModel->count(['status' => 'draft']);
        $totalViews = 0; // Can be implemented later
        
        Blog\Utils\Response::success([
            'total_posts' => $totalPosts,
            'draft_posts' => $draftPosts,
            'published_posts' => $totalPosts,
            'total_views' => $totalViews
        ], 'Statistics retrieved successfully');
        
    } catch (\Exception $e) {
        error_log("Stats error: " . $e->getMessage());
        Blog\Utils\Response::serverError('Failed to retrieve statistics');
    }
});