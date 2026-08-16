<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => 'Migrations']); ?>
</head>
<body>
<div class="app-shell">
    <?php \App\Services\View::render('layout/sidebar', ['active' => 'migrations', 'user' => $user]); ?>
    <div class="main-area">
        <div class="topbar"><div style="font-size:15px;font-weight:600">จัดการโครงสร้างฐานข้อมูล (Migrations)</div></div>
        <div class="content">
            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="card" style="padding:12px 16px;margin-bottom:16px;background:var(--success-bg);border-color:var(--success-border);color:#047857;font-size:13px"><?= \App\Services\View::e($_SESSION['flash']) ?></div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>

            <div class="flex gap-8" style="margin-bottom:16px">
                <form method="post" action="<?= APP_BASE_PATH ?>/admin/migrations/run"><button class="btn btn-primary" type="submit">รัน Migration ที่ค้างอยู่ (<?= count($pending) ?>)</button></form>
                <form method="post" action="<?= APP_BASE_PATH ?>/admin/migrations/rollback" onsubmit="return confirm('ย้อนกลับ batch ล่าสุด? การเปลี่ยนแปลงโครงสร้าง/ข้อมูลที่เกี่ยวข้องจะถูกยกเลิก')"><button class="btn btn-danger" type="submit">ย้อนกลับ Batch ล่าสุด</button></form>
            </div>

            <div class="card" style="overflow:hidden">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead><tr style="background:var(--bg-lighter);text-align:left">
                        <th style="padding:10px 14px">Migration</th><th>สถานะ</th><th>Batch</th><th>เวลาที่รัน</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($all as $name): $isApplied = in_array($name, $applied, true); $m = $meta[$name] ?? null; ?>
                        <tr style="border-top:1px solid var(--border)">
                            <td class="mono" style="padding:10px 14px"><?= \App\Services\View::e($name) ?></td>
                            <td><?= $isApplied ? '<span class="badge-ok" style="color:#047857;background:var(--success-bg)">Applied</span>' : '<span class="badge-warn" style="color:#B45309;background:var(--warning-bg)">Pending</span>' ?></td>
                            <td class="mono"><?= $m['batch'] ?? '-' ?></td>
                            <td class="text-muted"><?= $m['applied_at'] ?? '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
