<?php

namespace Blog\Controllers;

use Blog\Models\Post;
use Blog\Utils\Response;
use Blog\Config\Config;

class PostController
{
    private Post $postModel;

    public function __construct()
    {
        $this->postModel = new Post();
    }

    /**
     * Get all published posts (public endpoint)
     * GET /api/posts?page=1&limit=10&search=query
     */
    public function index(): void
    {
        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(
                Config::get('pagination.max_limit'),
                max(1, (int)($_GET['limit'] ?? Config::get('pagination.default_limit')))
            );
            $search = $_GET['search'] ?? '';

            if (!empty($search)) {
                $result = $this->postModel->search($search, $page, $limit);
                $message = "Search results for: {$search}";
            } else {
                $result = $this->postModel->getPublished($page, $limit);
                $message = 'Posts retrieved successfully';
            }

            Response::paginated(
                $result['data'],
                $result['page'],
                $result['limit'],
                $result['total'],
                $message
            );

        } catch (\Exception $e) {
            error_log("PostController::index error: " . $e->getMessage());
            Response::serverError('Failed to retrieve posts');
        }
    }

    /**
     * Get single post by slug (public endpoint)
     * GET /api/posts/{slug}
     */
    public function show(string $slug): void
    {
        try {
            $post = $this->postModel->getBySlug($slug);

            if (!$post) {
                Response::notFound('Post not found');
                return;
            }

            // Only show published posts on public endpoint
            if ($post['status'] !== 'published') {
                Response::notFound('Post not found');
                return;
            }

            Response::success($post, 'Post retrieved successfully');

        } catch (\Exception $e) {
            error_log("PostController::show error: " . $e->getMessage());
            Response::serverError('Failed to retrieve post');
        }
    }

    /**
     * Get all posts for admin (including drafts)
     * GET /api/admin/posts?page=1&limit=10
     */
    public function adminIndex(): void
    {
        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(
                Config::get('pagination.max_limit'),
                max(1, (int)($_GET['limit'] ?? Config::get('pagination.default_limit')))
            );

            $result = $this->postModel->getForAdmin($page, $limit);

            Response::paginated(
                $result['data'],
                $result['page'],
                $result['limit'],
                $result['total'],
                'Admin posts retrieved successfully'
            );

        } catch (\Exception $e) {
            error_log("PostController::adminIndex error: " . $e->getMessage());
            Response::serverError('Failed to retrieve posts');
        }
    }

    /**
     * Get single post by ID for admin editing
     * GET /api/admin/posts/{id}
     */
    public function adminShow(string $id): void
    {
        try {
            if (!is_numeric($id)) {
                Response::validationError(['id' => ['Invalid post ID']]);
                return;
            }

            $post = $this->postModel->getForEdit((int)$id);

            if (!$post) {
                Response::notFound('Post not found');
                return;
            }

            Response::success($post, 'Post retrieved successfully');

        } catch (\Exception $e) {
            error_log("PostController::adminShow error: " . $e->getMessage());
            Response::serverError('Failed to retrieve post');
        }
    }

    /**
     * Create new post
     * POST /api/admin/posts
     */
    public function store(): void
    {
        try {
            $input = $this->getJsonInput();
            $errors = $this->validatePostData($input);

            if (!empty($errors)) {
                Response::validationError($errors);
                return;
            }

            $post = $this->postModel->createPost($input);

            if (!$post) {
                Response::serverError('Failed to create post');
                return;
            }

            Response::created($post, 'Post created successfully');

        } catch (\Exception $e) {
            error_log("PostController::store error: " . $e->getMessage());
            Response::serverError('Failed to create post');
        }
    }

    /**
     * Update existing post
     * PUT /api/admin/posts/{id}
     */
    public function update(string $id): void
    {
        try {
            if (!is_numeric($id)) {
                Response::validationError(['id' => ['Invalid post ID']]);
                return;
            }

            $postId = (int)$id;
            $input = $this->getJsonInput();
            $errors = $this->validatePostData($input, $postId);

            if (!empty($errors)) {
                Response::validationError($errors);
                return;
            }

            $post = $this->postModel->updatePost($postId, $input);

            if (!$post) {
                Response::notFound('Post not found');
                return;
            }

            Response::success($post, 'Post updated successfully');

        } catch (\Exception $e) {
            error_log("PostController::update error: " . $e->getMessage());
            Response::serverError('Failed to update post');
        }
    }

    /**
     * Delete post
     * DELETE /api/admin/posts/{id}
     */
    public function destroy(string $id): void
    {
        try {
            if (!is_numeric($id)) {
                Response::validationError(['id' => ['Invalid post ID']]);
                return;
            }

            $postId = (int)$id;

            if (!$this->postModel->exists($postId)) {
                Response::notFound('Post not found');
                return;
            }

            $success = $this->postModel->delete($postId);

            if (!$success) {
                Response::serverError('Failed to delete post');
                return;
            }

            Response::success(null, 'Post deleted successfully');

        } catch (\Exception $e) {
            error_log("PostController::destroy error: " . $e->getMessage());
            Response::serverError('Failed to delete post');
        }
    }

    /**
     * Toggle publish status
     * PATCH /api/admin/posts/{id}/publish
     */
    public function togglePublish(string $id): void
    {
        try {
            if (!is_numeric($id)) {
                Response::validationError(['id' => ['Invalid post ID']]);
                return;
            }

            $post = $this->postModel->togglePublishStatus((int)$id);

            if (!$post) {
                Response::notFound('Post not found');
                return;
            }

            $action = $post['status'] === 'published' ? 'published' : 'unpublished';
            Response::success($post, "Post {$action} successfully");

        } catch (\Exception $e) {
            error_log("PostController::togglePublish error: " . $e->getMessage());
            Response::serverError('Failed to toggle publish status');
        }
    }

    /**
     * Get all available tags
     * GET /api/posts/tags
     */
    public function getTags(): void
    {
        try {
            $tags = $this->postModel->getAllTags();
            Response::success($tags, 'Tags retrieved successfully');

        } catch (\Exception $e) {
            error_log("PostController::getTags error: " . $e->getMessage());
            Response::serverError('Failed to retrieve tags');
        }
    }

    /**
     * Get posts by tag
     * GET /api/posts/tag/{tag}
     */

// PostController.php
public function getByTag(string $tag): void
{
    try {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(
            Config::get('pagination.max_limit'),
            max(1, (int)($_GET['limit'] ?? Config::get('pagination.default_limit')))
        );

        $result = $this->postModel->getByTag($tag, $page, $limit);

        Response::paginated(
            $result['data'],
            $result['page'],
            $result['limit'],
            $result['total'],
            "Posts with tag '{$tag}' retrieved successfully"
        );

    } catch (\Throwable $e) {
        error_log("PostController::getByTag error: " . $e->getMessage());
        Response::serverError('Failed to retrieve posts by tag');
    }
}


    /**
     * Get JSON input from request body
     */
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

    /**
     * Validate post data
     */
    private function validatePostData(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        // Title validation
        if (empty($data['title'])) {
            $errors['title'] = ['Title is required'];
        } elseif (strlen($data['title']) > 255) {
            $errors['title'] = ['Title must be less than 255 characters'];
        }

        // Content validation
        if (empty($data['content'])) {
            $errors['content'] = ['Content is required'];
        }

        // Slug validation
        if (!empty($data['slug'])) {
            if (!preg_match('/^[a-z0-9\-]+$/', $data['slug'])) {
                $errors['slug'] = ['Slug can only contain lowercase letters, numbers, and hyphens'];
            }

            // Check if slug exists
            $existingPost = $this->postModel->findBy('slug', $data['slug']);
            if ($existingPost && (!$excludeId || $existingPost['id'] !== $excludeId)) {
                $errors['slug'] = ['Slug already exists'];
            }
        }

        // Status validation
        if (!empty($data['status']) && !in_array($data['status'], ['draft', 'published'])) {
            $errors['status'] = ['Status must be either draft or published'];
        }

        // Tags validation
        if (!empty($data['tags']) && !is_array($data['tags'])) {
            $errors['tags'] = ['Tags must be an array'];
        }

        // Author validation
        if (!empty($data['author']) && strlen($data['author']) > 100) {
            $errors['author'] = ['Author name must be less than 100 characters'];
        }

        // Description validation
        if (!empty($data['description']) && strlen($data['description']) > 500) {
            $errors['description'] = ['Description must be less than 500 characters'];
        }

        // Image validation
        if (!empty($data['image_src']) && strlen($data['image_src']) > 500) {
            $errors['image_src'] = ['Image source path is too long'];
        }

        if (!empty($data['image_alt']) && strlen($data['image_alt']) > 255) {
            $errors['image_alt'] = ['Image alt text is too long'];
        }

        // Date validation
        if (!empty($data['pub_date']) && !strtotime($data['pub_date'])) {
            $errors['pub_date'] = ['Invalid publication date format'];
        }

        if (!empty($data['updated_date']) && !strtotime($data['updated_date'])) {
            $errors['updated_date'] = ['Invalid updated date format'];
        }

        return $errors;
    }
}