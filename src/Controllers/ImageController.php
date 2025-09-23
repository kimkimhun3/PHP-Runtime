<?php

namespace Blog\Controllers;

use Blog\Models\Image;
use Blog\Services\ImageService;
use Blog\Utils\Response;
use Blog\Config\Config;

class ImageController
{
    private Image $imageModel;
    private ImageService $imageService;

    public function __construct()
    {
        $this->imageModel = new Image();
        $this->imageService = new ImageService();
    }

    /**
     * Upload single or multiple images
     * POST /api/admin/upload
     */
    public function upload(): void
    {
        try {
            if (!isset($_FILES['file']) && !isset($_FILES['files'])) {
                Response::validationError(['file' => ['No file uploaded']]);
                return;
            }

            $uploadedFiles = [];

            // Single file
            if (isset($_FILES['file'])) {
                $uploadedFiles[] = $this->processFileUpload($_FILES['file']);
            }

            // Multiple files
            if (isset($_FILES['files'])) {
                $files = $_FILES['files'];
                $count = count($files['name'] ?? []);
                for ($i = 0; $i < $count; $i++) {
                    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    $file = [
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ];
                    $uploadedFiles[] = $this->processFileUpload($file);
                }
            }

            if (empty($uploadedFiles)) {
                Response::validationError(['file' => ['No valid files uploaded']]);
                return;
            }

            $message = count($uploadedFiles) === 1
                ? 'File uploaded successfully'
                : (count($uploadedFiles) . ' files uploaded successfully');

            Response::created(count($uploadedFiles) === 1 ? $uploadedFiles[0] : $uploadedFiles, $message);

        } catch (\Exception $e) {
            error_log("ImageController::upload error: " . $e->getMessage());
            Response::serverError('Upload failed: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/images
     */
    public function index(): void
    {
        try {
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
            $search = trim((string)($_GET['search'] ?? ''));

            if ($search !== '') {
                $result  = $this->imageModel->searchImages($search, $page, $limit);
                $message = "Search results for: {$search}";
            } else {
                $result  = $this->imageModel->getAllImages($page, $limit);
                $message = 'Images retrieved successfully';
            }

            $base = rtrim((string)Config::get('app.url'), '/');
            foreach ($result['data'] as &$image) {
                $image['url'] = $base . '/' . ltrim($image['path'], '/');
                if (!empty($image['thumbnail_path'])) {
                    $image['thumbnail_url'] = $base . '/' . ltrim($image['thumbnail_path'], '/');
                }
            }

            Response::paginated($result['data'], $result['page'], $result['limit'], $result['total'], $message);

        } catch (\Exception $e) {
            error_log("ImageController::index error: " . $e->getMessage());
            Response::serverError('Failed to retrieve images');
        }
    }

    /**
     * GET /api/admin/images/{id}
     */
    public function show(string $id): void
    {
        try {
            if (!is_numeric($id)) {
                Response::validationError(['id' => ['Invalid image ID']]);
                return;
            }

            $image = $this->imageModel->find((int)$id);
            if (!$image) {
                Response::notFound('Image not found');
                return;
            }

            $base = rtrim((string)Config::get('app.url'), '/');
            $image['url'] = $base . '/' . ltrim($image['path'], '/');
            if (!empty($image['thumbnail_path'])) {
                $image['thumbnail_url'] = $base . '/' . ltrim($image['thumbnail_path'], '/');
            }

            // Check if file exists on disk
            $fileInfo = $this->imageService->getFileInfo($image['filename']);
            $image['file_exists'] = $fileInfo !== null;

            Response::success($image, 'Image retrieved successfully');

        } catch (\Exception $e) {
            error_log("ImageController::show error: " . $e->getMessage());
            Response::serverError('Failed to retrieve image');
        }
    }

    /**
     * PUT /api/admin/images/{id}
     */
    public function update(string $id): void
    {
        try {
            if (!is_numeric($id)) {
                Response::validationError(['id' => ['Invalid image ID']]);
                return;
            }
            $imageId = (int)$id;

            $input  = $this->getJsonInput();
            $errors = $this->validateImageMetadata($input);
            if (!empty($errors)) {
                Response::validationError($errors);
                return;
            }

            $allowedFields = ['alt_text', 'description', 'post_id'];
            $updateData    = array_intersect_key($input, array_flip($allowedFields));

            $image = $this->imageModel->update($imageId, $updateData);
            if (!$image) {
                Response::notFound('Image not found');
                return;
            }

            $base = rtrim((string)Config::get('app.url'), '/');
            $image['url'] = $base . '/' . ltrim($image['path'], '/');
            if (!empty($image['thumbnail_path'])) {
                $image['thumbnail_url'] = $base . '/' . ltrim($image['thumbnail_path'], '/');
            }

            Response::success($image, 'Image updated successfully');

        } catch (\Exception $e) {
            error_log("ImageController::update error: " . $e->getMessage());
            Response::serverError('Failed to update image');
        }
    }

    /**
     * DELETE /api/admin/upload/{filename}
     */
    public function delete(string $filename): void
    {
        try {
            $image = $this->imageModel->findByFilename($filename);
            if (!$image) {
                Response::notFound('Image not found');
                return;
            }

            $fileDeleted = $this->imageService->deleteFile($filename);
            $dbDeleted   = $this->imageModel->delete((int)$image['id']);

            if (!$dbDeleted) {
                Response::serverError('Failed to delete image from database');
                return;
            }

            $message = $fileDeleted ? 'Image deleted successfully' : 'Image removed from database (file was already missing)';
            Response::success(null, $message);

        } catch (\Exception $e) {
            error_log("ImageController::delete error: " . $e->getMessage());
            Response::serverError('Failed to delete image');
        }
    }

    /**
     * GET /uploads/{filename}
     */
    public function serve(string $filename): void
    {
        try {
            $fileInfo = $this->imageService->getFileInfo($filename);
            if (!$fileInfo) {
                http_response_code(404);
                echo 'File not found';
                return;
            }

            header('Content-Type: ' . $fileInfo['mime_type']);
            header('Content-Length: ' . $fileInfo['size']);
            header('Cache-Control: public, max-age=31536000');
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));

            $safe = preg_replace('/[^a-zA-Z0-9._-]/', '', basename($filename));
            header('Content-Disposition: inline; filename="' . $safe . '"');

            readfile($fileInfo['full_path']);

        } catch (\Exception $e) {
            error_log("ImageController::serve error: " . $e->getMessage());
            http_response_code(500);
            echo 'Failed to serve file';
        }
    }

    /**
     * GET /api/admin/images/stats
     */
    public function stats(): void
    {
        try {
            $stats = $this->imageModel->getStorageStats();
            $stats['overall']['total_size_formatted']   = $this->formatBytes((int)$stats['overall']['total_size']);
            $stats['overall']['average_size_formatted'] = $this->formatBytes((int)$stats['overall']['average_size']);
            Response::success($stats, 'Storage statistics retrieved successfully');
        } catch (\Exception $e) {
            error_log("ImageController::stats error: " . $e->getMessage());
            Response::serverError('Failed to retrieve statistics');
        }
    }

    /**
     * GET /api/admin/images/orphaned
     */
    public function orphaned(): void
    {
        try {
            $list = $this->imageModel->getOrphanedImages();
            $base = rtrim((string)Config::get('app.url'), '/');
            foreach ($list as &$image) {
                $image['url'] = $base . '/' . ltrim($image['path'], '/');
            }
            Response::success($list, 'Orphaned images retrieved successfully');
        } catch (\Exception $e) {
            error_log("ImageController::orphaned error: " . $e->getMessage());
            Response::serverError('Failed to retrieve orphaned images');
        }
    }

    /**
     * POST /api/admin/upload/tutorial
     */
    public function uploadTutorial(): void
    {
        try {
            if (!isset($_FILES['files'])) {
                Response::validationError(['files' => ['No files uploaded for tutorial']]);
                return;
            }

            $postId = $_POST['post_id'] ?? null;
            if ($postId !== null && !is_numeric($postId)) {
                Response::validationError(['post_id' => ['Invalid post ID']]);
                return;
            }
            $postId = $postId !== null ? (int)$postId : null;

            $files = $_FILES['files'];
            $count = count($files['name'] ?? []);
            $uploaded = [];

            for ($i = 0; $i < $count; $i++) {
                if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;

                $file = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];

                $opts = [
                    'max_width'   => 1200,
                    'max_height'  => 800,
                    'thumb_width' => 300,
                    'thumb_height'=> 200,
                ];

                $fd = $this->imageService->processUpload($file, $opts);

                $row = [
                    'filename'       => $fd['filename'],
                    'original_name'  => $fd['original_name'],
                    'path'           => $fd['path'],
                    'size'           => $fd['size'],
                    'mime_type'      => $fd['mime_type'],
                    'width'          => $fd['width'],
                    'height'         => $fd['height'],
                    'thumbnail_path' => $fd['thumbnail_path'] ?? null,
                    'post_id'        => $postId,
                    'image_type'     => 'step',
                    'sort_order'     => $i + 1,
                    'step_number'    => $i + 1,
                    'caption'        => $_POST['captions'][$i] ?? null,
                ];

                $img = $this->imageModel->create($row);
                if (!$img) {
                    $this->imageService->deleteFile($fd['filename']);
                    throw new \Exception('Failed to save tutorial image to database');
                }

                $base = rtrim((string)Config::get('app.url'), '/');
                $img['url'] = $base . '/' . ltrim($img['path'], '/');
                $img['thumbnail_url'] = !empty($img['thumbnail_path']) ? $base . '/' . ltrim($img['thumbnail_path'], '/') : null;

                $uploaded[] = $img;
            }

            if (empty($uploaded)) {
                Response::validationError(['files' => ['No valid files uploaded']]);
                return;
            }

            Response::created($uploaded, count($uploaded) . ' tutorial images uploaded successfully');

        } catch (\Exception $e) {
            error_log("ImageController::uploadTutorial error: " . $e->getMessage());
            Response::serverError('Tutorial upload failed: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/admin/posts/{postId}/images
     */
    public function getPostImages(string $postId): void
    {
        try {
            if (!is_numeric($postId)) {
                Response::validationError(['postId' => ['Invalid post ID']]);
                return;
            }
            $type   = $_GET['type'] ?? null; // featured, content, gallery, step
            $images = $this->imageModel->getPostImages((int)$postId, $type);

            $base = rtrim((string)Config::get('app.url'), '/');
            foreach ($images as &$img) {
                $img['url'] = $base . '/' . ltrim($img['path'], '/');
                if (!empty($img['thumbnail_path'])) {
                    $img['thumbnail_url'] = $base . '/' . ltrim($img['thumbnail_path'], '/');
                }
            }

            $msg = $type ? (ucfirst((string)$type) . ' images for post retrieved successfully') : 'Post images retrieved successfully';
            Response::success($images, $msg);

        } catch (\Exception $e) {
            error_log("ImageController::getPostImages error: " . $e->getMessage());
            Response::serverError('Failed to retrieve post images');
        }
    }

    /**
     * GET /api/admin/posts/{postId}/steps
     */
    public function getTutorialSteps(string $postId): void
    {
        try {
            if (!is_numeric($postId)) {
                Response::validationError(['postId' => ['Invalid post ID']]);
                return;
            }

            $steps = $this->imageModel->getTutorialSteps((int)$postId);
            $base  = rtrim((string)Config::get('app.url'), '/');

            foreach ($steps as &$s) {
                $s['url'] = $base . '/' . ltrim($s['path'], '/');
                if (!empty($s['thumbnail_path'])) {
                    $s['thumbnail_url'] = $base . '/' . ltrim($s['thumbnail_path'], '/');
                }
            }

            Response::success($steps, 'Tutorial steps retrieved successfully');

        } catch (\Exception $e) {
            error_log("ImageController::getTutorialSteps error: " . $e->getMessage());
            Response::serverError('Failed to retrieve tutorial steps');
        }
    }

    /**
     * PUT /api/admin/posts/{postId}/images/reorder
     */
    public function reorderImages(string $postId): void
    {
        try {
            if (!is_numeric($postId)) {
                Response::validationError(['postId' => ['Invalid post ID']]);
                return;
            }

            $input = $this->getJsonInput();
            if (!isset($input['image_ids']) || !is_array($input['image_ids'])) {
                Response::validationError(['image_ids' => ['Image IDs array is required']]);
                return;
            }

            $ok = $this->imageModel->reorderPostImages((int)$postId, $input['image_ids']);
            if (!$ok) {
                Response::serverError('Failed to reorder images');
                return;
            }

            Response::success(null, 'Images reordered successfully');

        } catch (\Exception $e) {
            error_log("ImageController::reorderImages error: " . $e->getMessage());
            Response::serverError('Failed to reorder images');
        }
    }

    // ---------------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------------

    private function processFileUpload(array $file): array
    {
        $options = [
            'max_width'    => (int)($_POST['max_width']   ?? 0),
            'max_height'   => (int)($_POST['max_height']  ?? 0),
            'thumb_width'  => (int)($_POST['thumb_width'] ?? 0),
            'thumb_height' => (int)($_POST['thumb_height']?? 0),
        ];

        $fd = $this->imageService->processUpload($file, $options);

        // Build DB row
        $row = [
            'filename'       => $fd['filename'],
            'original_name'  => $fd['original_name'],
            'path'           => $fd['path'],
            'size'           => $fd['size'],
            'mime_type'      => $fd['mime_type'],
            'width'          => $fd['width'],
            'height'         => $fd['height'],
            'thumbnail_path' => $fd['thumbnail_path'] ?? null,
            'image_type'     => $_POST['image_type'] ?? 'content',
            'sort_order'     => (int)($_POST['sort_order'] ?? 0),
            'step_number'    => isset($_POST['step_number']) ? (int)$_POST['step_number'] : null,
            'caption'        => $_POST['caption'] ?? null,
            'post_id'        => isset($_POST['post_id']) && is_numeric($_POST['post_id']) ? (int)$_POST['post_id'] : null,
            'alt_text'       => $_POST['alt_text'] ?? null,
            'description'    => $_POST['description'] ?? null,
        ];

        $image = $this->imageModel->create($row);
        if (!$image) {
            $this->imageService->deleteFile($fd['filename']);
            throw new \Exception('Failed to save image to database');
        }

        $base = rtrim((string)Config::get('app.url'), '/');
        $image['url'] = $base . '/' . ltrim($image['path'], '/');
        if (!empty($image['thumbnail_path'])) {
            $image['thumbnail_url'] = $base . '/' . ltrim($image['thumbnail_path'], '/');
        }

        return $image;
    }

    private function validateImageMetadata(array $data): array
    {
        $errors = [];

        if (isset($data['alt_text']) && strlen($data['alt_text']) > 255) {
            $errors['alt_text'] = ['Alt text must be less than 255 characters'];
        }
        if (isset($data['description']) && strlen($data['description']) > 1000) {
            $errors['description'] = ['Description must be less than 1000 characters'];
        }
        if (isset($data['post_id']) && !is_numeric($data['post_id'])) {
            $errors['post_id'] = ['Post ID must be a number'];
        }

        return $errors;
    }

    private function getJsonInput(): array
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Response::validationError(['json' => ['Invalid JSON format']]);
            exit;
        }
        return $data ?? [];
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B','KB','MB','GB','TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
