<?php

namespace Blog\Models;

class Post extends BaseModel
{
    protected string $table = 'posts';
    
    protected array $fillable = [
        'title',
        'slug',
        'content',
        'description',
        'author',
        'image_src',
        'image_alt',
        'image_position_x',
        'image_position_y',
        'tags',
        'status',
        'pub_date',
        'updated_date'
    ];

    protected array $hidden = [];

    /**
     * Get all published posts for public viewing
     */
    public function getPublished(int $page = 1, int $limit = 10): array
    {
        return $this->paginate(
            page: $page,
            limit: $limit,
            conditions: ['status' => 'published'],
            orderBy: 'pub_date',
            direction: 'DESC'
        );
    }

    /**
     * Get single published post by slug
     */
    public function getBySlug(string $slug): ?array
    {
        $post = $this->findBy('slug', $slug);
        
        if (!$post) {
            return null;
        }

        // Convert JSONB tags to array
        $post = $this->convertJsonFields($post, ['tags']);
        
        return $post;
    }

    /**
     * Get all posts for admin (including drafts)
     */
    public function getForAdmin(int $page = 1, int $limit = 10): array
    {
        $result = $this->paginate(
            page: $page,
            limit: $limit,
            orderBy: 'updated_at',
            direction: 'DESC'
        );

        // Convert JSONB fields to arrays
        foreach ($result['data'] as &$post) {
            $post = $this->convertJsonFields($post, ['tags']);
        }

        return $result;
    }

    /**
     * Get single post by ID for admin editing
     */
    public function getForEdit(int $id): ?array
    {
        $post = $this->find($id);
        
        if (!$post) {
            return null;
        }

        return $this->convertJsonFields($post, ['tags']);
    }

    /**
     * Create new post
     */
    public function createPost(array $data): ?array
    {
        // Generate slug if not provided
        if (empty($data['slug']) && !empty($data['title'])) {
            $data['slug'] = $this->generateSlug($data['title']);
        }

        // Ensure slug is unique
        $data['slug'] = $this->ensureUniqueSlug($data['slug']);

        // Convert tags array to JSONB
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = json_encode($data['tags']);
        }

        // Set pub_date if status is published and no date provided
        if ($data['status'] === 'published' && empty($data['pub_date'])) {
            $data['pub_date'] = date('Y-m-d H:i:s');
        }

        // Set updated_date
        $data['updated_date'] = date('Y-m-d H:i:s');

        $post = $this->create($data);
        
        if ($post) {
            return $this->convertJsonFields($post, ['tags']);
        }

        return null;
    }

    /**
     * Update existing post
     */
    public function updatePost(int $id, array $data): ?array
    {
        // Generate new slug if title changed
        if (!empty($data['title']) && (empty($data['slug']))) {
            $data['slug'] = $this->generateSlug($data['title']);
        }

        // Ensure slug is unique (excluding current post)
        if (!empty($data['slug'])) {
            $data['slug'] = $this->ensureUniqueSlug($data['slug'], $id);
        }

        // Convert tags array to JSONB
        if (isset($data['tags']) && is_array($data['tags'])) {
            $data['tags'] = json_encode($data['tags']);
        }

        // Set pub_date if changing to published and no date provided
        if (isset($data['status']) && $data['status'] === 'published') {
            $currentPost = $this->find($id);
            if ($currentPost && $currentPost['status'] !== 'published' && empty($data['pub_date'])) {
                $data['pub_date'] = date('Y-m-d H:i:s');
            }
        }

        // Always update the updated_date
        $data['updated_date'] = date('Y-m-d H:i:s');

        $post = $this->update($id, $data);
        
        if ($post) {
            return $this->convertJsonFields($post, ['tags']);
        }

        return null;
    }

    /**
     * Toggle post publish status
     */
    public function togglePublishStatus(int $id): ?array
    {
        $post = $this->find($id);
        
        if (!$post) {
            return null;
        }

        $newStatus = $post['status'] === 'published' ? 'draft' : 'published';
        $updateData = ['status' => $newStatus];

        // Set pub_date if publishing
        if ($newStatus === 'published' && empty($post['pub_date'])) {
            $updateData['pub_date'] = date('Y-m-d H:i:s');
        }

        return $this->updatePost($id, $updateData);
    }

    /**
     * Get all unique tags
     */
    public function getAllTags(): array
    {
        $sql = "
            SELECT DISTINCT jsonb_array_elements_text(tags) as tag 
            FROM {$this->table} 
            WHERE status = 'published' AND tags IS NOT NULL 
            ORDER BY tag ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        $result = $stmt->fetchAll();
        
        return array_column($result, 'tag');
    }

    /**
     * Get posts by tag
     */
    public function getByTag(string $tag, int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;
        
        try {
            // Count total posts with this tag using JSONB contains operator
            $countSql = "
                SELECT COUNT(*) as total 
                FROM {$this->table} 
                WHERE status = 'published' 
                AND tags @> ?::jsonb
            ";
            
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute([json_encode([$tag])]);
            $total = $countStmt->fetch()['total'];

            // Get posts with this tag
            $sql = "
                SELECT * FROM {$this->table} 
                WHERE status = 'published' 
                AND tags @> ?::jsonb
                ORDER BY pub_date DESC 
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([json_encode([$tag]), $limit, $offset]);
            $posts = $stmt->fetchAll();

            // Convert JSONB fields
            foreach ($posts as &$post) {
                $post = $this->convertJsonFields($post, ['tags']);
            }

            return [
                'data' => $posts,
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int)ceil($total / $limit)
            ];
            
        } catch (\Exception $e) {
            error_log("Post::getByTag error: " . $e->getMessage());
            
            // Fallback: try with LIKE operator instead
            $countSql = "
                SELECT COUNT(*) as total 
                FROM {$this->table} 
                WHERE status = 'published' 
                AND tags::text LIKE ?
            ";
            
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute(['%"' . $tag . '"%']);
            $total = $countStmt->fetch()['total'];

            $sql = "
                SELECT * FROM {$this->table} 
                WHERE status = 'published' 
                AND tags::text LIKE ?
                ORDER BY pub_date DESC 
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['%"' . $tag . '"%', $limit, $offset]);
            $posts = $stmt->fetchAll();

            // Convert JSONB fields
            foreach ($posts as &$post) {
                $post = $this->convertJsonFields($post, ['tags']);
            }

            return [
                'data' => $posts,
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int)ceil($total / $limit)
            ];
        }
    }

    /**
     * Search posts
     */
    public function search(string $query, int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;
        $searchTerm = '%' . strtolower($query) . '%';
        
        // Count total results
        $countSql = "
            SELECT COUNT(*) as total 
            FROM {$this->table} 
            WHERE status = 'published' 
            AND (
                LOWER(title) LIKE ? 
                OR LOWER(content) LIKE ? 
                OR LOWER(description) LIKE ?
            )
        ";
        
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $total = $countStmt->fetch()['total'];

        // Get search results
        $sql = "
            SELECT * FROM {$this->table} 
            WHERE status = 'published' 
            AND (
                LOWER(title) LIKE ? 
                OR LOWER(content) LIKE ? 
                OR LOWER(description) LIKE ?
            )
            ORDER BY pub_date DESC 
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit, $offset]);
        $posts = $stmt->fetchAll();

        // Convert JSONB fields
        foreach ($posts as &$post) {
            $post = $this->convertJsonFields($post, ['tags']);
        }

        return [
            'data' => $posts,
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int)ceil($total / $limit)
        ];
    }

    /**
     * Generate URL-friendly slug from title
     */
    private function generateSlug(string $title): string
    {
        // Convert to lowercase
        $slug = strtolower($title);
        
        // Replace spaces and special characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        
        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');
        
        // Limit length
        $slug = substr($slug, 0, 100);
        
        return $slug;
    }

    /**
     * Ensure slug is unique
     */
    private function ensureUniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $sql = "SELECT id FROM {$this->table} WHERE slug = ?";
            $params = [$slug];

            if ($excludeId !== null) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            if (!$stmt->fetch()) {
                break; // Slug is unique
            }

            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get post statistics
     */
    public function getStats(): array
    {
        $sql = "
            SELECT 
                COUNT(*) as total_posts,
                COUNT(CASE WHEN status = 'published' THEN 1 END) as published_posts,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_posts
            FROM {$this->table}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch();
    }
}