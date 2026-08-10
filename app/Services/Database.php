<?php
declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            if (!defined('DB_HOST')) {
                throw new PDOException('ยังไม่ได้ตั้งค่าฐานข้อมูล กรุณารัน install.php ก่อน');
            }
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT ?? '3306',
                DB_NAME
            );
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$instance;
    }

    public static function connectWith(string $host, string $port, string $name, string $user, string $pass, bool $createIfMissing = false): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        if ($createIfMissing) {
            $safeName = str_replace('`', '', $name);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$safeName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        $pdo->exec('USE `' . str_replace('`', '', $name) . '`');
        return $pdo;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
