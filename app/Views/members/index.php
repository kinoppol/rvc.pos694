<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => 'สมาชิก']); ?>
</head>
<body>
<div class="app-shell">
    <?php \App\Services\View::render('layout/sidebar', ['active' => 'members', 'user' => $user]); ?>
    <div class="main-area">
        <div class="topbar"><div style="font-size:15px;font-weight:600">สมาชิก &amp; โปรโมชั่น</div></div>
        <div class="content">
            <form method="get" class="flex gap-8" style="margin-bottom:16px;max-width:420px">
                <input type="text" name="q" value="<?= \App\Services\View::e($q) ?>" placeholder="ค้นหาชื่อหรือเบอร์โทร…" style="flex:1;height:44px">
                <button class="btn btn-outline" type="submit">ค้นหา</button>
            </form>
            <div class="card" style="overflow:hidden">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead><tr style="background:var(--bg-lighter);text-align:left">
                        <th style="padding:10px 14px">ชื่อ</th><th>เบอร์โทร</th><th>ระดับ</th><th style="text-align:right">แต้ม</th><th style="text-align:right">ยอดซื้อสะสม</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($members as $m): ?>
                        <tr style="border-top:1px solid var(--border)">
                            <td style="padding:10px 14px;font-weight:600"><?= \App\Services\View::e($m['full_name']) ?></td>
                            <td><?= \App\Services\View::e($m['phone']) ?></td>
                            <td><?= \App\Services\View::e($m['tier_name'] ?? '-') ?></td>
                            <td class="mono" style="text-align:right"><?= number_format($m['points']) ?></td>
                            <td class="mono" style="text-align:right"><?= \App\Services\View::money((float) $m['total_spend']) ?></td>
                            <td style="text-align:right;padding-right:14px"><a href="/members/<?= $m['id'] ?>" class="btn btn-outline" style="height:32px;padding:0 12px;font-size:12px">ดูโปรไฟล์</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$members): ?><tr><td colspan="6" style="padding:20px;text-align:center" class="text-muted">ไม่พบสมาชิก</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>
