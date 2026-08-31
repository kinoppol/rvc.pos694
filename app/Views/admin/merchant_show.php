<?php
$e = fn ($v) => \App\Services\View::e((string) $v);
$money = fn ($v) => \App\Services\View::money((float) $v);
$roleLabel = ['owner' => 'เจ้าของ', 'manager' => 'ผู้จัดการ', 'cashier' => 'แคชเชียร์', 'staff' => 'พนักงาน'];
$maxRevenue = max(1, ...array_values(array_map(fn ($a) => $a['revenue'], $activity)));
$statusBadge = [
    'pending'   => ['รอการอนุมัติ', '#FEF3C7', '#92400E'],
    'active'    => ['ใช้งานได้', '#ECFDF5', '#047857'],
    'suspended' => ['ถูกระงับ', '#FEE2E2', '#991B1B'],
][$merchant['status']] ?? ['-', '#F1F5F9', '#475569'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => 'ภาพรวมร้าน: ' . $merchant['name']]); ?>
</head>
<body>
<div class="app-shell">
    <?php \App\Services\View::render('layout/sidebar', ['active' => 'admin_merchants', 'user' => $user]); ?>
    <div class="main-area">
        <div class="topbar">
            <a href="<?= APP_BASE_PATH ?>/admin/merchants" class="btn btn-outline" style="height:34px;padding:0 14px;font-size:12.5px">← ร้านค้าทั้งหมด</a>
            <div style="font-size:15px;font-weight:600"><?= $e($merchant['name']) ?></div>
            <span class="mono text-muted" style="font-size:12px">#<?= str_pad((string) $merchant['id'], 4, '0', STR_PAD_LEFT) ?></span>
            <span style="font-size:11.5px;font-weight:600;background:<?= $statusBadge[1] ?>;color:<?= $statusBadge[2] ?>;padding:3px 9px;border-radius:5px"><?= $e($statusBadge[0]) ?></span>
            <div class="flex-1"></div>
            <form method="post" action="<?= APP_BASE_PATH ?>/admin/merchants/<?= $merchant['id'] ?>/impersonate"
                  onsubmit="return confirm('สวมสิทธิ์เข้าใช้งานร้าน <?= $e(addslashes($merchant['name'])) ?>?')">
                <button class="btn btn-outline" type="submit" style="height:34px;padding:0 14px;font-size:12.5px;color:#1D4ED8;border-color:#BFDBFE">สวมสิทธิ์</button>
            </form>
        </div>
        <div class="content" style="display:grid;gap:18px;max-width:960px">

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px">
                <?php foreach ([
                    ['สาขา', $merchant['branch_count']],
                    ['พนักงาน', $merchant['active_user_count'] . ' / ' . $merchant['user_count']],
                    ['สินค้า', $merchant['product_count']],
                    ['ตัวเลือกสินค้า', $merchant['variant_count']],
                    ['สมาชิก', $merchant['member_count']],
                ] as [$label, $val]): ?>
                    <div class="card" style="padding:14px 16px">
                        <div class="text-muted" style="font-size:11.5px"><?= $e($label) ?></div>
                        <div class="mono" style="font-size:20px;font-weight:700;margin-top:3px"><?= $e($val) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card" style="padding:20px;display:grid;gap:12px">
                <div style="font-size:14px;font-weight:600">สาขา (<?= count($branches) ?>)</div>
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:12.5px;min-width:560px">
                        <thead><tr style="background:var(--bg-lighter);text-align:left">
                            <th style="padding:8px 10px">ชื่อสาขา</th><th>ที่อยู่</th>
                            <th style="text-align:right">พนักงาน</th>
                            <th style="text-align:right">บิลที่ขายแล้ว</th>
                            <th style="text-align:right;padding-right:10px">ยอดขายสะสม</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($branches as $b): ?>
                            <tr style="border-top:1px solid var(--border)">
                                <td style="padding:8px 10px;font-weight:600"><?= $e($b['name']) ?></td>
                                <td class="text-muted"><?= $e($b['address'] ?: '-') ?></td>
                                <td class="mono" style="text-align:right"><?= (int) $b['user_count'] ?></td>
                                <td class="mono" style="text-align:right"><?= (int) $b['sale_count'] ?></td>
                                <td class="mono" style="text-align:right;padding-right:10px"><?= $money($b['revenue']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$branches): ?>
                            <tr><td colspan="5" class="text-muted" style="padding:18px;text-align:center">ยังไม่มีสาขา</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card" style="padding:20px;display:grid;gap:12px">
                <div style="font-size:14px;font-weight:600">พนักงาน (<?= count($staff) ?>)</div>
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:12.5px;min-width:480px">
                        <thead><tr style="background:var(--bg-lighter);text-align:left">
                            <th style="padding:8px 10px">ชื่อ</th><th>บทบาท</th><th>สาขา</th><th>สถานะ</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($staff as $s): ?>
                            <tr style="border-top:1px solid var(--border)">
                                <td style="padding:8px 10px">
                                    <span style="font-weight:600"><?= $e($s['full_name']) ?></span>
                                    <span class="text-muted mono" style="font-size:11px">@<?= $e($s['username']) ?></span>
                                </td>
                                <td><?= $e($roleLabel[$s['role']] ?? $s['role']) ?></td>
                                <td class="text-muted"><?= $e($s['branch_name'] ?: '-') ?></td>
                                <td><?= ((int) $s['active'] === 1)
                                        ? '<span style="color:#047857;font-weight:600">ใช้งาน</span>'
                                        : '<span class="text-muted">ปิดใช้งาน</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$staff): ?>
                            <tr><td colspan="4" class="text-muted" style="padding:18px;text-align:center">ยังไม่มีพนักงาน</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card" style="padding:20px;display:grid;gap:12px">
                <div style="font-size:14px;font-weight:600">กิจกรรมรายวัน (14 วันล่าสุด)</div>
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:12.5px;min-width:620px">
                        <thead><tr style="background:var(--bg-lighter);text-align:left">
                            <th style="padding:8px 10px">วันที่</th>
                            <th style="text-align:right">บิล</th>
                            <th style="text-align:right">ยอดขาย</th>
                            <th style="width:34%">&nbsp;</th>
                            <th style="text-align:right">สมาชิกใหม่</th>
                            <th style="text-align:right;padding-right:10px">ลงเวลาเข้า</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach (array_reverse($activity, true) as $day => $a): ?>
                            <tr style="border-top:1px solid var(--border)">
                                <td class="mono" style="padding:8px 10px"><?= $e(date('d/m', strtotime($day))) ?>
                                    <span class="text-muted" style="font-size:10.5px"><?= $e(['อา','จ','อ','พ','พฤ','ศ','ส'][date('w', strtotime($day))]) ?></span>
                                </td>
                                <td class="mono" style="text-align:right"><?= $a['bills'] ?: '<span class="text-muted">–</span>' ?></td>
                                <td class="mono" style="text-align:right"><?= $a['revenue'] > 0 ? $money($a['revenue']) : '<span class="text-muted">–</span>' ?></td>
                                <td style="padding:0 10px">
                                    <div style="height:8px;border-radius:4px;background:linear-gradient(90deg,var(--brand),var(--brand-2));width:<?= (int) round($a['revenue'] / $maxRevenue * 100) ?>%;min-width:<?= $a['revenue'] > 0 ? 3 : 0 ?>px"></div>
                                </td>
                                <td class="mono" style="text-align:right"><?= $a['new_members'] ?: '<span class="text-muted">–</span>' ?></td>
                                <td class="mono" style="text-align:right;padding-right:10px"><?= $a['clock_ins'] ?: '<span class="text-muted">–</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>
</body>
</html>
