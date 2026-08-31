<?php $e = fn ($v) => \App\Services\View::e((string) $v); ?>
<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => 'สินค้า']); ?>
</head>
<body>
<div class="app-shell">
    <?php \App\Services\View::render('layout/sidebar', ['active' => 'products', 'user' => $user]); ?>
    <div class="main-area">
        <div class="topbar">
            <div style="font-size:15px;font-weight:600">จัดการสินค้า</div>
            <div class="flex-1"></div>
            <a href="<?= APP_BASE_PATH ?>/products/new?branch_id=<?= (int) $branchId ?>" class="btn btn-primary" style="height:34px;padding:0 14px;font-size:12.5px">+ เพิ่มสินค้า</a>
        </div>
        <div class="content">
            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="card" style="padding:12px 16px;margin-bottom:16px;background:var(--success-bg);border-color:var(--success-border);color:#047857;font-size:13px"><?= $e($_SESSION['flash']) ?></div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="card" style="padding:12px 16px;margin-bottom:16px;background:#FEE2E2;border-color:#FECACA;color:#991B1B;font-size:13px"><?= $e($error) ?></div>
            <?php endif; ?>

            <form method="get" class="flex gap-8" style="margin-bottom:16px;flex-wrap:wrap">
                <input type="text" name="q" value="<?= $e($q) ?>" placeholder="ค้นหาชื่อสินค้า…" style="flex:1;min-width:220px;height:40px">
                <select name="category_id" style="height:40px">
                    <option value="">ทุกหมวดหมู่</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= $e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (in_array($user['role'], ['owner', 'manager'], true) && count($branches) > 1): ?>
                    <select name="branch_id" style="height:40px" title="สาขาที่ใช้แสดงสต็อก">
                        <?php foreach ($branches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= (int) $branchId === (int) $b['id'] ? 'selected' : '' ?>>สต็อก: <?= $e($b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <button class="btn btn-outline" type="submit" style="height:40px">ค้นหา</button>
            </form>

            <div class="card" style="overflow:hidden">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead><tr style="background:var(--bg-lighter);text-align:left">
                        <th style="padding:10px 14px">สินค้า</th>
                        <th>หมวดหมู่</th>
                        <th style="text-align:right">ราคาขาย</th>
                        <th style="text-align:right">ทุน</th>
                        <th style="text-align:right">ตัวเลือก</th>
                        <th style="text-align:right">สต็อกสาขานี้</th>
                        <th style="text-align:right;padding-right:14px">การจัดการ</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($products as $p): ?>
                        <tr style="border-top:1px solid var(--border)">
                            <td style="padding:8px 14px;font-weight:600">
                                <div class="flex gap-10" style="align-items:center">
                                    <?php if (!empty($p['image_path'])): ?>
                                        <img src="<?= APP_BASE_PATH ?>/media/product/<?= rawurlencode($p['image_path']) ?>" alt="" style="width:38px;height:38px;object-fit:cover;border-radius:7px;border:1px solid var(--border);flex:none">
                                    <?php else: ?>
                                        <span style="width:38px;height:38px;border-radius:7px;background:var(--bg-lighter);display:grid;place-items:center;flex:none;font-size:17px"><?= $e($p['image_note'] ?? '') ?: '📦' ?></span>
                                    <?php endif; ?>
                                    <span><?= $e($p['name']) ?></span>
                                </div>
                            </td>
                            <td class="text-muted"><?= $e($p['category_name'] ?? '-') ?></td>
                            <td class="mono" style="text-align:right"><?= \App\Services\View::money((float) $p['base_price']) ?></td>
                            <td class="mono text-muted" style="text-align:right"><?= \App\Services\View::money((float) $p['cost_price']) ?></td>
                            <td class="mono" style="text-align:right"><?= (int) $p['variant_count'] ?></td>
                            <td class="mono" style="text-align:right;<?= (int) $p['stock_qty'] <= 0 ? 'color:#DC2626;font-weight:700' : '' ?>"><?= (int) $p['stock_qty'] ?></td>
                            <td style="text-align:right;padding-right:14px">
                                <div class="flex gap-8" style="justify-content:flex-end">
                                    <a href="<?= APP_BASE_PATH ?>/products/<?= $p['id'] ?>/edit?branch_id=<?= (int) $branchId ?>" class="btn btn-outline" style="height:32px;padding:0 12px;font-size:12px">แก้ไข</a>
                                    <form method="post" action="<?= APP_BASE_PATH ?>/products/<?= $p['id'] ?>/delete" onsubmit="return confirm('ลบสินค้า <?= $e(addslashes($p['name'])) ?>?')">
                                        <button class="btn btn-outline" type="submit" style="height:32px;padding:0 12px;font-size:12px;color:#DC2626;border-color:#FECACA">ลบ</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$products): ?>
                        <tr><td colspan="7" style="padding:28px;text-align:center" class="text-muted">ยังไม่มีสินค้า — กด “+ เพิ่มสินค้า” เพื่อเริ่มต้น</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
