<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;
use App\Services\View;

class MemberController
{
    public function index(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $q = trim($_GET['q'] ?? '');
        $sql = 'SELECT m.*, t.name AS tier_name FROM members m LEFT JOIN member_tiers t ON t.id = m.tier_id WHERE m.merchant_id = ?';
        $params = [$user['merchant_id']];
        if ($q !== '') {
            $sql .= ' AND (m.full_name LIKE ? OR m.phone LIKE ?)';
            $params[] = "%$q%";
            $params[] = "%$q%";
        }
        $sql .= ' ORDER BY m.total_spend DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $members = $stmt->fetchAll();
        View::render('members/index', compact('user', 'members', 'q'));
    }

    public function show(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $id = (int) $args['id'];

        $stmt = $db->prepare('SELECT m.*, t.name AS tier_name FROM members m LEFT JOIN member_tiers t ON t.id = m.tier_id WHERE m.id = ? AND m.merchant_id = ?');
        $stmt->execute([$id, $user['merchant_id']]);
        $member = $stmt->fetch();
        if (!$member) {
            http_response_code(404);
            echo 'ไม่พบสมาชิก';
            return;
        }

        $topProducts = $this->all($db, "SELECT COALESCE(p.name, si.product_name_snapshot) AS name, SUM(si.qty) AS qty
            FROM sale_items si JOIN sales s ON s.id = si.sale_id
            LEFT JOIN product_variants v ON v.id = si.variant_id LEFT JOIN products p ON p.id = v.product_id
            WHERE s.member_id = ? GROUP BY name ORDER BY qty DESC LIMIT 5", [$id]);
        $maxQty = $topProducts ? max(array_column($topProducts, 'qty')) : 1;

        $recentSales = $this->all($db, "SELECT s.*, b.name AS branch_name FROM sales s JOIN branches b ON b.id = s.branch_id
            WHERE s.member_id = ? ORDER BY s.created_at DESC LIMIT 5", [$id]);

        $freqStmt = $db->prepare("SELECT COUNT(*) AS cnt, COALESCE(AVG(grand_total),0) AS avg_total,
            TIMESTAMPDIFF(MONTH, MIN(created_at), NOW()) AS months
            FROM sales WHERE member_id = ? AND status='completed'");
        $freqStmt->execute([$id]);
        $freq = $freqStmt->fetch();
        $months = max(1, (int) ($freq['months'] ?? 1));
        $frequency = round(((int) $freq['cnt']) / $months, 1);

        View::render('members/show', compact('user', 'member', 'topProducts', 'maxQty', 'recentSales', 'frequency', 'freq'));
    }

    public function useWithCart(array $args): void
    {
        $id = (int) $args['id'];
        \App\Services\CartService::setMember($id);
        header('Location: ' . APP_BASE_PATH . '/pos');
        exit;
    }

    private function all($db, string $sql, array $params = []): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
