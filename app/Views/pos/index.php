<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => 'POS ขายหน้าร้าน']); ?>
</head>
<body>
<div class="app-shell">
    <?php \App\Services\View::render('layout/sidebar', ['active' => 'pos', 'user' => $user]); ?>
    <div class="main-area">
        <div class="topbar">
            <div class="flex gap-10" style="align-items:center;padding:6px 14px;border:1px solid #CBD5E1;border-radius:8px;background:#F8FAFC">
                <span style="font-size:13px;font-weight:700"><?= \App\Services\View::e($merchant['name'] ?? '-') ?></span>
                <span style="width:4px;height:4px;border-radius:50%;background:#CBD5E1;display:inline-block"></span>
                <span class="text-muted" style="font-size:11px">สาขา</span>
                <span style="font-size:13px;font-weight:600"><?= \App\Services\View::e($branch['name'] ?? '-') ?></span>
                <span class="mono text-muted" style="font-size:11px;padding-left:8px;border-left:1px solid #E2E8F0">รหัสร้าน #<?= str_pad((string) ($merchant['id'] ?? 0), 4, '0', STR_PAD_LEFT) ?></span>
            </div>
            <div class="flex gap-8" style="align-items:center;font-size:12px;color:#059669;font-weight:500">
                <span style="width:7px;height:7px;border-radius:50%;background:#10B981;display:inline-block"></span>ออนไลน์
            </div>
            <div class="flex-1"></div>
            <div class="mono" style="font-weight:600;padding-left:8px;border-left:1px solid #E2E8F0"><?= date('H:i') ?></div>
        </div>

        <div class="content flex gap-12" style="align-items:flex-start">
            <div class="flex-1" style="min-width:0">
                <form method="get" class="flex gap-8" style="margin-bottom:14px">
                    <input type="text" name="q" value="<?= \App\Services\View::e($q) ?>" placeholder="ยิงบาร์โค้ด / ค้นหาชื่อสินค้า…" style="flex:1;height:48px">
                    <button class="btn btn-outline" type="submit">ค้นหา</button>
                </form>

                <div class="flex gap-8" style="flex-wrap:wrap;margin-bottom:16px">
                    <a class="chip<?= $category === '' ? ' active' : '' ?>" href="<?= APP_BASE_PATH ?>/pos">ทั้งหมด</a>
                    <?php foreach ($categories as $c): ?>
                        <a class="chip<?= $category === $c ? ' active' : '' ?>" href="<?= APP_BASE_PATH ?>/pos?category=<?= urlencode($c) ?>"><?= \App\Services\View::e($c) ?></a>
                    <?php endforeach; ?>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:12px">
                    <?php foreach ($products as $p): ?>
                        <div class="card" style="overflow:hidden;position:relative;display:flex;flex-direction:column">
                            <?php if ($p['markdown_percent'] > 0): ?>
                                <div style="position:absolute;top:8px;left:8px;background:#DC2626;color:#fff;font-size:10px;font-weight:600;padding:3px 7px;border-radius:5px">Markdown −<?= (int) $p['markdown_percent'] ?>%</div>
                            <?php endif; ?>
                            <?php if (!empty($p['image_path'])): ?>
                                <img src="<?= APP_BASE_PATH ?>/media/product/<?= rawurlencode($p['image_path']) ?>" alt="" style="height:96px;width:100%;object-fit:cover;display:block">
                            <?php else: ?>
                                <div style="height:96px;background:#E2E8F0;display:grid;place-items:center;color:#94A3B8;font-size:28px"><?= \App\Services\View::e($p['image_note'] ?? '') ?: '📦' ?></div>
                            <?php endif; ?>
                            <div style="padding:9px 10px;display:flex;flex-direction:column;flex:1">
                                <div style="font-size:12.5px;font-weight:600;line-height:1.35"><?= \App\Services\View::e($p['name']) ?></div>
                                <div class="mono" style="font-size:13px;color:#1E40AF;margin-top:4px"><?= \App\Services\View::money((float) $p['base_price']) ?></div>
                                <div class="text-muted" style="font-size:10.5px;margin-top:2px">
                                    <?= count($p['variants']) ?> ตัวเลือก
                                    <?php if ($trackStock): ?>
                                        · คงเหลือรวม <?= (int) $p['total_stock'] ?>
                                    <?php else: ?>
                                        · <span style="color:#059669;font-weight:600">ขายได้ไม่จำกัด</span>
                                    <?php endif; ?>
                                </div>
                                <div style="display:grid;gap:6px;margin-top:auto;padding-top:9px">
                                    <?php foreach ($p['variants'] as $v):
                                        $vlabel = trim(($v['color'] ?? '') . ' / ' . ($v['size'] ?? ''), ' /');
                                        $out = $trackStock && (int) $v['qty'] <= 0; ?>
                                        <button type="button" class="btn <?= $out ? 'btn-outline' : 'btn-primary' ?> variant-btn"
                                                data-variant="<?= $v['id'] ?>"
                                                style="height:34px;padding:0 10px;font-size:12px;width:100%;justify-content:space-between;gap:6px<?= $out ? ';opacity:.5' : '' ?>"
                                                <?= $out ? 'disabled title="หมดสต็อก"' : '' ?>>
                                            <span style="display:flex;align-items:center;gap:6px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">
                                                <span style="font-size:13px">🛒</span><?= $vlabel !== '' ? \App\Services\View::e($vlabel) : 'หยิบใส่ตะกร้า' ?>
                                            </span>
                                            <?php if ($trackStock): ?>
                                                <span style="font-size:10.5px;<?= $out ? '' : 'background:rgba(255,255,255,.22);' ?>padding:1px 7px;border-radius:9px;flex:none"><?= (int) $v['qty'] ?></span>
                                            <?php endif; ?>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$products): ?>
                        <div class="text-muted" style="display:grid;gap:10px;justify-items:start">
                            <span>ไม่พบสินค้า</span>
                            <?php if (in_array($user['role'], ['owner', 'manager'], true)): ?>
                                <a href="<?= APP_BASE_PATH ?>/products/new" class="btn btn-primary" style="height:36px;padding:0 16px;font-size:12.5px;display:inline-flex;align-items:center">+ เพิ่มรายการสินค้า</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" style="width:380px;flex:none;display:flex;flex-direction:column;max-height:calc(100vh - 100px)">
                <div style="padding:12px 16px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border)">
                    <span style="font-size:13px;font-weight:600">ตะกร้า · <span id="item-count"><?= $totals['item_count'] ?></span> รายการ</span>
                    <a href="#" id="clear-cart" style="font-size:11.5px;color:#1E40AF;font-weight:600">ล้างทั้งหมด</a>
                </div>
                <div id="cart-items" style="flex:1;overflow:auto;padding:10px 16px;display:flex;flex-direction:column;gap:9px">
                    <?php foreach ($cart['items'] as $variantId => $item): ?>
                        <div class="cart-line flex gap-8" data-variant="<?= $variantId ?>" style="padding:10px;border:1px solid var(--border);border-radius:9px">
                            <div class="flex-1">
                                <div style="font-size:12.5px;font-weight:600"><?= \App\Services\View::e($item['name']) ?></div>
                                <div class="text-muted" style="font-size:11px"><?= \App\Services\View::e($item['variant_label']) ?> · SKU <?= \App\Services\View::e($item['sku']) ?></div>
                            </div>
                            <div style="text-align:right">
                                <div class="mono" style="font-size:12.5px;font-weight:600"><?= \App\Services\View::money($item['price'] * $item['qty']) ?></div>
                                <div class="flex gap-8" style="margin-top:5px;align-items:center;justify-content:flex-end">
                                    <button type="button" class="qty-btn" data-delta="-1">−</button>
                                    <span class="mono qty-val" style="width:16px;text-align:center"><?= $item['qty'] ?></span>
                                    <button type="button" class="qty-btn" data-delta="1">+</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="padding:14px 16px;border-top:1px solid var(--border);background:var(--bg-lighter)">
                    <form id="coupon-form" class="flex gap-8" style="margin-bottom:10px">
                        <input type="text" name="code" placeholder="รหัสคูปอง" style="flex:1;height:36px">
                        <button class="btn btn-outline" type="submit" style="height:36px;padding:0 12px">ใช้</button>
                    </form>
                    <div class="flex" style="justify-content:space-between;font-size:12.5px;color:var(--text-soft);margin-bottom:6px"><span>ยอดรวม</span><span class="mono" id="t-subtotal"><?= \App\Services\View::money($totals['subtotal']) ?></span></div>
                    <div class="flex" style="justify-content:space-between;font-size:12.5px;color:#DC2626;margin-bottom:6px"><span id="t-discount-label"><?= \App\Services\View::e($totals['discount_label'] ?: 'ส่วนลด') ?></span><span class="mono" id="t-discount">−<?= \App\Services\View::money($totals['discount_total']) ?></span></div>
                    <div class="flex" style="justify-content:space-between;font-size:12.5px;color:var(--text-soft);margin-bottom:10px"><span>VAT 7% (รวมในราคา)</span><span class="mono" id="t-vat"><?= \App\Services\View::money($totals['vat_total']) ?></span></div>
                    <div class="flex" style="justify-content:space-between;align-items:baseline;padding-top:10px;border-top:1px solid var(--border)">
                        <span style="font-size:14px;font-weight:600">ยอดสุทธิ</span>
                        <span class="mono" id="t-grand" style="font-size:26px;font-weight:700;color:#1E40AF"><?= \App\Services\View::money($totals['grand_total']) ?></span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px">
                        <button type="button" id="hold-bill" class="btn btn-outline">พักบิล</button>
                        <button type="button" class="btn btn-dark">ใบกำกับภาษี</button>
                    </div>
                    <a href="<?= APP_BASE_PATH ?>/pos/pay" class="btn btn-primary" style="width:100%;margin-top:8px;height:54px;font-size:16px">ชำระเงิน <span class="mono" style="font-size:11.5px;background:rgba(255,255,255,.18);padding:3px 7px;border-radius:5px">F9</span></a>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
async function post(url, data) {
    const body = new URLSearchParams(data);
    const res = await fetch(BASE + url, { method: 'POST', body, headers: {'X-Requested-With':'fetch'} });
    return res.json();
}
function applyTotals(t) {
    document.getElementById('t-subtotal').textContent = '฿' + Number(t.subtotal).toLocaleString(undefined,{minimumFractionDigits:2});
    document.getElementById('t-discount').textContent = '−฿' + Number(t.discount_total).toLocaleString(undefined,{minimumFractionDigits:2});
    document.getElementById('t-discount-label').textContent = t.discount_label || 'ส่วนลด';
    document.getElementById('t-vat').textContent = '฿' + Number(t.vat_total).toLocaleString(undefined,{minimumFractionDigits:2});
    document.getElementById('t-grand').textContent = '฿' + Number(t.grand_total).toLocaleString(undefined,{minimumFractionDigits:2});
    document.getElementById('item-count').textContent = t.item_count;
}
document.querySelectorAll('.variant-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const r = await post('/pos/cart/add', { variant_id: btn.dataset.variant, qty: 1 });
        if (r.ok) { location.reload(); }
    });
});
document.querySelectorAll('.qty-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const line = btn.closest('.cart-line');
        const variant = line.dataset.variant;
        const val = line.querySelector('.qty-val');
        const newQty = parseInt(val.textContent) + parseInt(btn.dataset.delta);
        const r = await post('/pos/cart/update', { variant_id: variant, qty: newQty });
        if (r.ok) location.reload();
    });
});
document.getElementById('clear-cart').addEventListener('click', async (e) => {
    e.preventDefault();
    await post('/pos/cart/clear', {});
    location.reload();
});
document.getElementById('coupon-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const code = e.target.code.value;
    const r = await post('/pos/coupon', { code });
    if (r.ok) applyTotals(r.totals); else alert(r.error || 'ใช้คูปองไม่สำเร็จ');
});
document.getElementById('hold-bill').addEventListener('click', async () => {
    const r = await post('/pos/hold', {});
    if (r.ok) { alert('พักบิลแล้ว: ' + r.invoice_no); location.reload(); } else alert(r.error);
});
</script>
</body>
</html>
