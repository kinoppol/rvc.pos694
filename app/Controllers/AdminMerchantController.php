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

    /** ภาพรวมของร้านค้าหนึ่งร้าน — สาขา พนักงาน สินค้า และกิจกรรมรายวัน (platform admin) */
    public function show(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $id = (int) $args['id'];

        $stmt = $db->prepare("SELECT m.*,
                (SELECT COUNT(*) FROM branches b WHERE b.merchant_id = m.id) AS branch_count,
                (SELECT COUNT(*) FROM users u WHERE u.merchant_id = m.id) AS user_count,
                (SELECT COUNT(*) FROM users u WHERE u.merchant_id = m.id AND u.active = 1) AS active_user_count,
                (SELECT COUNT(*) FROM products p WHERE p.merchant_id = m.id) AS product_count,
                (SELECT COUNT(*) FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.merchant_id = m.id) AS variant_count,
                (SELECT COUNT(*) FROM members mb WHERE mb.merchant_id = m.id) AS member_count
            FROM merchants m WHERE m.id = ? AND m.is_platform = 0");
        $stmt->execute([$id]);
        $merchant = $stmt->fetch();

        if (!$merchant) {
            http_response_code(404);
            echo 'ไม่พบร้านค้า';
            return;
        }

        $bStmt = $db->prepare("SELECT b.id, b.name, b.address,
                (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.id) AS user_count,
                (SELECT COUNT(*) FROM sales s WHERE s.branch_id = b.id AND s.status = 'completed') AS sale_count,
                (SELECT COALESCE(SUM(s.grand_total),0) FROM sales s WHERE s.branch_id = b.id AND s.status = 'completed') AS revenue
            FROM branches b WHERE b.merchant_id = ? ORDER BY b.name");
        $bStmt->execute([$id]);
        $branches = $bStmt->fetchAll();

        $staffStmt = $db->prepare("SELECT u.full_name, u.username, u.role, u.active, b.name AS branch_name
            FROM users u LEFT JOIN branches b ON b.id = u.branch_id
            WHERE u.merchant_id = ?
            ORDER BY FIELD(u.role,'owner','manager','cashier','staff'), u.full_name");
        $staffStmt->execute([$id]);
        $staff = $staffStmt->fetchAll();

        // กิจกรรมรายวัน 14 วันล่าสุด
        $activity = [];
        for ($d = 13; $d >= 0; $d--) {
            $activity[date('Y-m-d', strtotime("-$d day"))] = ['bills' => 0, 'revenue' => 0.0, 'new_members' => 0, 'clock_ins' => 0];
        }
        $since = date('Y-m-d', strtotime('-13 day'));

        $salesAgg = $db->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS bills, COALESCE(SUM(grand_total),0) AS revenue
            FROM sales WHERE merchant_id = ? AND status = 'completed' AND created_at >= ?
            GROUP BY DATE(created_at)");
        $salesAgg->execute([$id, $since . ' 00:00:00']);
        foreach ($salesAgg->fetchAll() as $r) {
            if (isset($activity[$r['d']])) {
                $activity[$r['d']]['bills'] = (int) $r['bills'];
                $activity[$r['d']]['revenue'] = (float) $r['revenue'];
            }
        }

        $memAgg = $db->prepare("SELECT member_since AS d, COUNT(*) AS c FROM members
            WHERE merchant_id = ? AND member_since >= ? GROUP BY member_since");
        $memAgg->execute([$id, $since]);
        foreach ($memAgg->fetchAll() as $r) {
            if (isset($activity[$r['d']])) {
                $activity[$r['d']]['new_members'] = (int) $r['c'];
            }
        }

        try {
            $attAgg = $db->prepare("SELECT DATE(a.clocked_at) AS d, COUNT(*) AS c
                FROM attendance_logs a JOIN users u ON u.id = a.user_id
                WHERE u.merchant_id = ? AND a.clock_type = 'in' AND a.clocked_at >= ?
                GROUP BY DATE(a.clocked_at)");
            $attAgg->execute([$id, $since . ' 00:00:00']);
            foreach ($attAgg->fetchAll() as $r) {
                if (isset($activity[$r['d']])) {
                    $activity[$r['d']]['clock_ins'] = (int) $r['c'];
                }
            }
        } catch (\PDOException $e) {
            // ไม่มีตาราง attendance — ข้าม
        }

        View::render('admin/merchant_show', compact('user', 'merchant', 'branches', 'staff', 'activity'));
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
