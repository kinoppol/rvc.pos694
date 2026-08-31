<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Validates and stores an uploaded product image under storage/uploads/products/.
 * Files are not web-readable there — they are streamed back by MediaController.
 */
class ImageUploader
{
    private const MAX_BYTES = 2 * 1024 * 1024; // 2 MB (stays within a stock php.ini upload_max_filesize)
    private const EXT_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    public static function dir(): string
    {
        $dir = APP_STORAGE . '/uploads/products';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    /**
     * @param array $file one slice of $_FILES (keys: tmp_name, error, size, name)
     * @return string|null stored filename, or null if no file was uploaded
     * @throws \RuntimeException on a real upload that fails validation
     */
    public static function store(array $file): ?string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('อัปโหลดรูปไม่สำเร็จ (error ' . $error . ')');
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new \RuntimeException('ไฟล์รูปต้องไม่เกิน 3 MB');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('ไฟล์อัปโหลดไม่ถูกต้อง');
        }

        $info = @getimagesize($file['tmp_name']);
        $mime = $info['mime'] ?? '';
        if (!isset(self::EXT_BY_MIME[$mime])) {
            throw new \RuntimeException('รองรับเฉพาะรูป JPG, PNG, WebP หรือ GIF');
        }

        $name = bin2hex(random_bytes(16)) . '.' . self::EXT_BY_MIME[$mime];
        $dest = self::dir() . '/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('บันทึกไฟล์รูปไม่สำเร็จ');
        }
        return $name;
    }

    /** Remove a stored file by its bare filename (ignores anything with a path). */
    public static function delete(?string $filename): void
    {
        if (!$filename || $filename !== basename($filename)) {
            return;
        }
        $path = self::dir() . '/' . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
