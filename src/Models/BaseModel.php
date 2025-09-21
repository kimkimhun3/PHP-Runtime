<?php

namespace Blog\Models;

use Blog\Config\Database;
use PDO;

abstract class BaseModel
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = [];
    protected array $dates = ['created_at', 'updated_at'];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Find record by ID
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find record by column value
     */
    public function findBy(string $column, $value): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$column} = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$value]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all records
     */
    public function all(array $columns = ['*'], string $orderBy = 'created_at', string $direction = 'DESC'): array
    {
        $columnsStr = implode(', ', $columns);
        $sql = "SELECT {$columnsStr} FROM {$this->table} ORDER BY {$orderBy} {$direction}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Get records with conditions
     */
    public function where(array $conditions, array $columns = ['*'], string $orderBy = 'created_at', string $direction = 'DESC'): array
    {
        $columnsStr = implode(', ', $columns);
        $whereClause = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                // Handle IN clause
                $placeholders = str_repeat('?,', count($value) - 1) . '?';
                $whereClause[] = "{$column} IN ({$placeholders})";
                $params = array_merge($params, $value);
            } else {
                $whereClause[] = "{$column} = ?";
                $params[] = $value;
            }
        }

        $whereStr = implode(' AND ', $whereClause);
        $sql = "SELECT {$columnsStr} FROM {$this->table} WHERE {$whereStr} ORDER BY {$orderBy} {$direction}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Get paginated records
     */
    public function paginate(int $page = 1, int $limit = 10, array $conditions = [], array $columns = ['*'], string $orderBy = 'created_at', string $direction = 'DESC'): array
    {
        $offset = ($page - 1) * $limit;
        $columnsStr = implode(', ', $columns);
        
        // Build WHERE clause
        $whereClause = '';
        $params = [];
        
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $column => $value) {
                if (is_array($value)) {
                    $placeholders = str_repeat('?,', count($value) - 1) . '?';
                    $whereParts[] = "{$column} IN ({$placeholders})";
                    $params = array_merge($params, $value);
                } else {
                    $whereParts[] = "{$column} = ?";
                    $params[] = $value;
                }
            }
            $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
        }

        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];

        // Get records
        $sql = "SELECT {$columnsStr} FROM {$this->table} {$whereClause} ORDER BY {$orderBy} {$direction} LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return [
            'data' => $stmt->fetchAll(),
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int)ceil($total / $limit)
        ];
    }

    /**
     * Create new record
     */
    public function create(array $data): ?array
    {
        // Filter fillable fields
        $data = $this->filterFillable($data);
        
        // Add timestamps if not present
        if (in_array('created_at', $this->dates) && !isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if (in_array('updated_at', $this->dates) && !isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $columns = array_keys($data);
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES ({$placeholders}) RETURNING *";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $stmt->fetch();
    }

    /**
     * Update record by ID
     */
    public function update(int $id, array $data): ?array
    {
        // Filter fillable fields
        $data = $this->filterFillable($data);
        
        // Add updated timestamp
        if (in_array('updated_at', $this->dates)) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "{$column} = ?";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE {$this->primaryKey} = ? RETURNING *";
        
        $params = array_values($data);
        $params[] = $id;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch();
    }

    /**
     * Delete record by ID
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([$id]);
    }

    /**
     * Count records
     */
    public function count(array $conditions = []): int
    {
        $whereClause = '';
        $params = [];
        
        if (!empty($conditions)) {
            $whereParts = [];
            foreach ($conditions as $column => $value) {
                $whereParts[] = "{$column} = ?";
                $params[] = $value;
            }
            $whereClause = 'WHERE ' . implode(' AND ', $whereParts);
        }

        $sql = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetch()['total'];
    }

    /**
     * Check if record exists
     */
    public function exists(int $id): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE {$this->primaryKey} = ? LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch() !== false;
    }

    /**
     * Execute raw SQL query
     */
    protected function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    /**
     * Filter data to only fillable fields
     */
    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }

        return array_intersect_key($data, array_flip($this->fillable));
    }

    /**
     * Hide sensitive fields from result
     */
    protected function hideFields(array $data): array
    {
        if (empty($this->hidden)) {
            return $data;
        }

        return array_diff_key($data, array_flip($this->hidden));
    }

    /**
     * Convert JSON fields to arrays
     */
    protected function convertJsonFields(array $data, array $jsonFields = []): array
    {
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = json_decode($data[$field], true) ?: [];
            }
        }

        return $data;
    }

    /**
     * Begin database transaction
     */
    public function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    public function commit(): bool
    {
        return $this->db->commit();
    }

    /**
     * Rollback database transaction
     */
    public function rollback(): bool
    {
        return $this->db->rollBack();
    }
}