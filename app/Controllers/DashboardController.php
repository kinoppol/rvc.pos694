<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;
use App\Services\View;

class DashboardController
{
    public function index(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $merchantId = (int) $user['merchant_id'];

        $merchant = $this->one($db, 'SELECT * FROM merchants WHERE id = ?', [$merchantId]);

        $today = date('Y-m-d');
        $todaySales = (float) $this->scalar($db, "SELECT COALESCE(SUM(grand_total),0) FROM sales WHERE merchant_id = ? AND status = 'completed' AND DATE(created_at) = ?", [$merchantId, $today]);
        $yestSales = (float) $this->scalar($db, "SELECT COALESCE(SUM(grand_total),0) FROM sales WHERE merchant_id = ? AND status = 'completed' AND DATE(created_at) = ?", [$merchantId, date('Y-m-d', strtotime('-1 day'))]);
        $changePct = $yestSales > 0 ? round((($todaySales - $yestSales) / $yestSales) * 100, 1) : 0;

        $billCount = (int) $this->scalar($db, "SELECT COUNT(*) FROM sales WHERE merchant_id = ? AND status='completed'", [$merchantId]);
        $avgBill = $billCount > 0 ? (float) $this->scalar($db, "SELECT COALESCE(AVG(grand_total),0) FROM sales WHERE merchant_id = ? AND status='completed'", [$merchantId]) : 0;

        $vatThisMonth = (float) $this->scalar($db, "SELECT COALESCE(SUM(vat_total),0) FROM sales WHERE merchant_id = ? AND status='completed' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())", [$merchantId]);

        $lowStockCount = (int) $this->scalar($db, "SELECT COUNT(*) FROM stock_levels sl JOIN branches b ON b.id = sl.branch_id WHERE b.merchant_id = ? AND sl.quantity <= sl.reorder_point", [$merchantId]);

        $branches = $this->all($db, "SELECT b.*,
                COALESCE((SELECT SUM(s.grand_total) FROM sales s WHERE s.branch_id = b.id AND s.status='completed' AND DATE(s.created_at) = CURDATE()),0) AS today_sales,
                COALESCE((SELECT COUNT(*) FROM sales s WHERE s.branch_id = b.id AND s.status='completed' AND DATE(s.created_at) = CURDATE()),0) AS today_bills,
                COALESCE((SELECT SUM(sl.quantity * p.cost_price) FROM stock_levels sl JOIN product_variants v ON v.id = sl.variant_id JOIN products p ON p.id = v.product_id WHERE sl.branch_id = b.id),0) AS stock_value,
                COALESCE((SELECT SUM(CASE WHEN sl.quantity <= sl.reorder_point THEN 1 ELSE 0 END) FROM stock_levels sl WHERE sl.branch_id = b.id),0) AS low_stock_count
            FROM branches b WHERE b.merchant_id = ? ORDER BY today_sales DESC", [$merchantId]);

        $topProducts = $this->all($db, "SELECT p.name, SUM(si.qty) AS qty
                FROM sale_items si
                JOIN sales s ON s.id = si.sale_id
                LEFT JOIN product_variants v ON v.id = si.variant_id
                LEFT JOIN products p ON p.id = v.product_id
                WHERE s.merchant_id = ? AND s.status='completed'
                GROUP BY COALESCE(p.name, si.product_name_snapshot)
                ORDER BY qty DESC LIMIT 5", [$merchantId]);
        $maxQty = $topProducts ? max(array_column($topProducts, 'qty')) : 1;

        $lowStockAlerts = $this->all($db, "SELECT p.name, b.name AS branch_name, sl.quantity, sl.reorder_point
                FROM stock_levels sl
                JOIN branches b ON b.id = sl.branch_id
                JOIN product_variants v ON v.id = sl.variant_id
                JOIN products p ON p.id = v.product_id
                WHERE b.merchant_id = ? AND sl.quantity <= sl.reorder_point
                ORDER BY sl.quantity ASC LIMIT 5", [$merchantId]);

        View::render('dashboard/index', compact(
            'user', 'merchant', 'todaySales', 'changePct', 'billCount', 'avgBill',
            'vatThisMonth', 'lowStockCount', 'branches', 'topProducts', 'maxQty', 'lowStockAlerts'
        ));
    }

    private function one($db, string $sql, array $params = []): ?array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    private function all($db, string $sql, array $params = []): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function scalar($db, string $sql, array $params = [])
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
