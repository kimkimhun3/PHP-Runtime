<?php

namespace Blog\Utils;

class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $prefix = '';

    /**
     * Add GET route
     */
    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Add POST route
     */
    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Add PUT route
     */
    public function put(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Add DELETE route
     */
    public function delete(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Add PATCH route
     */
    public function patch(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * Add OPTIONS route
     */
    public function options(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('OPTIONS', $path, $handler, $middleware);
    }

    /**
     * Add route group with prefix and middleware
     */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $oldPrefix = $this->prefix;
        $oldMiddleware = $this->middleware;

        $this->prefix = $oldPrefix . '/' . trim($prefix, '/');
        $this->middleware = array_merge($oldMiddleware, $middleware);

        $callback($this);

        $this->prefix = $oldPrefix;
        $this->middleware = $oldMiddleware;
    }

    /**
     * Add route
     */
    private function addRoute(string $method, string $path, $handler, array $middleware = []): void
    {
        $fullPath = $this->prefix . '/' . trim($path, '/');
        $fullPath = '/' . trim($fullPath, '/');
        
        if ($fullPath === '/') {
            $fullPath = '';
        }

        // Debug: Log routes being added
        if (\Blog\Config\Config::isDebug()) {
            error_log("Adding route: {$method} {$fullPath}");
        }

        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => array_merge($this->middleware, $middleware),
            'pattern' => $this->createPattern($fullPath),
            'params' => $this->extractParamNames($fullPath)
        ];
    }

    /**
     * Create regex pattern for route matching
     */
private function createPattern(string $path): string
{
    // Normalize to leading slash, but no trailing slash (except root)
    $path = '/' . ltrim($path, '/');
    $segments = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));

    // Root route
    if (empty($segments)) {
        return '~^$~u';
    }

    $regexParts = [];
    foreach ($segments as $seg) {
        // {param:regex}
        if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\:(.+)\}$/', $seg, $m)) {
            // Use the user-provided regex as-is inside a capturing group
            $regexParts[] = '(' . $m[2] . ')';
            continue;
        }
        // {param}
        if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $seg, $m)) {
            $regexParts[] = '([^/]+)';
            continue;
        }
        // Literal segment
        $regexParts[] = preg_quote($seg, '~');
    }

    // Build final pattern using ~ delimiter (safer than /)
    return '~^/' . implode('/', $regexParts) . '$~u';
}

    /**
     * Extract parameter names from path
     */
private function extractParamNames(string $path): array
{
    if (preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\s*(?::[^}]*)?\}/', $path, $m)) {
        return $m[1];
    }
    return [];
}

    /**
     * Dispatch request
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        // Remove query string
        $path = parse_url($uri, PHP_URL_PATH);
        
        // Handle CORS preflight
        if ($method === 'OPTIONS') {
            Response::corsOptions();
        }

        // Find matching route
        $matchedRoute = $this->findRoute($method, $path);

        if (!$matchedRoute) {
            Response::notFound('Route not found');
        }

        try {
            // Execute middleware
            $this->executeMiddleware($matchedRoute['middleware']);

            // Execute route handler
            $this->executeHandler($matchedRoute);

        } catch (\Exception $e) {
            error_log("Router error: " . $e->getMessage());
            
            if (\Blog\Config\Config::isDebug()) {
                Response::serverError($e->getMessage());
            } else {
                Response::serverError();
            }
        }
    }

    /**
     * Find matching route
     */
    private function findRoute(string $method, string $path): ?array
    {
        // Debug: Log the request being matched
        if (\Blog\Config\Config::isDebug()) {
            error_log("Matching route: {$method} {$path}");
        }

        foreach ($this->routes as $index => $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            // Debug: Log pattern matching attempts
            if (\Blog\Config\Config::isDebug()) {
                error_log("Testing route {$index}: {$route['path']} (pattern: {$route['pattern']})");
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                // Debug: Log successful match
                if (\Blog\Config\Config::isDebug()) {
                    error_log("Route matched! Matches: " . json_encode($matches));
                }

                // Extract route parameters
                $params = [];
                for ($i = 1; $i < count($matches); $i++) {
                    if (isset($route['params'][$i - 1])) {
                        $params[$route['params'][$i - 1]] = $matches[$i];
                    }
                }

                $route['params'] = $params;
                return $route;
            }
        }

        // Debug: Log no match found
        if (\Blog\Config\Config::isDebug()) {
            error_log("No route found for: {$method} {$path}");
            error_log("Available routes: " . json_encode(array_map(function($r) {
                return $r['method'] . ' ' . $r['path'];
            }, $this->routes)));
        }

        return null;
    }

    /**
     * Execute middleware chain
     */
    private function executeMiddleware(array $middleware): void
    {
        foreach ($middleware as $middlewareClass) {
            if (is_string($middlewareClass) && class_exists($middlewareClass)) {
                $instance = new $middlewareClass();
                if (method_exists($instance, 'handle')) {
                    $instance->handle();
                }
            }
        }
    }

    /**
     * Execute route handler
     */
    private function executeHandler(array $route): void
    {
        $handler = $route['handler'];
        $params = $route['params'];

        if (is_array($handler) && count($handler) === 2) {
            // Controller@method format
            [$controllerClass, $method] = $handler;
            
            if (!class_exists($controllerClass)) {
                throw new \Exception("Controller class not found: {$controllerClass}");
            }

            $controller = new $controllerClass();
            
            if (!method_exists($controller, $method)) {
                throw new \Exception("Method not found: {$controllerClass}::{$method}");
            }

            // Call controller method with parameters
            call_user_func_array([$controller, $method], array_values($params));
            
        } elseif (is_callable($handler)) {
            // Closure or function
            call_user_func_array($handler, array_values($params));
            
        } else {
            throw new \Exception("Invalid route handler");
        }
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get current request method
     */
    public function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Get current request URI
     */
    public function getUri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /**
     * Get current request path
     */
    public function getPath(): string
    {
        return parse_url($this->getUri(), PHP_URL_PATH);
    }
}