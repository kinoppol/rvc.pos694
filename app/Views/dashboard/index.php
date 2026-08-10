<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => 'ภาพรวมกิจการ']); ?>
</head>
<body class="dark-shell">
<div class="app-shell dark-shell">
    <?php \App\Services\View::render('layout/sidebar', ['active' => 'dashboard', 'user' => $user]); ?>
    <div class="main-area dark-shell">
        <div class="content">
            <div class="flex gap-12" style="align-items:center;margin-bottom:16px">
                <div>
                    <div style="font-size:19px;font-weight:600;color:#fff">ภาพรวมกิจการ · <?= count($branches) ?> สาขา</div>
                    <div class="text-muted" style="font-size:12px;margin-top:2px">อัปเดตสด · <?= date('d/m/Y H:i') ?></div>
                </div>
                <div class="flex-1"></div>
                <div style="padding:8px 14px;border:1px solid var(--dark-border);border-radius:9px;font-size:12.5px;color:#E2E8F0">ส่งออกรายงาน</div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px">
                <div class="dark-card">
                    <div class="text-muted" style="font-size:11.5px">ยอดขายวันนี้ (รวมทุกสาขา)</div>
                    <div class="mono" style="font-size:25px;font-weight:700;color:#fff;margin:6px 0 4px"><?= \App\Services\View::money($todaySales) ?></div>
                    <div style="font-size:11.5px;color:<?= $changePct >= 0 ? '#34D399' : '#F87171' ?>"><?= $changePct >= 0 ? '▲' : '▼' ?> <?= abs($changePct) ?>% เทียบวานนี้</div>
                </div>
                <div class="dark-card">
                    <div class="text-muted" style="font-size:11.5px">บิลทั้งหมด / บิลเฉลี่ย</div>
                    <div class="mono" style="font-size:25px;font-weight:700;color:#fff;margin:6px 0 4px"><?= number_format($billCount) ?></div>
                    <div class="text-muted" style="font-size:11.5px">เฉลี่ย <?= \App\Services\View::money((float) $avgBill) ?> / บิล</div>
                </div>
                <div class="dark-card">
                    <div class="text-muted" style="font-size:11.5px">VAT ที่ต้องนำส่ง (เดือนนี้)</div>
                    <div class="mono" style="font-size:25px;font-weight:700;color:#fff;margin:6px 0 4px"><?= \App\Services\View::money($vatThisMonth) ?></div>
                </div>
                <div class="dark-card" style="border-color:#451A03">
                    <div style="font-size:11.5px;color:#FBBF24">แจ้งเตือนที่ต้องจัดการ</div>
                    <div class="mono" style="font-size:25px;font-weight:700;color:#fff;margin:6px 0 4px"><?= $lowStockCount ?></div>
                    <div class="text-muted" style="font-size:11.5px">สต็อกต่ำ <?= $lowStockCount ?> รายการ</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1.45fr 1fr;gap:16px">
                <div class="dark-card" style="padding:18px">
                    <div style="font-size:14px;font-weight:600;color:#fff;margin-bottom:14px">ผลประกอบการรายสาขา</div>
                    <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr 84px;gap:10px;font-size:11px;color:var(--dark-muted);padding-bottom:9px;border-bottom:1px solid var(--dark-border)">
                        <div>สาขา</div><div style="text-align:right">ยอดขาย</div><div style="text-align:right">บิล</div><div style="text-align:right">มูลค่าสต็อก</div><div style="text-align:right">สถานะ</div>
                    </div>
                    <?php foreach ($branches as $b):
                        $status = $b['low_stock_count'] > 0 ? ['สต็อกต่ำ', 'badge-warn'] : ['ปกติ', 'badge-ok'];
                    ?>
                    <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr 1fr 84px;gap:10px;align-items:center;padding:11px 0;border-bottom:1px solid #16202F;font-size:12.5px">
                        <div style="color:#fff;font-weight:500"><?= \App\Services\View::e($b['name']) ?></div>
                        <div class="mono" style="text-align:right;color:#fff"><?= \App\Services\View::money((float) $b['today_sales']) ?></div>
                        <div class="mono" style="text-align:right;color:var(--dark-muted)"><?= (int) $b['today_bills'] ?></div>
                        <div class="mono" style="text-align:right;color:var(--dark-muted)"><?= \App\Services\View::money((float) $b['stock_value']) ?></div>
                        <div style="text-align:right"><span class="<?= $status[1] ?>"><?= $status[0] ?></span></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex" style="flex-direction:column;gap:16px">
                    <div class="dark-card" style="padding:18px">
                        <div style="font-size:14px;font-weight:600;color:#fff;margin-bottom:12px">สินค้าขายดี</div>
                        <div class="flex" style="flex-direction:column;gap:11px">
                            <?php foreach ($topProducts as $tp): ?>
                            <div>
                                <div class="flex" style="justify-content:space-between;font-size:12.5px;color:#E2E8F0;margin-bottom:5px">
                                    <span><?= \App\Services\View::e($tp['name']) ?></span><span class="mono"><?= (int) $tp['qty'] ?></span>
                                </div>
                                <div style="height:6px;border-radius:3px;background:var(--dark-border)"><div style="width:<?= round($tp['qty'] / $maxQty * 100) ?>%;height:100%;border-radius:3px;background:#1E40AF"></div></div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (!$topProducts): ?><p class="text-muted" style="font-size:12px">ยังไม่มีข้อมูลการขาย</p><?php endif; ?>
                        </div>
                    </div>
                    <div class="dark-card" style="padding:18px;flex:1">
                        <div style="font-size:14px;font-weight:600;color:#fff;margin-bottom:12px">แจ้งเตือนคลังสินค้า</div>
                        <div class="flex" style="flex-direction:column;gap:9px">
                            <?php foreach ($lowStockAlerts as $a): ?>
                            <div class="flex gap-8" style="padding:11px;border-radius:9px;background:#3F1113;border:1px solid #7F1D1D">
                                <div>▼</div>
                                <div>
                                    <div style="font-size:12.5px;color:#fff"><?= \App\Services\View::e($a['name']) ?> — <?= \App\Services\View::e($a['branch_name']) ?> เหลือ <?= (int) $a['quantity'] ?></div>
                                    <div style="font-size:11px;color:#FCA5A5;margin-top:2px">ต่ำกว่าจุดสั่งซื้อ (<?= (int) $a['reorder_point'] ?>)</div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (!$lowStockAlerts): ?><p class="text-muted" style="font-size:12px">ไม่มีรายการสต็อกต่ำ</p><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
