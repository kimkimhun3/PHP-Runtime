<?php

namespace Blog\Models;

class User extends BaseModel
{
    protected string $table = 'users';
    
    protected array $fillable = [
        'email',
        'password_hash',
        'name',
        'role',
        'is_active'
    ];

    protected array $hidden = [
        'password_hash'
    ];

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }

    /**
     * Verify user credentials
     */
    public function verifyCredentials(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        
        if (!$user) {
            error_log("User not found: " . $email);
            return null;
        }

        if (!$user['is_active']) {
            error_log("User inactive: " . $email);
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            error_log("Password verification failed for: " . $email);
            return null;
        }

        // Remove sensitive data
        unset($user['password_hash']);
        return $user;
    }

    /**
     * Create new user with hashed password
     */
    public function createUser(array $data): ?array
    {
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }

        $user = $this->create($data);
        
        if ($user) {
            return $this->hideFields($user);
        }

        return null;
    }

    /**
     * Update user password
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $result = $this->update($userId, ['password_hash' => $hashedPassword]);
        
        return $result !== null;
    }

    /**
     * Check if email exists
     */
    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE email = ?";
        $params = [$email];

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch() !== false;
    }
}