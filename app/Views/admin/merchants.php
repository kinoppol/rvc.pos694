<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => 'จัดการร้านค้า']); ?>
</head>
<body>
<div class="app-shell">
    <?php \App\Services\View::render('layout/sidebar', ['active' => 'admin_merchants', 'user' => $user]); ?>
    <div class="main-area">
        <div class="topbar">
            <div style="font-size:15px;font-weight:600">จัดการร้านค้า</div>
            <div class="flex-1"></div>
            <?php if ($requireApproval): ?>
                <span style="font-size:11.5px;background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;padding:4px 10px;border-radius:6px;font-weight:600">โหมด: ต้องอนุมัติก่อนใช้งาน</span>
            <?php else: ?>
                <span style="font-size:11.5px;background:#ECFDF5;color:#065F46;border:1px solid #A7F3D0;padding:4px 10px;border-radius:6px;font-weight:600">โหมด: เปิดใช้งานทันที</span>
            <?php endif; ?>
            <a href="<?= APP_BASE_PATH ?>/admin/settings" class="btn btn-outline" style="height:34px;padding:0 14px;font-size:12.5px">ตั้งค่าระบบ</a>
        </div>
        <div class="content">
            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="card" style="padding:12px 16px;margin-bottom:16px;background:var(--success-bg);border-color:var(--success-border);color:#047857;font-size:13px"><?= \App\Services\View::e($_SESSION['flash']) ?></div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>

            <div class="card" style="overflow:hidden">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead><tr style="background:var(--bg-lighter);text-align:left">
                        <th style="padding:10px 14px">ร้านค้า</th>
                        <th>สถานะ</th>
                        <th>ผู้ใช้</th>
                        <th>วันที่สมัคร</th>
                        <th style="text-align:right;padding-right:14px">การจัดการ</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($merchants as $m): ?>
                        <tr style="border-top:1px solid var(--border)">
                            <td style="padding:10px 14px">
                                <div style="font-weight:600"><?= \App\Services\View::e($m['name']) ?></div>
                                <div class="text-muted mono" style="font-size:11px">รหัสร้าน: #<?= str_pad((string) $m['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                <?php if ($m['tax_id']): ?>
                                    <div class="text-muted mono" style="font-size:11px">Tax ID: <?= \App\Services\View::e($m['tax_id']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($m['status'] === 'pending'): ?>
                                    <span style="font-size:11.5px;font-weight:600;background:#FEF3C7;color:#92400E;padding:3px 9px;border-radius:5px">รอการอนุมัติ</span>
                                <?php elseif ($m['status'] === 'active'): ?>
                                    <span style="font-size:11.5px;font-weight:600;background:var(--success-bg);color:#047857;padding:3px 9px;border-radius:5px">ใช้งานได้</span>
                                <?php else: ?>
                                    <span style="font-size:11.5px;font-weight:600;background:#FEE2E2;color:#991B1B;padding:3px 9px;border-radius:5px">ถูกระงับ</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= (int) $m['user_count'] ?> คน</td>
                            <td class="text-muted"><?= date('d M Y', strtotime($m['created_at'])) ?></td>
                            <td style="text-align:right;padding-right:14px">
                                <div class="flex gap-8" style="justify-content:flex-end">
                                    <?php if ($m['status'] !== 'active'): ?>
                                        <form method="post" action="<?= APP_BASE_PATH ?>/admin/merchants/<?= $m['id'] ?>/approve">
                                            <button class="btn btn-outline" type="submit" style="height:32px;padding:0 12px;font-size:12px;color:#047857;border-color:#A7F3D0">อนุมัติ</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($m['status'] !== 'suspended'): ?>
                                        <form method="post" action="<?= APP_BASE_PATH ?>/admin/merchants/<?= $m['id'] ?>/suspend" onsubmit="return confirm('ระงับร้านค้า <?= \App\Services\View::e(addslashes($m['name'])) ?>?')">
                                            <button class="btn btn-outline" type="submit" style="height:32px;padding:0 12px;font-size:12px;color:#DC2626;border-color:#FECACA">ระงับ</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ((int) $m['user_count'] > 0): ?>
                                        <form method="post" action="<?= APP_BASE_PATH ?>/admin/merchants/<?= $m['id'] ?>/impersonate" onsubmit="return confirm('สวมสิทธิ์เข้าใช้งานร้าน <?= \App\Services\View::e(addslashes($m['name'])) ?>?')">
                                            <button class="btn btn-outline" type="submit" style="height:32px;padding:0 12px;font-size:12px;color:#1D4ED8;border-color:#BFDBFE">สวมสิทธิ์</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$merchants): ?>
                        <tr><td colspan="5" style="padding:28px;text-align:center" class="text-muted">ยังไม่มีร้านค้าที่สมัครผ่านระบบ</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
