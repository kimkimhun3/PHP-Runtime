<?php

namespace Blog\Config;

use PDO;
use PDOException;

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        try {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? '5432';
            $dbname = $_ENV['DB_NAME'] ?? 'blog_db';
            $username = $_ENV['DB_USER'] ?? 'blog_user';
            $password = $_ENV['DB_PASSWORD'] ?? '';

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_PERSISTENT => false
            ]);

            // Set timezone
            $this->pdo->exec("SET timezone = 'UTC'");
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get singleton database instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get PDO connection
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Begin transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * Check if in transaction
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Get last insert ID
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Prepare and execute query
     */
    public function query(string $sql, array $params = []): \PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage() . " SQL: " . $sql);
            throw new \Exception("Database query failed: " . $e->getMessage());
        }
    }

    /**
     * Execute SQL without returning results
     */
    public function execute(string $sql, array $params = []): bool
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Execute failed: " . $e->getMessage() . " SQL: " . $sql);
            throw new \Exception("Database execute failed: " . $e->getMessage());
        }
    }

    /**
     * Run migrations
     */
    public function runMigrations(): void
    {
        $migrationDir = __DIR__ . '/../Database/migrations/';
        
        if (!is_dir($migrationDir)) {
            throw new \Exception("Migration directory not found: " . $migrationDir);
        }

        $migrations = glob($migrationDir . '*.sql');
        sort($migrations);

        foreach ($migrations as $migration) {
            $sql = file_get_contents($migration);
            if ($sql === false) {
                throw new \Exception("Could not read migration file: " . $migration);
            }

            try {
                $this->pdo->exec($sql);
                echo "✅ Migration completed: " . basename($migration) . "\n";
            } catch (PDOException $e) {
                echo "❌ Migration failed: " . basename($migration) . "\n";
                throw new \Exception("Migration failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserializing
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}