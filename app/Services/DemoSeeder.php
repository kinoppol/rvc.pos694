<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Populates demo data (categories, products, variants, stock, members,
 * a couple of extra branches, and a sample sale) so a fresh install has
 * something to look at on the dashboard/POS/member screens immediately.
 */
class DemoSeeder
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function seed(int $merchantId, int $mainBranchId): void
    {
        $extraBranches = [
            ['name' => 'สยามพารากอน', 'address' => 'กรุงเทพฯ'],
            ['name' => 'เมกาบางนา', 'address' => 'สมุทรปราการ'],
        ];
        $branchIds = [$mainBranchId];
        foreach ($extraBranches as $b) {
            $stmt = $this->db->prepare('INSERT INTO branches (merchant_id, name, address, lat, lng, geofence_radius_m) VALUES (?, ?, ?, 13.8221, 100.5610, 50)');
            $stmt->execute([$merchantId, $b['name'], $b['address']]);
            $branchIds[] = (int) $this->db->lastInsertId();
        }

        $catStmt = $this->db->prepare('INSERT INTO categories (merchant_id, name) VALUES (?, ?)');
        $catStmt->execute([$merchantId, 'เสื้อผ้า']);
        $catClothing = (int) $this->db->lastInsertId();
        $catStmt->execute([$merchantId, 'เครื่องประดับ']);
        $catAccessory = (int) $this->db->lastInsertId();

        $products = [
            ['name' => 'เสื้อโปโล Cotton Pique', 'category_id' => $catClothing, 'price' => 890, 'cost' => 450, 'sku' => 'PL', 'variants' => [
                ['size' => 'S', 'color' => 'ดำ', 'qty' => 6], ['size' => 'M', 'color' => 'ดำ', 'qty' => 9],
                ['size' => 'L', 'color' => 'ดำ', 'qty' => 4], ['size' => 'XL', 'color' => 'ดำ', 'qty' => 2],
                ['size' => 'M', 'color' => 'ขาว', 'qty' => 7], ['size' => 'L', 'color' => 'ขาว', 'qty' => 5],
            ]],
            ['name' => 'กางเกงชิโน Slim', 'category_id' => $catClothing, 'price' => 1290, 'cost' => 650, 'sku' => 'CH', 'variants' => [
                ['size' => '30', 'color' => 'กรม', 'qty' => 8], ['size' => '32', 'color' => 'กรม', 'qty' => 22],
                ['size' => '34', 'color' => 'กรม', 'qty' => 5],
            ]],
            ['name' => 'หมวกแก๊ป Logo', 'category_id' => $catAccessory, 'price' => 450, 'cost' => 180, 'sku' => 'CAP', 'variants' => [
                ['size' => 'Free', 'color' => 'ดำ', 'qty' => 3],
            ]],
            ['name' => 'เสื้อยืดคอลเลกชันเก่า', 'category_id' => $catClothing, 'price' => 590, 'cost' => 380, 'sku' => 'TEE-OLD', 'variants' => [
                ['size' => 'M', 'color' => 'เทา', 'qty' => 40],
            ]],
        ];

        $productStmt = $this->db->prepare('INSERT INTO products (merchant_id, category_id, name, base_price, cost_price, markdown_percent) VALUES (?, ?, ?, ?, ?, ?)');
        $variantStmt = $this->db->prepare('INSERT INTO product_variants (product_id, sku, size, color, barcode) VALUES (?, ?, ?, ?, ?)');
        $stockStmt = $this->db->prepare('INSERT INTO stock_levels (branch_id, variant_id, quantity, reorder_point) VALUES (?, ?, ?, 3)');

        $allVariantIds = [];
        foreach ($products as $p) {
            $markdown = $p['sku'] === 'TEE-OLD' ? 40 : 0;
            $productStmt->execute([$merchantId, $p['category_id'], $p['name'], $p['price'], $p['cost'], $markdown]);
            $productId = (int) $this->db->lastInsertId();
            $colorCodes = ['ดำ' => 'BK', 'ขาว' => 'WH', 'กรม' => 'NV', 'เทา' => 'GY'];
            foreach ($p['variants'] as $i => $v) {
                $colorCode = $colorCodes[$v['color']] ?? 'XX';
                $sku = $p['sku'] . '-' . $colorCode . '-' . $v['size'];
                $variantStmt->execute([$productId, $sku . '-' . $productId . $i, $v['size'], $v['color'], $sku]);
                $variantId = (int) $this->db->lastInsertId();
                $allVariantIds[] = $variantId;
                foreach ($branchIds as $idx => $branchId) {
                    $qty = $idx === 0 ? $v['qty'] : max(0, $v['qty'] - $idx * 3);
                    $stockStmt->execute([$branchId, $variantId, $qty]);
                }
            }
        }

        $tierStmt = $this->db->prepare('INSERT INTO member_tiers (merchant_id, name, min_spend) VALUES (?, ?, ?)');
        $tierStmt->execute([$merchantId, 'Silver', 0]);
        $tierStmt->execute([$merchantId, 'Gold', 50000]);
        $goldTierId = (int) $this->db->lastInsertId();

        $memberStmt = $this->db->prepare('INSERT INTO members (merchant_id, tier_id, full_name, phone, birthdate, points, total_spend, member_since) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $memberStmt->execute([$merchantId, $goldTierId, 'คุณปณิดา ศรีวัฒน์', '081-234-5678', '1993-08-14', 2480, 184290, '2022-01-15']);
        $memberId = (int) $this->db->lastInsertId();

        $couponStmt = $this->db->prepare('INSERT INTO coupons (merchant_id, code, discount_type, discount_value) VALUES (?, ?, ?, ?)');
        $couponStmt->execute([$merchantId, 'NEWYEAR200', 'amount', 200]);

        $shiftStmt = $this->db->prepare('INSERT INTO attendance_shifts (branch_id, name, start_time, end_time) VALUES (?, ?, ?, ?)');
        foreach ($branchIds as $branchId) {
            $shiftStmt->execute([$branchId, 'กะเช้า', '09:00:00', '18:00:00']);
        }

        // A couple of sample completed sales so the dashboard/member history are non-empty.
        $saleStmt = $this->db->prepare('INSERT INTO sales (merchant_id, branch_id, member_id, user_id, invoice_no, subtotal, discount_total, vat_total, grand_total, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "completed", ?)');
        $itemStmt = $this->db->prepare('INSERT INTO sale_items (sale_id, variant_id, product_name_snapshot, variant_label_snapshot, qty, unit_price, line_discount) VALUES (?, ?, ?, ?, ?, ?, 0)');
        $ownerId = (int) $this->db->query('SELECT id FROM users WHERE merchant_id = ' . $merchantId . ' ORDER BY id LIMIT 1')->fetchColumn();

        $sampleSales = [
            ['days_ago' => 15, 'invoice' => 'INV-LP-' . date('ymd', strtotime('-15 days')) . '-0231', 'total' => 2340],
            ['days_ago' => 29, 'invoice' => 'INV-SP-' . date('ymd', strtotime('-29 days')) . '-1104', 'total' => 1190],
        ];
        foreach ($sampleSales as $s) {
            $createdAt = date('Y-m-d H:i:s', strtotime('-' . $s['days_ago'] . ' days'));
            $saleStmt->execute([$merchantId, $mainBranchId, $memberId, $ownerId, $s['invoice'], $s['total'], 0, round($s['total'] * 0.07 / 1.07, 2), $s['total'], $createdAt]);
            $saleId = (int) $this->db->lastInsertId();
            $variantId = $allVariantIds[array_rand($allVariantIds)];
            $itemStmt->execute([$saleId, $variantId, 'เสื้อโปโล Cotton Pique', null, 1, $s['total']]);
        }
    }
}
