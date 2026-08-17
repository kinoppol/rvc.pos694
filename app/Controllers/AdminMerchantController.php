<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;
use App\Services\View;

class AdminMerchantController
{
    public function index(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();

        $stmt = $db->query("SELECT m.*, COUNT(u.id) AS user_count
            FROM merchants m
            LEFT JOIN users u ON u.merchant_id = m.id
            WHERE m.is_platform = 0
            GROUP BY m.id
            ORDER BY m.status = 'pending' DESC, m.created_at DESC");
        $merchants = $stmt->fetchAll();

        $requireApproval = $this->getRequireApproval($db);

        View::render('admin/merchants', compact('user', 'merchants', 'requireApproval'));
    }

    public function approve(array $args): void
    {
        $id = (int) $args['id'];
        $db = Database::connection();
        $db->prepare("UPDATE merchants SET status = 'active' WHERE id = ? AND is_platform = 0")
           ->execute([$id]);
        $_SESSION['flash'] = 'อนุมัติร้านค้าสำเร็จ';
        header('Location: ' . APP_BASE_PATH . '/admin/merchants');
        exit;
    }

    public function suspend(array $args): void
    {
        $id = (int) $args['id'];
        $db = Database::connection();
        $db->prepare("UPDATE merchants SET status = 'suspended' WHERE id = ? AND is_platform = 0")
           ->execute([$id]);
        $_SESSION['flash'] = 'ระงับร้านค้าเรียบร้อย';
        header('Location: ' . APP_BASE_PATH . '/admin/merchants');
        exit;
    }

    /**
     * สวมสิทธิ์ร้านค้า — switch the platform admin's session to a user of the
     * target merchant (preferring its owner). The admin session is stashed and
     * restored by AuthService::stopImpersonation().
     */
    public function impersonate(array $args): void
    {
        $id = (int) $args['id'];
        $db = Database::connection();

        $stmt = $db->prepare("SELECT * FROM users
            WHERE merchant_id = ? AND active = 1
            ORDER BY FIELD(role, 'owner', 'manager', 'cashier', 'staff'), id
            LIMIT 1");
        $stmt->execute([$id]);
        $target = $stmt->fetch();

        $mStmt = $db->prepare('SELECT name, is_platform FROM merchants WHERE id = ?');
        $mStmt->execute([$id]);
        $merchant = $mStmt->fetch();

        if (!$target || !$merchant || (int) $merchant['is_platform'] === 1) {
            $_SESSION['flash'] = 'ไม่สามารถสวมสิทธิ์ร้านค้านี้ได้ (ไม่พบผู้ใช้ที่ใช้งานอยู่)';
            header('Location: ' . APP_BASE_PATH . '/admin/merchants');
            exit;
        }

        (new AuthService($db))->impersonate($target);
        $_SESSION['impersonator']['merchant_name'] = $merchant['name'];
        header('Location: ' . APP_BASE_PATH . '/pos');
        exit;
    }

    public function stopImpersonate(array $args): void
    {
        if (AuthService::stopImpersonation()) {
            $_SESSION['flash'] = 'ออกจากการสวมสิทธิ์ กลับสู่สิทธิ์ผู้ดูแลระบบแล้ว';
            header('Location: ' . APP_BASE_PATH . '/admin/merchants');
            exit;
        }
        header('Location: ' . APP_BASE_PATH . '/pos');
        exit;
    }

    public function settings(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $requireApproval = $this->getRequireApproval($db);
        View::render('admin/settings', compact('user', 'requireApproval'));
    }

    public function saveSettings(array $args): void
    {
        $db = Database::connection();
        $value = isset($_POST['require_approval']) ? '1' : '0';
        $db->prepare("INSERT INTO system_settings (`key`, `value`) VALUES ('require_approval', ?)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
           ->execute([$value]);
        $_SESSION['flash'] = 'บันทึกการตั้งค่าเรียบร้อย';
        header('Location: ' . APP_BASE_PATH . '/admin/settings');
        exit;
    }

    private function getRequireApproval(\PDO $db): bool
    {
        try {
            $row = $db->query("SELECT `value` FROM system_settings WHERE `key` = 'require_approval'")->fetch();
            return $row && $row['value'] === '1';
        } catch (\PDOException $e) {
            return false;
        }
    }
}
