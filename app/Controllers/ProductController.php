<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;
use App\Services\ImageUploader;
use App\Services\View;
use PDO;

/**
 * จัดการสินค้า — products + product_variants + stock_levels ของสาขาที่เลือก
 * (owner/manager เท่านั้น) หมวดหมู่ถูกสร้างอัตโนมัติจากชื่อที่พิมพ์เข้ามา
 */
class ProductController
{
    public function index(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $branchId = $this->currentBranchId($db, $user);

        $q = trim($_GET['q'] ?? '');
        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int) $_GET['category_id'] : null;

        $sql = "SELECT p.*, c.name AS category_name,
                    (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count,
                    (SELECT COALESCE(SUM(s.quantity),0) FROM stock_levels s
                        JOIN product_variants v2 ON v2.id = s.variant_id
                        WHERE v2.product_id = p.id AND s.branch_id = ?) AS stock_qty
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.merchant_id = ?";
        $params = [$branchId, $user['merchant_id']];
        if ($q !== '') {
            $sql .= ' AND p.name LIKE ?';
            $params[] = "%$q%";
        }
        if ($categoryId !== null) {
            $sql .= ' AND p.category_id = ?';
            $params[] = $categoryId;
        }
        $sql .= ' ORDER BY p.name';

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        View::render('products/index', [
            'user'       => $user,
            'products'   => $products,
            'categories' => $this->categories($db, (int) $user['merchant_id']),
            'branches'   => $this->branches($db, (int) $user['merchant_id']),
            'branchId'   => $branchId,
            'q'          => $q,
            'categoryId' => $categoryId,
            'error'      => $this->takeError(),
        ]);
    }

    public function create(array $args): void
    {
        $user = AuthService::currentUser();
        $this->renderForm(Database::connection(), $user, null);
    }

    public function edit(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $product = $this->findOr404($db, $user, (int) $args['id']);
        if (!$product) {
            return;
        }
        $this->renderForm($db, $user, $product);
    }

    public function store(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $data = $this->productInput($db, $user);

        $imagePath = null;
        try {
            $imagePath = ImageUploader::store($_FILES['image'] ?? []);
        } catch (\RuntimeException $e) {
            $this->back($e->getMessage(), '/products/new');
        }

        $stmt = $db->prepare('INSERT INTO products (merchant_id, category_id, name, base_price, cost_price, markdown_percent, image_note, image_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $user['merchant_id'], $data['category_id'], $data['name'],
            $data['base_price'], $data['cost_price'], $data['markdown_percent'], $data['image_note'], $imagePath,
        ]);
        $productId = (int) $db->lastInsertId();

        $this->syncVariants($db, $user, $productId, $data['name']);

        $_SESSION['flash'] = 'เพิ่มสินค้าเรียบร้อย';
        header('Location: ' . APP_BASE_PATH . '/products/' . $productId . '/edit');
        exit;
    }

    public function update(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $id = (int) $args['id'];
        $product = $this->findOr404($db, $user, $id);
        if (!$product) {
            return;
        }
        $data = $this->productInput($db, $user, $id);

        $imagePath = $product['image_path'] ?? null;
        if (!empty($_POST['remove_image'])) {
            ImageUploader::delete($imagePath);
            $imagePath = null;
        }
        try {
            $uploaded = ImageUploader::store($_FILES['image'] ?? []);
            if ($uploaded !== null) {
                ImageUploader::delete($imagePath);
                $imagePath = $uploaded;
            }
        } catch (\RuntimeException $e) {
            $this->back($e->getMessage(), '/products/' . $id . '/edit');
        }

        $db->prepare('UPDATE products SET category_id = ?, name = ?, base_price = ?, cost_price = ?, markdown_percent = ?, image_note = ?, image_path = ?
                WHERE id = ? AND merchant_id = ?')
           ->execute([
               $data['category_id'], $data['name'], $data['base_price'], $data['cost_price'],
               $data['markdown_percent'], $data['image_note'], $imagePath, $id, $user['merchant_id'],
           ]);

        $this->syncVariants($db, $user, $id, $data['name']);

        $_SESSION['flash'] = 'บันทึกสินค้าเรียบร้อย';
        header('Location: ' . APP_BASE_PATH . '/products/' . $id . '/edit');
        exit;
    }

    /** ลบได้เฉพาะสินค้าที่ยังไม่เคยถูกขาย เพื่อไม่ให้บิลเก่าเสียข้อมูล */
    public function destroy(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $id = (int) $args['id'];
        if (!$this->findOr404($db, $user, $id)) {
            return;
        }

        $stmt = $db->prepare('SELECT COUNT(*) FROM sale_items si
            JOIN product_variants v ON v.id = si.variant_id WHERE v.product_id = ?');
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $this->back('ลบไม่ได้ — สินค้านี้มีประวัติการขายแล้ว');
        }

        $imgStmt = $db->prepare('SELECT image_path FROM product_variants WHERE product_id = ? AND image_path IS NOT NULL
            UNION ALL SELECT image_path FROM products WHERE id = ? AND image_path IS NOT NULL');
        $imgStmt->execute([$id, $id]);
        foreach ($imgStmt->fetchAll(PDO::FETCH_COLUMN) as $file) {
            ImageUploader::delete($file);
        }

        $db->prepare('DELETE FROM products WHERE id = ? AND merchant_id = ?')->execute([$id, $user['merchant_id']]);
        $_SESSION['flash'] = 'ลบสินค้าเรียบร้อย';
        header('Location: ' . APP_BASE_PATH . '/products');
        exit;
    }

    // ---------- helpers ----------

    private function renderForm(PDO $db, array $user, ?array $product): void
    {
        $branchId = $this->currentBranchId($db, $user);
        $variants = [];
        if ($product !== null) {
            $stmt = $db->prepare('SELECT v.*, COALESCE(s.quantity, 0) AS qty, COALESCE(s.reorder_point, 10) AS reorder_point,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.variant_id = v.id) AS sold_count
                FROM product_variants v
                LEFT JOIN stock_levels s ON s.variant_id = v.id AND s.branch_id = ?
                WHERE v.product_id = ? ORDER BY v.id');
            $stmt->execute([$branchId, $product['id']]);
            $variants = $stmt->fetchAll();
        }

        View::render('products/form', [
            'user'       => $user,
            'product'    => $product,
            'variants'   => $variants,
            'categories' => $this->categories($db, (int) $user['merchant_id']),
            'branches'   => $this->branches($db, (int) $user['merchant_id']),
            'branchId'   => $branchId,
            'error'      => $this->takeError(),
        ]);
    }

    private function productInput(PDO $db, array $user, ?int $id = null): array
    {
        $path = $id === null ? '/products/new' : '/products/' . $id . '/edit';

        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $this->back('กรุณากรอกชื่อสินค้า', $path);
        }
        $basePrice = (float) ($_POST['base_price'] ?? 0);
        $costPrice = (float) ($_POST['cost_price'] ?? 0);
        $markdown  = (float) ($_POST['markdown_percent'] ?? 0);
        if ($basePrice < 0 || $costPrice < 0) {
            $this->back('ราคาต้องไม่ติดลบ', $path);
        }
        if ($markdown < 0 || $markdown > 100) {
            $this->back('ส่วนลด (%) ต้องอยู่ระหว่าง 0 - 100', $path);
        }

        return [
            'name'             => $name,
            'category_id'      => $this->resolveCategoryId($db, (int) $user['merchant_id'], trim($_POST['category'] ?? '')),
            'base_price'       => $basePrice,
            'cost_price'       => $costPrice,
            'markdown_percent' => $markdown,
            'image_note'       => trim($_POST['image_note'] ?? '') ?: null,
        ];
    }

    /** สร้างหมวดหมู่ใหม่อัตโนมัติถ้ายังไม่มีชื่อนี้ */
    private function resolveCategoryId(PDO $db, int $merchantId, string $name): ?int
    {
        if ($name === '') {
            return null;
        }
        $stmt = $db->prepare('SELECT id FROM categories WHERE merchant_id = ? AND name = ?');
        $stmt->execute([$merchantId, $name]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }
        $db->prepare('INSERT INTO categories (merchant_id, name) VALUES (?, ?)')->execute([$merchantId, $name]);
        return (int) $db->lastInsertId();
    }

    /**
     * บันทึกแถวตัวเลือกสินค้า (ไซซ์/สี) พร้อมสต็อกของสาขาที่เลือก
     * แถวที่ติ๊กลบและยังไม่เคยขายเท่านั้นจึงจะถูกลบจริง
     */
    private function syncVariants(PDO $db, array $user, int $productId, string $productName): void
    {
        $branchId = $this->currentBranchId($db, $user);
        $rows = $_POST['variants'] ?? [];
        $blocked = 0;

        foreach ($rows as $i => $row) {
            $variantId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $sku     = trim($row['sku'] ?? '');
            $size    = trim($row['size'] ?? '') ?: null;
            $color   = trim($row['color'] ?? '') ?: null;
            $barcode = trim($row['barcode'] ?? '') ?: null;
            $priceOverride = ($row['price_override'] ?? '') === '' ? null : (float) $row['price_override'];
            $qty     = (int) ($row['qty'] ?? 0);
            $reorder = max(0, (int) ($row['reorder_point'] ?? 10));

            if (!empty($row['delete']) && $variantId !== null) {
                $check = $db->prepare('SELECT COUNT(*) FROM sale_items WHERE variant_id = ?');
                $check->execute([$variantId]);
                if ((int) $check->fetchColumn() > 0) {
                    $blocked++;
                    continue;
                }
                $db->prepare('DELETE FROM product_variants WHERE id = ?')->execute([$variantId]);
                continue;
            }

            // แถวที่เพิ่งเพิ่มแต่ยังไม่ได้กรอกอะไรเลย — ข้ามไป
            if ($variantId === null && $sku === '' && $size === null && $color === null && $barcode === null && $qty === 0) {
                continue;
            }

            if ($variantId === null) {
                $db->prepare('INSERT INTO product_variants (product_id, sku, size, color, barcode, price_override) VALUES (?, ?, ?, ?, ?, ?)')
                   ->execute([$productId, $sku !== '' ? $sku : $this->generateSku($db, $productName), $size, $color, $barcode, $priceOverride]);
                $variantId = (int) $db->lastInsertId();
            } elseif ($sku !== '') {
                $db->prepare('UPDATE product_variants SET sku = ?, size = ?, color = ?, barcode = ?, price_override = ?
                        WHERE id = ? AND product_id = ?')
                   ->execute([$sku, $size, $color, $barcode, $priceOverride, $variantId, $productId]);
            } else {
                $db->prepare('UPDATE product_variants SET size = ?, color = ?, barcode = ?, price_override = ?
                        WHERE id = ? AND product_id = ?')
                   ->execute([$size, $color, $barcode, $priceOverride, $variantId, $productId]);
            }

            $db->prepare('INSERT INTO stock_levels (branch_id, variant_id, quantity, reorder_point) VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), reorder_point = VALUES(reorder_point)')
               ->execute([$branchId, $variantId, $qty, $reorder]);

            $this->applyVariantImage($db, $variantId, $i, !empty($row['remove_image']));
        }

        if ($blocked > 0) {
            $_SESSION['product_error'] = 'มี ' . $blocked . ' ตัวเลือกที่ลบไม่ได้เพราะเคยถูกขายไปแล้ว';
        }
    }

    /** Save / replace / clear the image for one variant row (index $i in the POST). */
    private function applyVariantImage(PDO $db, int $variantId, int $i, bool $remove): void
    {
        $stmt = $db->prepare('SELECT image_path FROM product_variants WHERE id = ?');
        $stmt->execute([$variantId]);
        $current = $stmt->fetchColumn() ?: null;

        $next = $current;
        if ($remove) {
            ImageUploader::delete($current);
            $next = null;
        }
        try {
            $uploaded = ImageUploader::store($this->fileSlice('variant_image', $i));
            if ($uploaded !== null) {
                ImageUploader::delete($next);
                $next = $uploaded;
            }
        } catch (\RuntimeException $e) {
            $_SESSION['product_error'] = $e->getMessage();
        }

        if ($next !== $current) {
            $db->prepare('UPDATE product_variants SET image_path = ? WHERE id = ?')->execute([$next, $variantId]);
        }
    }

    /** Reshape $_FILES['key']['x'][$i] into a flat {tmp_name,error,size,name} slice. */
    private function fileSlice(string $key, int $i): array
    {
        $f = $_FILES[$key] ?? null;
        if (!is_array($f) || !isset($f['name'][$i])) {
            return ['error' => UPLOAD_ERR_NO_FILE, 'tmp_name' => '', 'size' => 0, 'name' => ''];
        }
        return [
            'name'     => $f['name'][$i],
            'tmp_name' => $f['tmp_name'][$i] ?? '',
            'error'    => $f['error'][$i] ?? UPLOAD_ERR_NO_FILE,
            'size'     => $f['size'][$i] ?? 0,
        ];
    }

    private function generateSku(PDO $db, string $productName): string
    {
        $letters = preg_replace('/[^A-Za-z0-9]/', '', $productName) ?? '';
        $prefix = $letters !== '' ? strtoupper(substr($letters, 0, 4)) : 'SKU';
        do {
            $sku = $prefix . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmt = $db->prepare('SELECT id FROM product_variants WHERE sku = ?');
            $stmt->execute([$sku]);
        } while ($stmt->fetch());
        return $sku;
    }

    /** สาขาที่กำลังดูสต็อกอยู่ — owner/ผู้จัดการสลับสาขาได้ (จัดการสินค้าร่วมกันทั้งกลุ่ม), พนักงานอื่นยึดสาขาตัวเอง */
    private function currentBranchId(PDO $db, array $user): int
    {
        $requested = isset($_REQUEST['branch_id']) && $_REQUEST['branch_id'] !== '' ? (int) $_REQUEST['branch_id'] : null;
        if ($requested !== null && in_array($user['role'], ['owner', 'manager'], true)) {
            $stmt = $db->prepare('SELECT id FROM branches WHERE id = ? AND merchant_id = ?');
            $stmt->execute([$requested, $user['merchant_id']]);
            if ($stmt->fetch()) {
                return $requested;
            }
        }
        if (!empty($user['branch_id'])) {
            return (int) $user['branch_id'];
        }
        $stmt = $db->prepare('SELECT id FROM branches WHERE merchant_id = ? ORDER BY id LIMIT 1');
        $stmt->execute([$user['merchant_id']]);
        return (int) $stmt->fetchColumn();
    }

    private function categories(PDO $db, int $merchantId): array
    {
        $stmt = $db->prepare('SELECT id, name FROM categories WHERE merchant_id = ? ORDER BY name');
        $stmt->execute([$merchantId]);
        return $stmt->fetchAll();
    }

    private function branches(PDO $db, int $merchantId): array
    {
        $stmt = $db->prepare('SELECT id, name FROM branches WHERE merchant_id = ? ORDER BY name');
        $stmt->execute([$merchantId]);
        return $stmt->fetchAll();
    }

    private function findOr404(PDO $db, array $user, int $id): ?array
    {
        $stmt = $db->prepare('SELECT p.*, c.name AS category_name FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.id = ? AND p.merchant_id = ?');
        $stmt->execute([$id, $user['merchant_id']]);
        $product = $stmt->fetch();
        if (!$product) {
            http_response_code(404);
            echo 'ไม่พบสินค้า';
            return null;
        }
        return $product;
    }

    private function back(string $error, string $path = '/products'): void
    {
        $_SESSION['product_error'] = $error;
        header('Location: ' . APP_BASE_PATH . $path);
        exit;
    }

    private function takeError(): ?string
    {
        $error = $_SESSION['product_error'] ?? null;
        unset($_SESSION['product_error']);
        return $error;
    }
}
