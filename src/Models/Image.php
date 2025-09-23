<?php

namespace Blog\Models;

class Image extends BaseModel
{
    protected string $table = 'images';

    protected array $fillable = [
        'filename',
        'original_name',
        'path',
        'size',
        'mime_type',
        'width',
        'height',
        'thumbnail_path',
        'image_type',     // featured, content, gallery, step
        'sort_order',
        'step_number',
        'caption',
        'post_id',
        'alt_text',
        'description',
        'created_at',
        'updated_at'
    ];

    // ... other CRUD helpers you already have ...

    /**
     * ✅ PHP 8.4: make $type explicitly nullable
     */
    public function getPostImages(int $postId, ?string $type = null): array
    {
        $params = [':post_id' => $postId];
        $where  = 'post_id = :post_id';

        if ($type !== null && $type !== '') {
            $where .= ' AND image_type = :type';
            $params[':type'] = $type;
        }

        $sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY sort_order ASC, id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Tutorial step images for a post
     */
    public function getTutorialSteps(int $postId): array
    {
        $sql = "SELECT * FROM {$this->table}
                WHERE post_id = :post_id AND image_type = 'step'
                ORDER BY sort_order ASC, step_number ASC, id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':post_id' => $postId]);
        return $stmt->fetchAll();
    }

    /**
     * Reorder images for a post with provided IDs order
     */
    public function reorderPostImages(int $postId, array $imageIds): bool
    {
        $this->db->beginTransaction();
        try {
            $order = 1;
            $stmt = $this->db->prepare("UPDATE {$this->table} SET sort_order = :order WHERE id = :id AND post_id = :post_id");
            foreach ($imageIds as $id) {
                $stmt->execute([':order' => $order++, ':id' => (int)$id, ':post_id' => $postId]);
            }
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("reorderPostImages error: " . $e->getMessage());
            return false;
        }
    }
    public function findByFilename(string $filename): ?array
        {
            $sql = "SELECT * FROM {$this->table} WHERE filename = :filename LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':filename' => $filename]);
            $row = $stmt->fetch();
            return $row ?: null;
        }


    // (Your existing find, findByFilename, create, update, delete, getAllImages, searchImages, getStorageStats, getOrphanedImages, etc., remain unchanged.)
}

