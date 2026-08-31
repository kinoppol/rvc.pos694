<?php
$e = fn ($v) => \App\Services\View::e((string) $v);
$isEdit = $product !== null;
$action = $isEdit ? APP_BASE_PATH . '/products/' . $product['id'] : APP_BASE_PATH . '/products';
$branchName = '';
foreach ($branches as $b) {
    if ((int) $b['id'] === (int) $branchId) $branchName = $b['name'];
}
$img = fn ($file) => APP_BASE_PATH . '/media/product/' . rawurlencode((string) $file);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => $isEdit ? 'แก้ไขสินค้า' : 'เพิ่มสินค้า']); ?>
</head>
<body>
<div class="app-shell">
    <?php \App\Services\View::render('layout/sidebar', ['active' => 'products', 'user' => $user]); ?>
    <div class="main-area">
        <div class="topbar">
            <div style="font-size:15px;font-weight:600"><?= $isEdit ? 'แก้ไขสินค้า' : 'เพิ่มสินค้าใหม่' ?></div>
            <div class="flex-1"></div>
            <a href="<?= APP_BASE_PATH ?>/products?branch_id=<?= (int) $branchId ?>" class="btn btn-outline" style="height:34px;padding:0 14px;font-size:12.5px">ย้อนกลับ</a>
        </div>
        <div class="content">
            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="card" style="padding:12px 16px;margin-bottom:16px;max-width:900px;background:var(--success-bg);border-color:var(--success-border);color:#047857;font-size:13px"><?= $e($_SESSION['flash']) ?></div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="card" style="padding:12px 16px;margin-bottom:16px;max-width:900px;background:#FEE2E2;border-color:#FECACA;color:#991B1B;font-size:13px"><?= $e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= $action ?>" enctype="multipart/form-data" style="max-width:900px;display:grid;gap:16px">
                <input type="hidden" name="branch_id" value="<?= (int) $branchId ?>">

                <div class="card" style="padding:20px;display:grid;gap:14px">
                    <div style="font-size:14px;font-weight:600">ข้อมูลสินค้า</div>
                    <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px">
                        <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">ชื่อสินค้า *
                            <input type="text" name="name" required value="<?= $e($product['name'] ?? '') ?>" style="height:42px;font-weight:400">
                        </label>
                        <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">หมวดหมู่
                            <input type="text" name="category" list="category-list" placeholder="เช่น เสื้อยืด" style="height:42px;font-weight:400"
                                   value="<?= $e($product['category_name'] ?? '') ?>">
                            <datalist id="category-list">
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $e($c['name']) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </label>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
                        <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">ราคาขาย (รวม VAT) *
                            <input type="number" step="0.01" min="0" name="base_price" required value="<?= $e($product['base_price'] ?? '0.00') ?>" class="mono" style="height:42px;font-weight:400">
                        </label>
                        <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">ราคาทุน
                            <input type="number" step="0.01" min="0" name="cost_price" value="<?= $e($product['cost_price'] ?? '0.00') ?>" class="mono" style="height:42px;font-weight:400">
                        </label>
                        <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">ส่วนลดหน้าร้าน (%)
                            <input type="number" step="0.01" min="0" max="100" name="markdown_percent" value="<?= $e($product['markdown_percent'] ?? '0') ?>" class="mono" style="height:42px;font-weight:400">
                        </label>
                    </div>
                    <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">หมายเหตุ / อีโมจิรูปสินค้า (ใช้เมื่อไม่มีรูปถ่าย)
                        <input type="text" name="image_note" value="<?= $e($product['image_note'] ?? '') ?>" placeholder="เช่น 👕" style="height:42px;font-weight:400">
                    </label>

                    <div style="display:grid;gap:8px;font-size:12.5px;font-weight:600">รูปภาพสินค้า
                        <div class="flex gap-12" style="align-items:center;flex-wrap:wrap">
                            <?php if (!empty($product['image_path'])): ?>
                                <img src="<?= $img($product['image_path']) ?>" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid var(--border)">
                                <label class="text-muted" style="font-size:12px;font-weight:400;display:flex;gap:6px;align-items:center">
                                    <input type="checkbox" name="remove_image" value="1"> ลบรูปนี้
                                </label>
                            <?php endif; ?>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" style="font-weight:400">
                        </div>
                        <span class="text-muted" style="font-size:11px;font-weight:400">JPG / PNG / WebP / GIF ไม่เกิน 2 MB</span>
                    </div>
                </div>

                <div class="card" style="padding:20px;display:grid;gap:12px">
                    <div class="flex" style="align-items:center;gap:10px">
                        <div style="font-size:14px;font-weight:600">ตัวเลือกสินค้า (ไซซ์ / สี) และสต็อก</div>
                        <span class="text-muted" style="font-size:11.5px">สต็อกของสาขา: <?= $e($branchName) ?></span>
                    </div>
                    <div style="overflow-x:auto">
                        <table style="width:100%;border-collapse:collapse;font-size:12.5px;min-width:880px">
                            <thead><tr style="background:var(--bg-lighter);text-align:left">
                                <th style="padding:8px 10px">ไซซ์</th><th>สี</th><th>SKU</th><th>บาร์โค้ด</th><th>รูป</th>
                                <th style="text-align:right">ราคาเฉพาะตัวเลือก</th>
                                <th style="text-align:right">จำนวนคงเหลือ</th>
                                <th style="text-align:right">จุดสั่งซื้อ</th>
                                <th style="text-align:center">ลบ</th>
                            </tr></thead>
                            <tbody id="variant-rows">
                            <?php foreach ($variants as $i => $v): ?>
                                <tr style="border-top:1px solid var(--border)">
                                    <td style="padding:6px 10px">
                                        <input type="hidden" name="variants[<?= $i ?>][id]" value="<?= $v['id'] ?>">
                                        <input type="text" name="variants[<?= $i ?>][size]" value="<?= $e($v['size']) ?>" style="height:36px;width:80px">
                                    </td>
                                    <td><input type="text" name="variants[<?= $i ?>][color]" value="<?= $e($v['color']) ?>" style="height:36px;width:110px"></td>
                                    <td><input type="text" name="variants[<?= $i ?>][sku]" value="<?= $e($v['sku']) ?>" class="mono" style="height:36px;width:140px"></td>
                                    <td><input type="text" name="variants[<?= $i ?>][barcode]" value="<?= $e($v['barcode']) ?>" class="mono" style="height:36px;width:140px"></td>
                                    <td>
                                        <?php if (!empty($v['image_path'])): ?>
                                            <img src="<?= $img($v['image_path']) ?>" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:6px;border:1px solid var(--border);display:block;margin-bottom:3px">
                                            <label style="font-size:10px;display:flex;gap:3px;align-items:center"><input type="checkbox" name="variants[<?= $i ?>][remove_image]" value="1">ลบรูป</label>
                                        <?php endif; ?>
                                        <input type="file" name="variant_image[<?= $i ?>]" accept="image/jpeg,image/png,image/webp,image/gif" style="width:150px;font-size:11px">
                                    </td>
                                    <td style="text-align:right"><input type="number" step="0.01" min="0" name="variants[<?= $i ?>][price_override]" value="<?= $v['price_override'] !== null ? $e($v['price_override']) : '' ?>" placeholder="ใช้ราคาหลัก" class="mono" style="height:36px;width:120px;text-align:right"></td>
                                    <td style="text-align:right"><input type="number" name="variants[<?= $i ?>][qty]" value="<?= (int) $v['qty'] ?>" class="mono" style="height:36px;width:90px;text-align:right"></td>
                                    <td style="text-align:right"><input type="number" min="0" name="variants[<?= $i ?>][reorder_point]" value="<?= (int) $v['reorder_point'] ?>" class="mono" style="height:36px;width:90px;text-align:right"></td>
                                    <td style="text-align:center">
                                        <?php if ((int) $v['sold_count'] === 0): ?>
                                            <input type="checkbox" name="variants[<?= $i ?>][delete]" value="1" title="ติ๊กแล้วกดบันทึกเพื่อลบ">
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size:10.5px" title="เคยขายแล้ว ลบไม่ได้">ขายแล้ว</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div>
                        <button type="button" class="btn btn-outline" style="height:36px;padding:0 14px;font-size:12.5px" onclick="addVariantRow()">+ เพิ่มตัวเลือก</button>
                        <span class="text-muted" style="font-size:11.5px;margin-left:8px">เว้น SKU ว่างไว้ ระบบจะสร้างให้อัตโนมัติ</span>
                    </div>
                </div>

                <div class="flex gap-8">
                    <button class="btn btn-primary" type="submit" style="height:42px;padding:0 22px"><?= $isEdit ? 'บันทึกการแก้ไข' : 'เพิ่มสินค้า' ?></button>
                    <a href="<?= APP_BASE_PATH ?>/products?branch_id=<?= (int) $branchId ?>" class="btn btn-outline" style="height:42px;padding:0 22px;display:inline-flex;align-items:center">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
let variantIndex = <?= count($variants) ?>;
function addVariantRow() {
    const i = variantIndex++;
    const tr = document.createElement('tr');
    tr.style.borderTop = '1px solid var(--border)';
    tr.innerHTML =
        '<td style="padding:6px 10px"><input type="text" name="variants[' + i + '][size]" style="height:36px;width:80px"></td>' +
        '<td><input type="text" name="variants[' + i + '][color]" style="height:36px;width:110px"></td>' +
        '<td><input type="text" name="variants[' + i + '][sku]" class="mono" placeholder="อัตโนมัติ" style="height:36px;width:140px"></td>' +
        '<td><input type="text" name="variants[' + i + '][barcode]" class="mono" style="height:36px;width:140px"></td>' +
        '<td><input type="file" name="variant_image[' + i + ']" accept="image/jpeg,image/png,image/webp,image/gif" style="width:150px;font-size:11px"></td>' +
        '<td style="text-align:right"><input type="number" step="0.01" min="0" name="variants[' + i + '][price_override]" placeholder="ใช้ราคาหลัก" class="mono" style="height:36px;width:120px;text-align:right"></td>' +
        '<td style="text-align:right"><input type="number" name="variants[' + i + '][qty]" value="0" class="mono" style="height:36px;width:90px;text-align:right"></td>' +
        '<td style="text-align:right"><input type="number" min="0" name="variants[' + i + '][reorder_point]" value="10" class="mono" style="height:36px;width:90px;text-align:right"></td>' +
        '<td style="text-align:center"><span class="text-muted" style="font-size:10.5px">ใหม่</span></td>';
    document.getElementById('variant-rows').appendChild(tr);
}
if (variantIndex === 0) addVariantRow();
</script>
</body>
</html>
