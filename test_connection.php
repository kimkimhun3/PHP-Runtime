<?php
/**
 * Test database connection script
 * Save this as test_connection.php in your blog-backend root directory
 */

// Database configuration
$host = 'localhost';
$port = '5432';
$dbname = 'blog_db';
$username = 'blog_user';
$password = 'your_password';

try {
    // Create PDO connection
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    echo "✅ Database connection successful!\n\n";

    // Create test table
    $createTableSQL = "
        CREATE TABLE IF NOT EXISTS test_posts (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) UNIQUE NOT NULL,
            content TEXT,
            author VARCHAR(100) DEFAULT 'Kimhoon Rin',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";

    $pdo->exec($createTableSQL);
    echo "✅ Test table created successfully!\n\n";

    // Insert test data
    $insertSQL = "
        INSERT INTO test_posts (title, slug, content) VALUES 
        ('My First Blog Post', 'my-first-blog-post', 'This is the content of my first blog post.'),
        ('Winter Adventure', 'winter-adventure', 'A story about skiing and winter fun.'),
        ('PHP Development Tips', 'php-development-tips', 'Some useful PHP development tips and tricks.')
        ON CONFLICT (slug) DO NOTHING
    ";

    $pdo->exec($insertSQL);
    echo "✅ Test data inserted successfully!\n\n";

    // Fetch and display data
    $stmt = $pdo->query("SELECT * FROM test_posts ORDER BY created_at DESC");
    $posts = $stmt->fetchAll();

    echo "📝 Test posts in database:\n";
    echo str_repeat("-", 80) . "\n";
    
    foreach ($posts as $post) {
        echo "ID: {$post['id']}\n";
        echo "Title: {$post['title']}\n";
        echo "Slug: {$post['slug']}\n";
        echo "Author: {$post['author']}\n";
        echo "Created: {$post['created_at']}\n";
        echo "Content: " . substr($post['content'], 0, 50) . "...\n";
        echo str_repeat("-", 80) . "\n";
    }

    echo "\n✅ Database test completed successfully!\n";
    echo "Total posts: " . count($posts) . "\n";

} catch (PDOException $e) {
    echo "❌ Database connection failed!\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    
    // Common troubleshooting tips
    echo "Troubleshooting tips:\n";
    echo "1. Make sure PostgreSQL is running: brew services start postgresql@15\n";
    echo "2. Check if database exists: psql postgres -c '\\l'\n";
    echo "3. Verify user permissions: psql postgres -c '\\du'\n";
    echo "4. Test manual connection: psql -h localhost -p 5432 -U blog_user -d blog_db\n";
}
?>