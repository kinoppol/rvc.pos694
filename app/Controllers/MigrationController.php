<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;
use App\Services\MigrationRunner;
use App\Services\View;

class MigrationController
{
    public function index(array $args): void
    {
        $user = AuthService::currentUser();
        $runner = new MigrationRunner(Database::connection());
        $all = $runner->allMigrationFiles();
        $applied = $runner->appliedMigrations();
        $pending = $runner->pendingMigrations();

        $db = Database::connection();
        $stmt = $db->query('SELECT migration, batch, applied_at FROM migrations ORDER BY id');
        $meta = [];
        foreach ($stmt->fetchAll() as $row) {
            $meta[$row['migration']] = $row;
        }

        View::render('admin/migrations', compact('user', 'all', 'applied', 'pending', 'meta'));
    }

    public function run(array $args): void
    {
        $runner = new MigrationRunner(Database::connection());
        $result = $runner->migrate();
        $_SESSION['flash'] = $result['error']
            ? 'เกิดข้อผิดพลาด: ' . $result['error']
            : (count($result['applied']) ? 'รัน migration สำเร็จ: ' . implode(', ', $result['applied']) : 'ไม่มี migration ที่ค้างอยู่');
        header('Location: ' . APP_BASE_PATH . '/admin/migrations');
        exit;
    }

    public function rollback(array $args): void
    {
        $runner = new MigrationRunner(Database::connection());
        $result = $runner->rollbackLastBatch();
        $_SESSION['flash'] = $result['error']
            ? 'เกิดข้อผิดพลาด: ' . $result['error']
            : (count($result['rolled_back']) ? 'ย้อนกลับสำเร็จ: ' . implode(', ', $result['rolled_back']) : 'ไม่มี batch ให้ย้อนกลับ');
        header('Location: ' . APP_BASE_PATH . '/admin/migrations');
        exit;
    }
}
