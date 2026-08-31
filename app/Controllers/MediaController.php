<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\ImageUploader;

/**
 * Streams uploaded product images out of storage/uploads/products/ (that folder
 * has its own `Require all denied`, so files can't be fetched directly).
 */
class MediaController
{
    private const TYPES = [
        'jpg'  => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];

    public function productImage(array $args): void
    {
        $file = basename((string) ($args['file'] ?? ''));
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $path = ImageUploader::dir() . '/' . $file;

        if (!isset(self::TYPES[$ext]) || !preg_match('/^[a-f0-9]{32}\.[a-z]+$/', $file) || !is_file($path)) {
            http_response_code(404);
            echo 'ไม่พบรูปภาพ';
            return;
        }

        header('Content-Type: ' . self::TYPES[$ext]);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=604800');
        readfile($path);
    }
}
