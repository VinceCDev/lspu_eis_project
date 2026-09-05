<?php

namespace App\Core;

/**
 * Ported from backend/Core/Uploader.php, unchanged — this is a plain,
 * framework-agnostic file-upload validator (extension + magic-byte check),
 * kept as a static utility class rather than converted to Laravel's
 * UploadedFile/Storage facade so its exact validation behavior isn't
 * altered in translation. `uploads/` stays at the project root exactly as
 * in the original app so file paths already stored in the database (e.g.
 * `uploads/profile_picture/xxx.jpg`) keep resolving unchanged.
 */
class Uploader
{
    /**
     * Resolves a path inside the uploads tree. Configurable via UPLOADS_PATH
     * (see config/filesystems.php); defaults to base_path('uploads') so
     * behavior is unchanged for any environment that doesn't set it.
     */
    public static function basePath(string $relative = ''): string
    {
        $root = rtrim(config('filesystems.uploads_path') ?: base_path('uploads'), '/\\');

        return $relative === '' ? $root : $root.'/'.ltrim($relative, '/\\');
    }

    private static array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
    private static int $maxBytes = 5 * 1024 * 1024; // 5 MB

    private static array $mimeToExtensions = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/zip' => ['docx'],
    ];

    public static function store(array $file, string $category): ?string
    {
        if (!self::isValidUpload($file)) {
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::$allowedExtensions, true)) {
            return null;
        }

        $mimeType = mime_content_type($file['tmp_name']);
        $expectedExts = self::$mimeToExtensions[$mimeType] ?? null;
        if ($expectedExts === null || !in_array($ext, $expectedExts, true)) {
            return null;
        }

        $targetDir = self::basePath(trim($category, '/')).'/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = uniqid($category.'_', true).'.'.$ext;
        $targetPath = $targetDir.$filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return null;
        }

        return $filename;
    }

    public static function storeNamed(array $file, string $category, array $allowedMimeTypes): ?string
    {
        if (!self::isValidUpload($file)) {
            return null;
        }

        $mimeType = mime_content_type($file['tmp_name']);
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $expectedExts = self::$mimeToExtensions[$mimeType] ?? null;
        if ($expectedExts === null || !in_array($ext, $expectedExts, true)) {
            return null;
        }

        $targetDir = self::basePath(trim($category, '/')).'/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($file['name']));
        $filename = uniqid('', true).'_'.$safeName;
        $targetPath = $targetDir.$filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return null;
        }

        return $filename;
    }

    private static function isValidUpload(array $file): bool
    {
        if (!isset($file['tmp_name'], $file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            return false;
        }

        if (($file['size'] ?? 0) <= 0 || $file['size'] > self::$maxBytes) {
            return false;
        }

        return true;
    }
}
