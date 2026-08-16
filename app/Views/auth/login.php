<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => 'เข้าสู่ระบบ']); ?>
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="flex gap-8" style="align-items:center;margin-bottom:22px">
            <div class="sidebar-logo" style="width:40px;height:40px;border-radius:11px;background:linear-gradient(140deg,#1E40AF,#0891B2);color:#fff;display:grid;place-items:center;font:700 15px 'IBM Plex Mono',monospace">POS</div>
            <div style="font-size:16px;font-weight:600">เข้าสู่ระบบ POS/ERP</div>
        </div>
        <?php if (!empty($success)): ?>
            <div style="width:100%;padding:10px 14px;border-radius:9px;font-weight:500;margin-bottom:16px;text-align:center;background:var(--success-bg);color:#047857;border:1px solid var(--success-border)"><?= \App\Services\View::e($success) ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="btn-danger" style="width:100%;padding:10px 14px;border-radius:9px;font-weight:500;margin-bottom:16px;text-align:center"><?= \App\Services\View::e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= APP_BASE_PATH ?>/login" class="form-row">
            <label>Username<input name="username" required autofocus></label>
            <label>Password<input type="password" name="password" required></label>
            <button class="btn btn-primary" type="submit" style="width:100%;margin-top:6px">เข้าสู่ระบบ</button>
        </form>
        <p class="text-muted mt-16" style="font-size:11.5px">พนักงานหน้าร้านที่มี PIN สามารถสลับผู้ใช้ได้จากหน้าจอ POS โดยตรง</p>
        <p style="font-size:12px;text-align:center;margin-top:12px;color:#64748B">ยังไม่มีร้านค้า? <a href="<?= APP_BASE_PATH ?>/register" style="color:#1E40AF;font-weight:600">สมัครใช้งาน</a></p>
    </div>
</div>
</body>
</html>
