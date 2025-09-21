<?php

/**
 * Database Migration Script
 * Run: php migrate.php
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Initialize configuration
Blog\Config\Config::init();

echo "🚀 Running database migrations...\n\n";

try {
    // Get database instance
    $database = Blog\Config\Database::getInstance();
    
    // Run migrations
    $database->runMigrations();
    
    echo "\n✅ All migrations completed successfully!\n";
    echo "🎉 Your database is ready to use.\n\n";
    
    // Show some info
    echo "Database Info:\n";
    echo "- Host: " . $_ENV['DB_HOST'] ?? 'localhost' . "\n";
    echo "- Database: " . $_ENV['DB_NAME'] ?? 'blog_db' . "\n";
    echo "- User: " . $_ENV['DB_USER'] ?? 'blog_user' . "\n\n";
    
    echo "Default Admin Login:\n";
    echo "- Email: admin@blog.com\n";
    echo "- Password: admin123\n";
    echo "⚠️  Please change the default password!\n\n";
    
    echo "Next steps:\n";
    echo "1. Start your development server: php -S localhost:8000 -t public\n";
    echo "2. Test API health: curl http://localhost:8000/api/health\n";
    echo "3. Login to admin: POST http://localhost:8000/api/auth/login\n\n";

} catch (\Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n\n";
    
    echo "Troubleshooting:\n";
    echo "1. Make sure PostgreSQL is running\n";
    echo "2. Check your .env file configuration\n";
    echo "3. Verify database permissions\n";
    echo "4. Run: psql -h localhost -p 5432 -U blog_user -d blog_db\n\n";
    
    exit(1);
}