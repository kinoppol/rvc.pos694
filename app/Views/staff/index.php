<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => 'พนักงาน']); ?>
</head>
<body>
<div class="app-shell">
    <?php \App\Services\View::render('layout/sidebar', ['active' => 'staff', 'user' => $user]); ?>
    <div class="main-area">
        <div class="topbar">
            <div style="font-size:15px;font-weight:600">จัดการข้อมูลพนักงาน</div>
            <div class="flex-1"></div>
            <a href="<?= APP_BASE_PATH ?>/staff/new" class="btn btn-primary" style="height:34px;padding:0 14px;font-size:12.5px">+ เพิ่มพนักงาน</a>
        </div>
        <div class="content">
            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="card" style="padding:12px 16px;margin-bottom:16px;background:var(--success-bg);border-color:var(--success-border);color:#047857;font-size:13px"><?= \App\Services\View::e($_SESSION['flash']) ?></div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
            <?php if (!empty($_SESSION['staff_error'])): ?>
                <div class="card" style="padding:12px 16px;margin-bottom:16px;background:#FEE2E2;border-color:#FECACA;color:#991B1B;font-size:13px"><?= \App\Services\View::e($_SESSION['staff_error']) ?></div>
                <?php unset($_SESSION['staff_error']); ?>
            <?php endif; ?>

            <div class="flex gap-8" style="margin-bottom:16px;flex-wrap:wrap">
                <?php foreach ([
                    ['พนักงานทั้งหมด', $counts['total']],
                    ['ใช้งานอยู่', $counts['active']],
                    ['ตั้ง PIN แล้ว', $counts['with_pin']],
                ] as [$label, $value]): ?>
                    <div class="card" style="padding:12px 18px;min-width:150px">
                        <div class="text-muted" style="font-size:11.5px"><?= $label ?></div>
                        <div class="mono" style="font-size:20px;font-weight:700"><?= (int) $value ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="get" class="flex gap-8" style="margin-bottom:16px;flex-wrap:wrap">
                <input type="text" name="q" value="<?= \App\Services\View::e($q) ?>" placeholder="ค้นหาชื่อ / ชื่อผู้ใช้ / อีเมล…" style="flex:1;min-width:220px;height:40px">
                <select name="branch_id" style="height:40px">
                    <option value="">ทุกสาขา</option>
                    <?php foreach ($branches as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $branchId === (int) $b['id'] ? 'selected' : '' ?>><?= \App\Services\View::e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" style="height:40px">
                    <option value="">ทุกสถานะ</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>ใช้งานอยู่</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน</option>
                </select>
                <button class="btn btn-outline" type="submit" style="height:40px">ค้นหา</button>
            </form>

            <div class="card" style="overflow:hidden">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead><tr style="background:var(--bg-lighter);text-align:left">
                        <th style="padding:10px 14px">ชื่อ-นามสกุล</th>
                        <th>ชื่อผู้ใช้</th>
                        <th>บทบาท</th>
                        <th>สาขา</th>
                        <th>PIN</th>
                        <th>สถานะ</th>
                        <th style="text-align:right;padding-right:14px">การจัดการ</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($staff as $s): ?>
                        <tr style="border-top:1px solid var(--border)">
                            <td style="padding:10px 14px">
                                <div style="font-weight:600"><?= \App\Services\View::e($s['full_name']) ?></div>
                                <?php if ($s['email']): ?><div class="text-muted" style="font-size:11px"><?= \App\Services\View::e($s['email']) ?></div><?php endif; ?>
                            </td>
                            <td class="mono"><?= \App\Services\View::e($s['username']) ?></td>
                            <td><?= \App\Services\View::e($roles[$s['role']] ?? $s['role']) ?></td>
                            <td class="text-muted"><?= \App\Services\View::e($s['branch_name'] ?? 'ทุกสาขา') ?></td>
                            <td class="text-muted"><?= $s['pin_hash'] ? 'ตั้งแล้ว' : '-' ?></td>
                            <td>
                                <?php if ((int) $s['active'] === 1): ?>
                                    <span style="font-size:11.5px;font-weight:600;background:var(--success-bg);color:#047857;padding:3px 9px;border-radius:5px">ใช้งานอยู่</span>
                                <?php else: ?>
                                    <span style="font-size:11.5px;font-weight:600;background:#FEE2E2;color:#991B1B;padding:3px 9px;border-radius:5px">ปิดใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;padding-right:14px">
                                <div class="flex gap-8" style="justify-content:flex-end">
                                    <a href="<?= APP_BASE_PATH ?>/staff/<?= $s['id'] ?>/edit" class="btn btn-outline" style="height:32px;padding:0 12px;font-size:12px">แก้ไข</a>
                                    <?php if ((int) $s['id'] !== (int) $user['id']): ?>
                                        <form method="post" action="<?= APP_BASE_PATH ?>/staff/<?= $s['id'] ?>/toggle" onsubmit="return confirm('<?= (int) $s['active'] === 1 ? 'ปิด' : 'เปิด' ?>การใช้งานบัญชีนี้?')">
                                            <button class="btn btn-outline" type="submit" style="height:32px;padding:0 12px;font-size:12px;<?= (int) $s['active'] === 1 ? 'color:#DC2626;border-color:#FECACA' : 'color:#047857;border-color:#A7F3D0' ?>"><?= (int) $s['active'] === 1 ? 'ปิดใช้งาน' : 'เปิดใช้งาน' ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$staff): ?>
                        <tr><td colspan="7" style="padding:28px;text-align:center" class="text-muted">ไม่พบพนักงานตามเงื่อนไขที่ค้นหา</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
