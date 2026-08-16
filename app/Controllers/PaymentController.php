<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\CartService;
use App\Services\Database;
use App\Services\View;
use PDO;

class PaymentController
{
    public function show(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $cart = CartService::get();
        if (empty($cart['items'])) {
            header('Location: ' . APP_BASE_PATH . '/pos');
            exit;
        }
        $totals = CartService::totals($db);
        $ref = 'INV-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)) . '-' . date('ymd');
        View::render('pos/payment', compact('user', 'cart', 'totals', 'ref'));
    }

    public function confirm(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $cart = CartService::get();
        if (empty($cart['items'])) {
            View::json(['error' => 'ตะกร้าว่าง'], 400);
            return;
        }
        $totals = CartService::totals($db);
        $method = $_POST['method'] ?? 'cash';
        $tendered = isset($_POST['tendered']) ? (float) $_POST['tendered'] : null;
        $branchId = $user['branch_id'] ?? (int) $db->query('SELECT id FROM branches WHERE merchant_id = ' . (int) $user['merchant_id'] . ' ORDER BY id LIMIT 1')->fetchColumn();

        $db->beginTransaction();
        try {
            $invoice = 'INV-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)) . '-' . date('ymd') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $db->prepare('INSERT INTO sales (merchant_id, branch_id, member_id, user_id, invoice_no, subtotal, discount_total, vat_total, grand_total, coupon_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "completed")');
            $stmt->execute([
                $user['merchant_id'], $branchId, $cart['member_id'], $user['id'], $invoice,
                $totals['subtotal'], $totals['discount_total'], $totals['vat_total'], $totals['grand_total'], $cart['coupon_code'],
            ]);
            $saleId = (int) $db->lastInsertId();

            $itemStmt = $db->prepare('INSERT INTO sale_items (sale_id, variant_id, product_name_snapshot, variant_label_snapshot, qty, unit_price) VALUES (?, ?, ?, ?, ?, ?)');
            $stockStmt = $db->prepare('UPDATE stock_levels SET quantity = GREATEST(0, quantity - ?) WHERE branch_id = ? AND variant_id = ?');
            foreach ($cart['items'] as $variantId => $item) {
                $itemStmt->execute([$saleId, $variantId, $item['name'], $item['variant_label'], $item['qty'], $item['price']]);
                $stockStmt->execute([$item['qty'], $branchId, $variantId]);
            }

            $change = null;
            if ($method === 'cash' && $tendered !== null) {
                $change = round($tendered - $totals['grand_total'], 2);
            }
            $payStmt = $db->prepare('INSERT INTO payments (sale_id, method, amount, tendered, change_amount, reference) VALUES (?, ?, ?, ?, ?, ?)');
            $payStmt->execute([$saleId, $method, $totals['grand_total'], $tendered, $change, $invoice]);

            if ($cart['member_id']) {
                $pointsEarned = (int) floor($totals['grand_total'] / 100);
                $db->prepare('UPDATE members SET points = points + ?, total_spend = total_spend + ? WHERE id = ?')
                    ->execute([$pointsEarned, $totals['grand_total'], $cart['member_id']]);
            }

            $db->commit();
            CartService::clear();
            View::json(['ok' => true, 'invoice_no' => $invoice, 'change' => $change, 'sale_id' => $saleId]);
        } catch (\Throwable $e) {
            $db->rollBack();
            View::json(['error' => $e->getMessage()], 500);
        }
    }
}
