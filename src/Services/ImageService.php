<?php

namespace Blog\Services;

use Blog\Config\Config;

class ImageService
{
    /** Absolute filesystem path ending with slash (e.g. /.../public/uploads/) */
    private string $uploadPath;
    /** Allowed file extensions (lowercase, no dot) */
    private array $allowedTypes;
    /** Max upload size in bytes */
    private int $maxSize;

    public function __construct()
    {
        // e.g. /repo/public/uploads/
        $publicBase = __DIR__ . '/../../public';
        $publicPrefix = Config::get('upload.path', '/uploads/');
        $this->uploadPath = rtrim($publicBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . trim($publicPrefix, '/\\') . DIRECTORY_SEPARATOR;

        $this->allowedTypes = Config::get('upload.allowed_types', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        $this->maxSize      = (int) Config::get('upload.max_size', 5 * 1024 * 1024); // 5MB default

        $this->ensureUploadDirectoryExists();
    }

    /**
     * Validates, moves, optionally resizes and thumbs, returns metadata for DB.
     *
     * @param array $file A single item from $_FILES (name,type,tmp_name,error,size)
     * @param array $options ['max_width','max_height','thumb_width','thumb_height']
     * @return array{filename:string,original_name:string,path:string,size:int,mime_type:string,width:int,height:int,thumbnail_path:?string}
     */
    public function processUpload(array $file, array $options = []): array
    {
        $this->validateUpload($file);

        $originalName = $file['name'] ?? 'upload';
        $ext = strtolower($this->getFileExtension($originalName));

        if (!in_array($ext, $this->allowedTypes, true)) {
            throw new \Exception('File type not allowed: ' . $ext);
        }

        $mimeType = $this->getMimeType($file['tmp_name']) ?? ($file['type'] ?? 'application/octet-stream');
        if (!$this->isValidImageMimeType($mimeType)) {
            throw new \Exception('Unsupported file MIME: ' . $mimeType);
        }

        // Generate unique filename and final absolute path
        $filename = $this->generateUniqueFilename($ext, $originalName);
        $destFull = $this->uploadPath . $filename;

        // Move/copy uploaded file
        if (is_uploaded_file($file['tmp_name'])) {
            if (!move_uploaded_file($file['tmp_name'], $destFull)) {
                throw new \Exception('Failed to move uploaded file');
            }
        } else {
            // Allow CLI/tests
            if (!@copy($file['tmp_name'], $destFull)) {
                throw new \Exception('Failed to copy source file');
            }
        }

        // Original dimensions
        [$width, $height] = $this->getImageDimensions($destFull);

        // Optional downscale of the main image
        $maxW = (int)($options['max_width'] ?? 0);
        $maxH = (int)($options['max_height'] ?? 0);
        if ($maxW > 0 || $maxH > 0) {
            [$width, $height] = $this->resizeImage($destFull, $mimeType, $width, $height, $maxW, $maxH);
        }

        // Optional thumbnail
        $thumbPath = null;
        $tW = (int)($options['thumb_width'] ?? 0);
        $tH = (int)($options['thumb_height'] ?? 0);
        if ($tW > 0 && $tH > 0) {
            $thumbFilename = $this->thumbnailName($filename);
            $thumbPath     = $this->generateThumbnail($destFull, $thumbFilename, $mimeType, $tW, $tH);
        }

        $size = filesize($destFull) ?: 0;

        return [
            'filename'       => $filename,
            'original_name'  => $originalName,
            'path'           => $this->publicUrlPath($filename),     // e.g. /uploads/abc.jpg
            'size'           => (int)$size,
            'mime_type'      => $mimeType,
            'width'          => (int)$width,
            'height'         => (int)$height,
            'thumbnail_path' => $thumbPath,
        ];
    }

    /**
     * Delete main and thumbnail files by *stored filename*.
     */
    public function deleteFile(string $filename): bool
    {
        $ok = true;

        $main = $this->uploadPath . $filename;
        if (is_file($main) && !@unlink($main)) {
            $ok = false;
        }

        $thumb = $this->uploadPath . $this->thumbnailName($filename);
        if (is_file($thumb) && !@unlink($thumb)) {
            $ok = false;
        }

        return $ok;
    }

    /**
     * Return file info by stored filename or null if missing.
     */
    public function getFileInfo(string $filename): ?array
    {
        $full = $this->uploadPath . $filename;
        if (!is_file($full)) {
            return null;
        }

        $mime = $this->getMimeType($full) ?? 'application/octet-stream';

        return [
            'full_path' => $full,
            'mime_type' => $mime,
            'size'      => filesize($full) ?: 0,
        ];
    }

    // ---------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------

    private function validateUpload(array $file): void
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->handleUploadError($file['error'] ?? null);
        }
        if (!isset($file['tmp_name']) || !is_readable($file['tmp_name'])) {
            throw new \Exception('Invalid uploaded file');
        }
        if (!isset($file['size']) || (int)$file['size'] > $this->maxSize) {
            throw new \Exception('File exceeds max size of ' . $this->formatBytes($this->maxSize));
        }
    }

    private function handleUploadError(?int $err): void
    {
        $map = [
            UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the MAX_FILE_SIZE directive specified in the HTML form',
            UPLOAD_ERR_PARTIAL    => 'The uploaded file was only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload',
        ];
        throw new \Exception($map[$err] ?? 'Unknown upload error');
    }

    private function getFileExtension(string $name): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return $ext ?: 'bin';
    }

    private function generateUniqueFilename(string $ext, string $originalBase = ''): string
    {
        $base = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalBase, PATHINFO_FILENAME)) ?: 'file';
        return sprintf('%d_%04d_%s.%s', time(), mt_rand(0, 9999), $base, $ext);
    }

    private function getMimeType(string $path): ?string
    {
        if (!is_readable($path)) return null;
        $f = new \finfo(FILEINFO_MIME_TYPE);
        return $f->file($path) ?: null;
    }

    private function isValidImageMimeType(string $mime): bool
    {
        $mime = strtolower($mime);
        return in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'], true);
    }

    private function getImageDimensions(string $fullPath): array
    {
        $info = @getimagesize($fullPath);
        if ($info === false) {
            throw new \Exception('Failed to read image size');
        }
        return [$info[0], $info[1]];
    }

    private function ensureUploadDirectoryExists(): void
    {
        if (!is_dir($this->uploadPath)) {
            if (!@mkdir($this->uploadPath, 0775, true) && !is_dir($this->uploadPath)) {
                throw new \Exception('Cannot create upload directory: ' . $this->uploadPath);
            }
        }
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Resize image in-place to fit within max box (no upscale). Returns new [w,h].
     */
    private function resizeImage(string $fullPath, string $mime, int $srcW, int $srcH, int $maxW, int $maxH): array
    {
        if ($maxW <= 0 && $maxH <= 0) return [$srcW, $srcH];

        $scaleW = $maxW > 0 ? ($maxW / $srcW) : 1.0;
        $scaleH = $maxH > 0 ? ($maxH / $srcH) : 1.0;
        $scale  = min($scaleW, $scaleH);
        if ($scale >= 1.0) return [$srcW, $srcH]; // no upscaling

        $newW = max(1, (int) floor($srcW * $scale));
        $newH = max(1, (int) floor($srcH * $scale));

        $srcImg = $this->createImageFromFile($fullPath, $mime);
        $dstImg = imagecreatetruecolor($newW, $newH);
        $this->preserveAlpha($dstImg, $mime);

        if (!imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH)) {
            imagedestroy($srcImg);
            imagedestroy($dstImg);
            throw new \Exception('Resample failed during resize');
        }

        $this->saveImage($dstImg, $fullPath, $mime);

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        return [$newW, $newH];
    }

    private function createImageFromFile(string $path, string $mime)
    {
        switch (strtolower($mime)) {
            case 'image/jpeg':
            case 'image/jpg':
                return imagecreatefromjpeg($path);
            case 'image/png':
                return imagecreatefrompng($path);
            case 'image/gif':
                return imagecreatefromgif($path);
            case 'image/webp':
                if (!function_exists('imagecreatefromwebp')) {
                    throw new \Exception('WEBP not supported by GD build');
                }
                return imagecreatefromwebp($path);
            default:
                throw new \Exception('Unsupported image type for read: ' . $mime);
        }
    }

    private function preserveAlpha(\GdImage $img, string $mime): void
    {
        $mime = strtolower($mime);
        if (in_array($mime, ['image/png', 'image/gif', 'image/webp'], true)) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }
    }

    /**
     * ✅ Implemented: persist a GD image resource to disk based on MIME.
     */
    private function saveImage(\GdImage $image, string $fullPath, string $mimeType): void
    {
        $dir = \dirname($fullPath);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new \Exception('Failed to create directory: ' . $dir);
            }
        }

        $ok = false;
        switch (strtolower($mimeType)) {
            case 'image/jpeg':
            case 'image/jpg':
                imageinterlace($image, true);              // progressive JPEG
                $ok = imagejpeg($image, $fullPath, 90);
                break;
            case 'image/png':
                $ok = imagepng($image, $fullPath);
                break;
            case 'image/gif':
                $ok = imagegif($image, $fullPath);
                break;
            case 'image/webp':
                if (!function_exists('imagewebp')) {
                    throw new \Exception('WEBP not supported by GD build');
                }
                $ok = imagewebp($image, $fullPath, 90);
                break;
            default:
                throw new \Exception('Unsupported image type for write: ' . $mimeType);
        }

        if (!$ok) {
            throw new \Exception('Failed to write image: ' . $fullPath);
        }
    }

    /**
     * Generate and save a thumbnail. Returns the **public** path (e.g. /uploads/foo_thumb.jpg)
     */
    public function generateThumbnail(string $srcFullPath, string $thumbFilename, string $mime, int $targetW, int $targetH): string
    {
        if (!is_file($srcFullPath)) {
            throw new \Exception('Source image not found for thumbnail');
        }

        [$srcW, $srcH] = $this->getImageDimensions($srcFullPath);
        $srcImg = $this->createImageFromFile($srcFullPath, $mime);

        $scale = min($targetW / $srcW, $targetH / $srcH);
        $newW = max(1, (int) floor($srcW * $scale));
        $newH = max(1, (int) floor($srcH * $scale));

        $dstImg = imagecreatetruecolor($newW, $newH);
        $this->preserveAlpha($dstImg, $mime);

        if (!imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH)) {
            imagedestroy($srcImg);
            imagedestroy($dstImg);
            throw new \Exception('Failed to resample thumbnail');
        }

        $thumbFull = $this->uploadPath . $thumbFilename;
        $this->saveImage($dstImg, $thumbFull, $mime);

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        return $this->publicUrlPath($thumbFilename);
    }

    private function publicUrlPath(string $filename): string
    {
        $prefix = rtrim((string)Config::get('upload.path', '/uploads/'), '/');
        return $prefix . '/' . ltrim($filename, '/');
    }

    private function thumbnailName(string $filename): string
    {
        $ext  = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        return $base . '_thumb' . ($ext ? ('.' . $ext) : '');
    }
}
