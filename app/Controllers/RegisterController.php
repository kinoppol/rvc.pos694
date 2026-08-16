<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;
use App\Services\View;

class RegisterController
{
    public function show(array $args): void
    {
        if (AuthService::check()) {
            header('Location: ' . APP_BASE_PATH . '/pos');
            exit;
        }
        View::render('register/index', ['errors' => [], 'old' => []]);
    }

    public function store(array $args): void
    {
        if (AuthService::check()) {
            header('Location: ' . APP_BASE_PATH . '/pos');
            exit;
        }

        $old = [
            'shop_name'      => trim($_POST['shop_name'] ?? ''),
            'owner_name'     => trim($_POST['owner_name'] ?? ''),
            'username'       => trim($_POST['username'] ?? ''),
            'email'          => trim($_POST['email'] ?? ''),
            'branch_name'    => trim($_POST['branch_name'] ?? ''),
            'branch_address' => trim($_POST['branch_address'] ?? ''),
        ];
        $password = (string) ($_POST['password'] ?? '');
        $errors = [];

        if ($old['shop_name'] === '') {
            $errors['shop_name'] = 'กรุณาระบุชื่อร้านค้า';
        }
        if ($old['username'] === '') {
            $errors['username'] = 'กรุณาระบุชื่อผู้ใช้';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $old['username'])) {
            $errors['username'] = 'ชื่อผู้ใช้ต้องเป็นตัวอักษร a–z, 0–9, _ ความยาว 3–50 ตัว';
        }
        if (strlen($password) < 8) {
            $errors['password'] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
        }
        if ($old['branch_name'] === '') {
            $old['branch_name'] = 'สาขาหลัก';
        }

        if ($errors) {
            View::render('register/index', compact('errors', 'old'));
            return;
        }

        try {
            $db = Database::connection();

            // check username uniqueness
            $chk = $db->prepare('SELECT id FROM users WHERE username = ?');
            $chk->execute([$old['username']]);
            if ($chk->fetch()) {
                $errors['username'] = 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว';
                View::render('register/index', compact('errors', 'old'));
                return;
            }

            // read require_approval setting (graceful fallback if table not yet migrated)
            $requireApproval = false;
            try {
                $s = $db->query("SELECT `value` FROM system_settings WHERE `key` = 'require_approval'");
                $row = $s ? $s->fetch() : false;
                $requireApproval = $row && $row['value'] === '1';
            } catch (\PDOException $e) {
                // migration not yet run — default open
            }

            $status = $requireApproval ? 'pending' : 'active';
            $ownerName = $old['owner_name'] !== '' ? $old['owner_name'] : $old['username'];

            $db->beginTransaction();

            $db->prepare('INSERT INTO merchants (name, status) VALUES (?, ?)')
               ->execute([$old['shop_name'], $status]);
            $merchantId = (int) $db->lastInsertId();

            $db->prepare('INSERT INTO branches (merchant_id, name, address, lat, lng, geofence_radius_m) VALUES (?, ?, ?, 13.8221, 100.5610, 50)')
               ->execute([$merchantId, $old['branch_name'], $old['branch_address']]);

            $db->prepare('INSERT INTO users (merchant_id, username, email, full_name, password_hash, role, active) VALUES (?, ?, ?, ?, ?, \'owner\', 1)')
               ->execute([$merchantId, $old['username'], $old['email'] ?: null, $ownerName, AuthService::hashPassword($password)]);

            $db->commit();

        } catch (\Throwable $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            $errors['_general'] = 'เกิดข้อผิดพลาดในระบบ: ' . $e->getMessage();
            View::render('register/index', compact('errors', 'old'));
            return;
        }

        if ($requireApproval) {
            $_SESSION['register_flash'] = 'สมัครสำเร็จ! ระบบจะแจ้งเตือนเมื่อผู้ดูแลอนุมัติร้านค้าของคุณ';
        } else {
            $_SESSION['register_flash'] = 'สมัครสำเร็จ! กรุณาเข้าสู่ระบบ';
        }
        header('Location: ' . APP_BASE_PATH . '/login');
        exit;
    }
}
