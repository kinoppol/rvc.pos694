<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => 'ตั้งค่าระบบ']); ?>
</head>
<body>
<div class="app-shell">
    <?php \App\Services\View::render('layout/sidebar', ['active' => 'admin_settings', 'user' => $user]); ?>
    <div class="main-area">
        <div class="topbar"><div style="font-size:15px;font-weight:600">ตั้งค่าระบบ</div></div>
        <div class="content">
            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="card" style="padding:12px 16px;margin-bottom:16px;background:var(--success-bg);border-color:var(--success-border);color:#047857;font-size:13px"><?= \App\Services\View::e($_SESSION['flash']) ?></div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>

            <div class="card" style="max-width:540px;padding:24px 28px">
                <div style="font-size:14px;font-weight:600;margin-bottom:18px">การสมัครร้านค้าใหม่</div>
                <form method="post" action="<?= APP_BASE_PATH ?>/admin/settings">
                    <label style="display:flex;align-items:flex-start;gap:14px;cursor:pointer;padding:16px;border:1px solid var(--border);border-radius:10px;background:var(--bg-lighter)">
                        <input type="checkbox" name="require_approval" value="1" <?= $requireApproval ? 'checked' : '' ?>
                            style="width:18px;height:18px;margin-top:2px;flex:none;accent-color:#1E40AF">
                        <div>
                            <div style="font-size:13.5px;font-weight:600">ต้องอนุมัติก่อนใช้งาน</div>
                            <div class="text-muted" style="font-size:12px;margin-top:3px;line-height:1.5">
                                เมื่อเปิดใช้ ร้านค้าที่สมัครใหม่จะมีสถานะ <strong>"รอการอนุมัติ"</strong> และไม่สามารถ login ได้จนกว่าผู้ดูแลระบบจะอนุมัติ<br>
                                เมื่อปิด ร้านค้าใหม่จะเข้าใช้งานได้ทันทีหลังสมัคร
                            </div>
                        </div>
                    </label>
                    <button class="btn btn-primary" type="submit" style="margin-top:16px">บันทึกการตั้งค่า</button>
                </form>

                <div style="margin-top:28px;padding-top:22px;border-top:1px solid var(--border)">
                    <div style="font-size:13px;font-weight:600;margin-bottom:10px">ลิงก์สมัครใช้งาน</div>
                    <div class="flex gap-8" style="align-items:center">
                        <input readonly value="<?= \App\Services\View::e((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . APP_BASE_PATH . '/register') ?>"
                            style="flex:1;height:36px;font-size:12px;color:#475569;background:var(--bg-lighter)" onclick="this.select()">
                        <button class="btn btn-outline" onclick="navigator.clipboard.writeText(this.previousElementSibling.value).then(()=>this.textContent='คัดลอกแล้ว!')" style="height:36px;padding:0 12px;font-size:12px" type="button">คัดลอก</button>
                    </div>
                    <p class="text-muted" style="font-size:11.5px;margin-top:6px">แชร์ลิงก์นี้ให้เจ้าของร้านที่ต้องการสมัครใช้งานระบบ</p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
