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
     * (see config/filesystems.php); defaults to public_path('uploads') —
     * the web-served location directly, with NO symlink in between.
     *
     * This used to default to base_path('uploads') with public/uploads as a
     * separate symlink pointing at it. That silently broke: creating a
     * symlink via `ln -s` requires an elevated privilege on Windows
     * (SeCreateSymbolicLinkPrivilege) that a normal Git Bash session
     * doesn't have, and without it `ln -s` doesn't error — it silently
     * falls back to a one-time directory COPY instead of a live link. Every
     * upload after that copy was made kept writing to base_path('uploads')
     * correctly, but the web-facing "symlink" was actually a frozen
     * snapshot, so newly uploaded files 404'd on their public URL despite
     * existing on disk. Removing the indirection entirely removes the
     * failure mode: there is now exactly one physical uploads directory.
     */
    public static function basePath(string $relative = ''): string
    {
        $root = rtrim(config('filesystems.uploads_path') ?: public_path('uploads'), '/\\');

        return $relative === '' ? $root : $root.'/'.ltrim($relative, '/\\');
    }

    private static array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];
    private static int $maxBytes = 5 * 1024 * 1024; // 5 MB

    private static array $mimeToExtensions = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/zip' => ['docx'],
    ];

    /**
     * Human-readable reason the last store()/storeNamed() call returned null.
     * Callers should surface this instead of silently dropping the file
     * (e.g. "your account saved but the photo didn't" with no explanation).
     */
    public static ?string $lastError = null;

    public static function store(array $file, string $category): ?string
    {
        self::$lastError = null;

        if (!self::isValidUpload($file)) {
            return null; // isValidUpload() already set $lastError
        }

        // Trust the magic-byte MIME type over the (user-supplied, often wrong
        // or uppercase) filename extension. A "screenshot.png" that is really
        // JPEG data, a ".JPG", or a browser "copy image" saved as .webp all
        // used to be rejected here with no message.
        $mimeType = mime_content_type($file['tmp_name']) ?: '';
        $expectedExts = self::$mimeToExtensions[$mimeType] ?? null;
        if ($expectedExts === null) {
            self::$lastError = 'Unsupported file type'
                .($mimeType !== '' ? " ({$mimeType})" : '')
                .'. Allowed: JPG, PNG, GIF, WEBP'
                .(str_contains($category, 'logo') ? ', PDF, DOC.' : '.');

            return null;
        }
        $ext = $expectedExts[0];

        $targetDir = self::basePath(trim($category, '/')).'/';
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            self::$lastError = 'Server could not create the upload folder.';

            return null;
        }

        $filename = uniqid($category.'_', true).'.'.$ext;
        $targetPath = $targetDir.$filename;

        if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
            self::$lastError = 'Server could not save the uploaded file (check upload folder permissions).';

            return null;
        }

        return $filename;
    }

    public static function storeNamed(array $file, string $category, array $allowedMimeTypes): ?string
    {
        self::$lastError = null;

        if (!self::isValidUpload($file)) {
            return null;
        }

        $mimeType = mime_content_type($file['tmp_name']) ?: '';
        $expectedExts = self::$mimeToExtensions[$mimeType] ?? null;
        if (!in_array($mimeType, $allowedMimeTypes, true) || $expectedExts === null) {
            self::$lastError = 'Unsupported file type'.($mimeType !== '' ? " ({$mimeType})" : '').'.';

            return null;
        }
        $ext = $expectedExts[0];

        $targetDir = self::basePath(trim($category, '/')).'/';
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            self::$lastError = 'Server could not create the upload folder.';

            return null;
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $filename = uniqid('', true).'_'.$safeName.'.'.$ext;
        $targetPath = $targetDir.$filename;

        if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
            self::$lastError = 'Server could not save the uploaded file.';

            return null;
        }

        return $filename;
    }

    private static function isValidUpload(array $file): bool
    {
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (!isset($file['tmp_name']) || $err !== UPLOAD_ERR_OK) {
            self::$lastError = match ($err) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image is too large for the server to accept.',
                UPLOAD_ERR_PARTIAL => 'The upload was interrupted — please try again.',
                UPLOAD_ERR_NO_FILE => 'No file was received by the server.',
                UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Server upload configuration error.',
                default => 'The file could not be uploaded.',
            };

            return false;
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            self::$lastError = 'The file was not received as a valid upload.';

            return false;
        }

        $size = $file['size'] ?? 0;
        if ($size <= 0) {
            self::$lastError = 'The selected file is empty.';

            return false;
        }
        if ($size > self::$maxBytes) {
            self::$lastError = 'The image is larger than the '.(self::$maxBytes / 1048576).' MB limit.';

            return false;
        }

        return true;
    }
}
