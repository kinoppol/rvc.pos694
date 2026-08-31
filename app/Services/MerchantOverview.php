<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Builds the "store overview" dataset — branch/staff/product counts, per-branch
 * revenue, and 14-day daily activity. Shared by the platform-admin per-merchant
 * page and the owner's own overview.
 */
class MerchantOverview
{
    /** @return array{merchant:array,branches:array,staff:array,activity:array,topSellers:array,topProducts:array,vat:array}|null */
    public static function build(PDO $db, int $merchantId): ?array
    {
        $stmt = $db->prepare("SELECT m.*,
                (SELECT COUNT(*) FROM branches b WHERE b.merchant_id = m.id) AS branch_count,
                (SELECT COUNT(*) FROM users u WHERE u.merchant_id = m.id) AS user_count,
                (SELECT COUNT(*) FROM users u WHERE u.merchant_id = m.id AND u.active = 1) AS active_user_count,
                (SELECT COUNT(*) FROM products p WHERE p.merchant_id = m.id) AS product_count,
                (SELECT COUNT(*) FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.merchant_id = m.id) AS variant_count,
                (SELECT COUNT(*) FROM members mb WHERE mb.merchant_id = m.id) AS member_count
            FROM merchants m WHERE m.id = ?");
        $stmt->execute([$merchantId]);
        $merchant = $stmt->fetch();

        if (!$merchant) {
            return null;
        }

        $bStmt = $db->prepare("SELECT b.id, b.name, b.address,
                (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.id) AS user_count,
                (SELECT COUNT(*) FROM sales s WHERE s.branch_id = b.id AND s.status = 'completed') AS sale_count,
                (SELECT COALESCE(SUM(s.grand_total),0) FROM sales s WHERE s.branch_id = b.id AND s.status = 'completed') AS revenue
            FROM branches b WHERE b.merchant_id = ? ORDER BY b.name");
        $bStmt->execute([$merchantId]);
        $branches = $bStmt->fetchAll();

        $staffStmt = $db->prepare("SELECT u.full_name, u.username, u.role, u.active, b.name AS branch_name
            FROM users u LEFT JOIN branches b ON b.id = u.branch_id
            WHERE u.merchant_id = ?
            ORDER BY FIELD(u.role,'owner','manager','cashier','staff'), u.full_name");
        $staffStmt->execute([$merchantId]);
        $staff = $staffStmt->fetchAll();

        $activity = [];
        for ($d = 13; $d >= 0; $d--) {
            $activity[date('Y-m-d', strtotime("-$d day"))] = ['bills' => 0, 'revenue' => 0.0, 'new_members' => 0, 'clock_ins' => 0];
        }
        $since = date('Y-m-d', strtotime('-13 day'));

        $salesAgg = $db->prepare("SELECT DATE(created_at) AS d, COUNT(*) AS bills, COALESCE(SUM(grand_total),0) AS revenue
            FROM sales WHERE merchant_id = ? AND status = 'completed' AND created_at >= ?
            GROUP BY DATE(created_at)");
        $salesAgg->execute([$merchantId, $since . ' 00:00:00']);
        foreach ($salesAgg->fetchAll() as $r) {
            if (isset($activity[$r['d']])) {
                $activity[$r['d']]['bills'] = (int) $r['bills'];
                $activity[$r['d']]['revenue'] = (float) $r['revenue'];
            }
        }

        $memAgg = $db->prepare("SELECT member_since AS d, COUNT(*) AS c FROM members
            WHERE merchant_id = ? AND member_since >= ? GROUP BY member_since");
        $memAgg->execute([$merchantId, $since]);
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
            $attAgg->execute([$merchantId, $since . ' 00:00:00']);
            foreach ($attAgg->fetchAll() as $r) {
                if (isset($activity[$r['d']])) {
                    $activity[$r['d']]['clock_ins'] = (int) $r['c'];
                }
            }
        } catch (\PDOException $e) {
            // no attendance table — skip
        }

        // เดือนนี้ — พนักงานขายทำยอดสูงสุด 3 อันดับ + สินค้าขายดี 10 รายการ
        $monthStart = date('Y-m-01') . ' 00:00:00';

        $sellerStmt = $db->prepare("SELECT u.full_name, u.username, COUNT(*) AS bills, COALESCE(SUM(s.grand_total),0) AS revenue
            FROM sales s JOIN users u ON u.id = s.user_id
            WHERE s.merchant_id = ? AND s.status = 'completed' AND s.created_at >= ?
            GROUP BY s.user_id
            ORDER BY revenue DESC, bills DESC
            LIMIT 3");
        $sellerStmt->execute([$merchantId, $monthStart]);
        $topSellers = $sellerStmt->fetchAll();

        $productStmt = $db->prepare("SELECT MIN(COALESCE(p.name, si.product_name_snapshot)) AS name,
                MAX(p.image_path) AS image_path,
                SUM(si.qty) AS qty,
                COALESCE(SUM(si.qty * si.unit_price - si.line_discount), 0) AS revenue
            FROM sale_items si
            JOIN sales s ON s.id = si.sale_id
            LEFT JOIN product_variants v ON v.id = si.variant_id
            LEFT JOIN products p ON p.id = v.product_id
            WHERE s.merchant_id = ? AND s.status = 'completed' AND s.created_at >= ?
            GROUP BY COALESCE(p.id, si.product_name_snapshot)
            ORDER BY qty DESC, revenue DESC
            LIMIT 10");
        $productStmt->execute([$merchantId, $monthStart]);
        $topProducts = $productStmt->fetchAll();

        // ยอดขายสะสมย้อนหลัง 12 เดือน เทียบเกณฑ์จดทะเบียน VAT (1.8 ล้านบาท/ปี)
        $vatThreshold = 1_800_000.0;
        $months = [];
        for ($m = 11; $m >= 0; $m--) {
            $months[date('Y-m', strtotime("first day of -$m month"))] = 0.0;
        }
        $vatStart = date('Y-m-01', strtotime('first day of -11 month')) . ' 00:00:00';
        $vatStmt = $db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(grand_total),0) AS revenue
            FROM sales WHERE merchant_id = ? AND status = 'completed' AND created_at >= ?
            GROUP BY ym");
        $vatStmt->execute([$merchantId, $vatStart]);
        foreach ($vatStmt->fetchAll() as $r) {
            if (array_key_exists($r['ym'], $months)) {
                $months[$r['ym']] = (float) $r['revenue'];
            }
        }
        $vat = ['threshold' => $vatThreshold, 'months' => $months, 'total12' => array_sum($months)];

        return compact('merchant', 'branches', 'staff', 'activity', 'topSellers', 'topProducts', 'vat');
    }
}
