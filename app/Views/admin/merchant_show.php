<?php
$e = fn ($v) => \App\Services\View::e((string) $v);
$money = fn ($v) => \App\Services\View::money((float) $v);
$roleLabel = ['owner' => 'เจ้าของ', 'manager' => 'ผู้จัดการ', 'cashier' => 'แคชเชียร์', 'staff' => 'พนักงาน'];
$maxRevenue = max(1, ...array_values(array_map(fn ($a) => $a['revenue'], $activity)));
$maxCount = max(1, ...array_values(array_map(fn ($a) => max($a['bills'], $a['new_members'], $a['clock_ins']), $activity)));
$palette = ['#2563EB', '#0891B2', '#F59E0B', '#10B981', '#8B5CF6', '#EC4899', '#EF4444', '#14B8A6', '#F97316', '#6366F1'];

// SVG donut from [[label,value,color,display?], ...]
$donut = function (array $slices, int $size = 160) {
    $total = array_sum(array_column($slices, 'value'));
    if ($total <= 0) {
        return "<div class='text-muted' style='font-size:12px;padding:24px 0'>ยังไม่มีข้อมูล</div>";
    }
    $r = $size / 2;
    $sw = $size * 0.24;
    $rr = $r - $sw / 2;
    $acc = 0.0;
    $seg = '';
    foreach ($slices as $s) {
        $val = (float) $s['value'];
        if ($val <= 0) {
            continue;
        }
        $frac = $val / $total;
        if ($frac >= 0.9999) {
            $seg .= "<circle cx='$r' cy='$r' r='$rr' fill='none' stroke='{$s['color']}' stroke-width='$sw'/>";
            break;
        }
        $a0 = $acc * 2 * M_PI - M_PI / 2;
        $acc += $frac;
        $a1 = $acc * 2 * M_PI - M_PI / 2;
        $x0 = round($r + $rr * cos($a0), 2);
        $y0 = round($r + $rr * sin($a0), 2);
        $x1 = round($r + $rr * cos($a1), 2);
        $y1 = round($r + $rr * sin($a1), 2);
        $large = $frac > 0.5 ? 1 : 0;
        $seg .= "<path d='M $x0 $y0 A $rr $rr 0 $large 1 $x1 $y1' fill='none' stroke='{$s['color']}' stroke-width='$sw'/>";
    }
    return "<svg viewBox='0 0 $size $size' style='width:{$size}px;height:{$size}px;flex:none'>$seg</svg>";
};

$legend = function (array $slices) use ($e) {
    $h = '';
    foreach ($slices as $s) {
        if (($s['value'] ?? 0) <= 0) {
            continue;
        }
        $disp = $s['display'] ?? $s['value'];
        $h .= "<div style='display:flex;align-items:center;gap:8px;font-size:12px;margin-bottom:6px'>"
            . "<span style='width:11px;height:11px;border-radius:3px;background:{$s['color']};flex:none'></span>"
            . "<span style='flex:1'>" . $e($s['label']) . "</span>"
            . "<span class='text-muted mono'>" . $e($disp) . "</span></div>";
    }
    return $h;
};

// staff-by-role donut
$roleColors = ['owner' => '#2563EB', 'manager' => '#0891B2', 'cashier' => '#F59E0B', 'staff' => '#10B981'];
$roleCounts = array_fill_keys(array_keys($roleLabel), 0);
foreach ($staff as $s) {
    $roleCounts[$s['role']] = ($roleCounts[$s['role']] ?? 0) + 1;
}
$roleSlices = [];
foreach ($roleCounts as $role => $c) {
    $roleSlices[] = ['label' => $roleLabel[$role], 'value' => $c, 'color' => $roleColors[$role], 'display' => $c . ' คน'];
}

// revenue-share-by-branch donut
$branchSlices = [];
foreach ($branches as $i => $b) {
    $branchSlices[] = [
        'label'   => $b['name'],
        'value'   => round((float) $b['revenue'], 2),
        'color'   => $palette[$i % count($palette)],
        'display' => \App\Services\View::money((float) $b['revenue']),
    ];
}

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

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px">
                <div class="card" style="padding:20px;display:grid;gap:14px">
                    <div style="font-size:14px;font-weight:600">พนักงานแยกตามบทบาท</div>
                    <div class="flex" style="align-items:center;gap:18px;flex-wrap:wrap">
                        <?= $donut($roleSlices, 150) ?>
                        <div style="flex:1;min-width:150px"><?= $legend($roleSlices) ?></div>
                    </div>
                </div>
                <div class="card" style="padding:20px;display:grid;gap:14px">
                    <div style="font-size:14px;font-weight:600">สัดส่วนยอดขายตามสาขา</div>
                    <div class="flex" style="align-items:center;gap:18px;flex-wrap:wrap">
                        <?= $donut($branchSlices, 150) ?>
                        <div style="flex:1;min-width:150px"><?= $legend($branchSlices) ?: '<span class="text-muted" style="font-size:12px">ยังไม่มียอดขาย</span>' ?></div>
                    </div>
                </div>
            </div>

            <div class="card" style="padding:20px;display:grid;gap:14px">
                <div class="flex" style="align-items:baseline;gap:12px;flex-wrap:wrap">
                    <div style="font-size:14px;font-weight:600">ยอดขายรายวัน (14 วันล่าสุด)</div>
                    <span class="text-muted" style="font-size:11.5px">สูงสุด <?= $e($money($maxRevenue)) ?>/วัน</span>
                </div>
                <div style="display:flex;align-items:flex-end;gap:5px;height:140px">
                    <?php foreach ($activity as $day => $a):
                        $pct = $a['revenue'] > 0 ? max(4, round($a['revenue'] / $maxRevenue * 100)) : 0; ?>
                        <div style="flex:1;height:100%;display:flex;align-items:flex-end" title="<?= $e(date('d/m', strtotime($day))) ?> · <?= $e($money($a['revenue'])) ?> · <?= (int) $a['bills'] ?> บิล">
                            <div style="width:100%;height:<?= $pct ?>%;background:linear-gradient(180deg,#3B82F6,#1D4ED8);border-radius:3px 3px 0 0;min-height:<?= $a['revenue'] > 0 ? 2 : 0 ?>px"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex;gap:5px" class="text-muted mono">
                    <?php foreach ($activity as $day => $a): ?>
                        <div style="flex:1;text-align:center;font-size:9px"><?= $e(date('j/n', strtotime($day))) ?></div>
                    <?php endforeach; ?>
                </div>

                <div style="font-size:14px;font-weight:600;margin-top:8px">กิจกรรมรายวัน</div>
                <div class="flex" style="gap:14px;flex-wrap:wrap;font-size:11.5px">
                    <span class="flex" style="align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:3px;background:#2563EB"></span>บิล</span>
                    <span class="flex" style="align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:3px;background:#10B981"></span>สมาชิกใหม่</span>
                    <span class="flex" style="align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:3px;background:#F59E0B"></span>ลงเวลาเข้า</span>
                </div>
                <div style="display:flex;align-items:flex-end;gap:5px;height:110px">
                    <?php foreach ($activity as $day => $a): ?>
                        <div style="flex:1;height:100%;display:flex;align-items:flex-end;justify-content:center;gap:2px"
                             title="<?= $e(date('d/m', strtotime($day))) ?> · <?= (int) $a['bills'] ?> บิล · <?= (int) $a['new_members'] ?> สมาชิกใหม่ · <?= (int) $a['clock_ins'] ?> ลงเวลา">
                            <div style="width:5px;background:#2563EB;height:<?= $a['bills'] ? max(3, round($a['bills'] / $maxCount * 100)) : 0 ?>%"></div>
                            <div style="width:5px;background:#10B981;height:<?= $a['new_members'] ? max(3, round($a['new_members'] / $maxCount * 100)) : 0 ?>%"></div>
                            <div style="width:5px;background:#F59E0B;height:<?= $a['clock_ins'] ? max(3, round($a['clock_ins'] / $maxCount * 100)) : 0 ?>%"></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex;gap:5px" class="text-muted mono">
                    <?php foreach ($activity as $day => $a): ?>
                        <div style="flex:1;text-align:center;font-size:9px"><?= $e(date('j/n', strtotime($day))) ?></div>
                    <?php endforeach; ?>
                </div>
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
                <div style="font-size:14px;font-weight:600">กิจกรรมรายวัน — ตัวเลข</div>
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:12.5px;min-width:460px">
                        <thead><tr style="background:var(--bg-lighter);text-align:left">
                            <th style="padding:8px 10px">วันที่</th>
                            <th style="text-align:right">บิล</th>
                            <th style="text-align:right">ยอดขาย</th>
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
