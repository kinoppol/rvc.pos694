<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Verifies the runtime environment (PHP version, extensions, writable
 * paths) before the installer is allowed to proceed. Reused by the
 * installer and by an admin "environment check" panel.
 */
class PermissionChecker
{
    public const MIN_PHP_VERSION = '8.0.0';

    /**
     * @return array<int, array{label:string, ok:bool, detail:string}>
     */
    public static function checkAll(): array
    {
        $results = [];

        $results[] = [
            'label' => 'เวอร์ชัน PHP (ต้องการ ' . self::MIN_PHP_VERSION . ' ขึ้นไป)',
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '>='),
            'detail' => 'พบ PHP ' . PHP_VERSION,
        ];

        foreach (['pdo_mysql', 'mbstring', 'openssl', 'json'] as $ext) {
            $results[] = [
                'label' => "PHP extension: $ext",
                'ok' => extension_loaded($ext),
                'detail' => extension_loaded($ext) ? 'ติดตั้งแล้ว' : 'ไม่พบ extension นี้',
            ];
        }

        foreach (self::writablePaths() as $label => $path) {
            $ok = self::pathIsWritable($path);
            $results[] = [
                'label' => $label,
                'ok' => $ok,
                'detail' => $ok ? "เขียนได้: $path" : "เขียนไม่ได้ กรุณา chmod 775 (หรือมากกว่า) ที่: $path",
            ];
        }

        return $results;
    }

    public static function allOk(): bool
    {
        foreach (self::checkAll() as $row) {
            if (!$row['ok']) {
                return false;
            }
        }
        return true;
    }

    /** @return array<string, string> */
    public static function writablePaths(): array
    {
        return [
            'โฟลเดอร์ตั้งค่า (app/Config)' => APP_ROOT . '/app/Config',
            'โฟลเดอร์ storage' => APP_STORAGE,
            'โฟลเดอร์ storage/logs' => APP_STORAGE . '/logs',
            'โฟลเดอร์ storage/cache' => APP_STORAGE . '/cache',
            'โฟลเดอร์ storage/uploads' => APP_STORAGE . '/uploads',
        ];
    }

    private static function pathIsWritable(string $path): bool
    {
        if (!is_dir($path)) {
            // Try to create it — a missing-but-creatable folder is not a hard failure.
            if (!@mkdir($path, 0775, true) && !is_dir($path)) {
                return false;
            }
        }
        return is_writable($path);
    }
}
