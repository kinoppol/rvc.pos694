<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\CartService;
use App\Services\Database;
use App\Services\View;
use PDO;

class PosController
{
    public function index(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $branchId = $user['branch_id'] ?? $this->firstBranchId($db, (int) $user['merchant_id']);

        $category = $_GET['category'] ?? '';
        $q = trim($_GET['q'] ?? '');

        $sql = "SELECT p.*, c.name AS category_name FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.merchant_id = ?";
        $params = [$user['merchant_id']];
        if ($category !== '') {
            $sql .= ' AND c.name = ?';
            $params[] = $category;
        }
        if ($q !== '') {
            $sql .= ' AND p.name LIKE ?';
            $params[] = "%$q%";
        }
        $sql .= ' ORDER BY p.name';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        foreach ($products as &$p) {
            $vStmt = $db->prepare('SELECT v.*, COALESCE(s.quantity,0) AS qty FROM product_variants v
                LEFT JOIN stock_levels s ON s.variant_id = v.id AND s.branch_id = ?
                WHERE v.product_id = ? ORDER BY v.id');
            $vStmt->execute([$branchId, $p['id']]);
            $p['variants'] = $vStmt->fetchAll();
            $p['total_stock'] = array_sum(array_column($p['variants'], 'qty'));
        }
        unset($p);

        $catStmt = $db->prepare('SELECT DISTINCT c.name FROM categories c JOIN products p ON p.category_id = c.id WHERE c.merchant_id = ? ORDER BY c.name');
        $catStmt->execute([$user['merchant_id']]);
        $categories = array_column($catStmt->fetchAll(), 'name');

        $branchStmt = $db->prepare('SELECT * FROM branches WHERE id = ?');
        $branchStmt->execute([$branchId]);
        $branch = $branchStmt->fetch();

        $cart = CartService::get();
        $totals = CartService::totals($db);

        View::render('pos/index', compact('user', 'products', 'categories', 'category', 'q', 'branch', 'cart', 'totals'));
    }

    public function search(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $q = trim($_GET['q'] ?? '');
        $stmt = $db->prepare("SELECT v.id AS variant_id, v.sku, v.barcode, v.size, v.color, p.name, p.base_price
            FROM product_variants v JOIN products p ON p.id = v.product_id
            WHERE p.merchant_id = ? AND (v.barcode = ? OR v.sku = ? OR p.name LIKE ?) LIMIT 10");
        $like = "%$q%";
        $stmt->execute([$user['merchant_id'], $q, $q, $like]);
        View::json($stmt->fetchAll());
    }

    public function cartAdd(array $args): void
    {
        $variantId = (int) ($_POST['variant_id'] ?? 0);
        $qty = max(1, (int) ($_POST['qty'] ?? 1));
        $db = Database::connection();
        $stmt = $db->prepare('SELECT v.id, v.size, v.color, v.sku, v.price_override, p.name, p.base_price, p.markdown_percent
            FROM product_variants v JOIN products p ON p.id = v.product_id WHERE v.id = ?');
        $stmt->execute([$variantId]);
        $v = $stmt->fetch();
        if (!$v) {
            View::json(['error' => 'ไม่พบสินค้า'], 404);
            return;
        }
        $price = (float) ($v['price_override'] ?? $v['base_price']);
        if ($v['markdown_percent'] > 0) {
            $price = round($price * (1 - $v['markdown_percent'] / 100), 2);
        }
        CartService::addItem($variantId, [
            'name' => $v['name'],
            'variant_label' => trim(($v['color'] ?? '') . ' / ' . ($v['size'] ?? ''), ' /'),
            'sku' => $v['sku'],
            'price' => $price,
        ], $qty);
        View::json(['ok' => true, 'totals' => CartService::totals($db)]);
    }

    public function cartUpdate(array $args): void
    {
        $variantId = (int) ($_POST['variant_id'] ?? 0);
        $qty = (int) ($_POST['qty'] ?? 0);
        CartService::updateQty($variantId, $qty);
        View::json(['ok' => true, 'totals' => CartService::totals(Database::connection())]);
    }

    public function cartClear(array $args): void
    {
        CartService::clear();
        View::json(['ok' => true]);
    }

    public function applyCoupon(array $args): void
    {
        $code = trim($_POST['code'] ?? '');
        $db = Database::connection();
        $user = AuthService::currentUser();
        $stmt = $db->prepare('SELECT * FROM coupons WHERE merchant_id = ? AND code = ? AND active = 1');
        $stmt->execute([$user['merchant_id'], $code]);
        $coupon = $stmt->fetch();
        if (!$coupon) {
            View::json(['error' => 'ไม่พบคูปองนี้ หรือถูกปิดใช้งาน'], 404);
            return;
        }
        CartService::setCoupon($code);
        View::json(['ok' => true, 'totals' => CartService::totals($db)]);
    }

    public function setMember(array $args): void
    {
        $memberId = (int) ($_POST['member_id'] ?? 0) ?: null;
        CartService::setMember($memberId);
        View::json(['ok' => true, 'totals' => CartService::totals(Database::connection())]);
    }

    public function hold(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $cart = CartService::get();
        if (empty($cart['items'])) {
            View::json(['error' => 'ตะกร้าว่าง'], 400);
            return;
        }
        $branchId = $user['branch_id'] ?? $this->firstBranchId($db, (int) $user['merchant_id']);
        $invoice = 'HOLD-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        $stmt = $db->prepare('INSERT INTO sales (merchant_id, branch_id, member_id, user_id, invoice_no, subtotal, discount_total, vat_total, grand_total, status, cart_snapshot) VALUES (?, ?, ?, ?, ?, 0, 0, 0, 0, "held", ?)');
        $stmt->execute([$user['merchant_id'], $branchId, $cart['member_id'], $user['id'], $invoice, json_encode($cart, JSON_UNESCAPED_UNICODE)]);
        CartService::clear();
        View::json(['ok' => true, 'invoice_no' => $invoice]);
    }

    private function firstBranchId(PDO $db, int $merchantId): int
    {
        $stmt = $db->prepare('SELECT id FROM branches WHERE merchant_id = ? ORDER BY id LIMIT 1');
        $stmt->execute([$merchantId]);
        return (int) $stmt->fetchColumn();
    }
}
