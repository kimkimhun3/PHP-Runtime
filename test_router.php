<?php

/**
 * Simple router pattern test
 */

function createPattern(string $path): string
{
    // Escape forward slashes and dots
    $pattern = preg_quote($path, '/');
    
    // Replace parameter placeholders {param} with regex groups
    $pattern = preg_replace('/\\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\\}/', '([^/]+)', $pattern);
    
    return '/^' . $pattern . '$/';
}

function extractParamNames(string $path): array
{
    preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $path, $matches);
    return $matches[1] ?? [];
}

// Test patterns
$testRoutes = [
    '/api/posts/tags',
    '/api/posts/tag/{tag}',
    '/api/posts',
    '/api/posts/{slug}'
];

$testPaths = [
    '/api/posts/tags',
    '/api/posts/tag/Skiing',
    '/api/posts',
    '/api/posts/ryuoo-ski-park'
];

echo "=== Route Pattern Test ===\n\n";

foreach ($testRoutes as $route) {
    $pattern = createPattern($route);
    $params = extractParamNames($route);
    
    echo "Route: {$route}\n";
    echo "Pattern: {$pattern}\n";
    echo "Params: " . json_encode($params) . "\n";
    
    foreach ($testPaths as $path) {
        if (preg_match($pattern, $path, $matches)) {
            echo "  ✅ MATCHES: {$path} -> " . json_encode($matches) . "\n";
        } else {
            echo "  ❌ NO MATCH: {$path}\n";
        }
    }
    echo "\n";
}