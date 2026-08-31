<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => 'สมัครใช้งาน POS/ERP']); ?>
</head>
<body>
<div class="login-wrap">
    <div class="login-card" style="max-width:480px;width:100%">
        <div class="flex gap-8" style="align-items:center;margin-bottom:22px">
            <div class="sidebar-logo" style="width:40px;height:40px;border-radius:11px;background:linear-gradient(140deg,#1E40AF,#0891B2);color:#fff;display:grid;place-items:center;font:700 15px 'IBM Plex Mono',monospace">POS</div>
            <div>
                <div style="font-size:16px;font-weight:600">สมัครใช้งานระบบ POS/ERP</div>
                <div class="text-muted" style="font-size:12px">สร้างบัญชีร้านค้าใหม่</div>
            </div>
        </div>

        <?php if (!empty($errors['_general'])): ?>
            <div class="btn-danger" style="width:100%;padding:10px 14px;border-radius:9px;font-weight:500;margin-bottom:16px;text-align:center;box-sizing:border-box"><?= \App\Services\View::e($errors['_general']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= APP_BASE_PATH ?>/register" class="form-row">

            <label style="background:#F1F5F9;border:1px solid var(--border);border-radius:9px;padding:10px 12px;font-size:12.5px">รหัสเข้าร่วมกลุ่มสาขา (ถ้ามี)
                <input name="join_code" value="<?= \App\Services\View::e($old['join_code'] ?? '') ?>" placeholder="กรอกเมื่อได้รับรหัสจากร้านสำนักงานใหญ่" style="text-transform:uppercase" class="mono" oninput="toggleJoin()">
                <span class="text-muted" style="font-size:11px;display:block;margin-top:3px">มีรหัส = สมัครเข้าเป็น "สาขา" ของร้านที่มีอยู่แล้ว โดยคุณจะเป็นผู้จัดการสาขานั้น</span>
                <?php if (!empty($errors['join_code'])): ?><span class="text-muted" style="font-size:11.5px;color:#DC2626"><?= \App\Services\View::e($errors['join_code']) ?></span><?php endif; ?>
            </label>

            <div id="join-title" style="font-size:11px;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;margin-top:4px">ข้อมูลร้านค้า</div>

            <label id="shop-name-row">ชื่อร้านค้า <span style="color:#DC2626">*</span>
                <input name="shop_name" value="<?= \App\Services\View::e($old['shop_name'] ?? '') ?>" required autofocus placeholder="เช่น ร้านเสื้อผ้า ABC">
                <?php if (!empty($errors['shop_name'])): ?><span class="text-muted" style="font-size:11.5px;color:#DC2626"><?= \App\Services\View::e($errors['shop_name']) ?></span><?php endif; ?>
            </label>

            <label><span id="branch-name-label">ชื่อสาขาแรก</span>
                <input name="branch_name" value="<?= \App\Services\View::e($old['branch_name'] ?? '') ?>" placeholder="สาขาหลัก (ไม่กรอก = ใช้ค่านี้)">
                <?php if (!empty($errors['branch_name'])): ?><span class="text-muted" style="font-size:11.5px;color:#DC2626"><?= \App\Services\View::e($errors['branch_name']) ?></span><?php endif; ?>
            </label>

            <label>ที่อยู่สาขา
                <input name="branch_address" value="<?= \App\Services\View::e($old['branch_address'] ?? '') ?>" placeholder="ที่อยู่สาขาหลัก (ไม่บังคับ)">
            </label>

            <div style="font-size:11px;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;margin-top:8px">ข้อมูลเจ้าของร้าน</div>

            <label>ชื่อ-นามสกุล
                <input name="owner_name" value="<?= \App\Services\View::e($old['owner_name'] ?? '') ?>" placeholder="ชื่อเจ้าของ (ไม่กรอก = ใช้ Username)">
            </label>

            <label>เบอร์โทร / Email
                <input name="email" type="text" value="<?= \App\Services\View::e($old['email'] ?? '') ?>" placeholder="081-234-5678 หรือ owner@example.com">
            </label>

            <label>Username <span style="color:#DC2626">*</span>
                <input name="username" value="<?= \App\Services\View::e($old['username'] ?? '') ?>" required placeholder="ตัวอักษร a–z, 0–9, _ เท่านั้น">
                <?php if (!empty($errors['username'])): ?><span class="text-muted" style="font-size:11.5px;color:#DC2626"><?= \App\Services\View::e($errors['username']) ?></span><?php endif; ?>
            </label>

            <label>รหัสผ่าน <span style="color:#DC2626">*</span>
                <input type="password" name="password" required placeholder="อย่างน้อย 8 ตัวอักษร">
                <?php if (!empty($errors['password'])): ?><span class="text-muted" style="font-size:11.5px;color:#DC2626"><?= \App\Services\View::e($errors['password']) ?></span><?php endif; ?>
            </label>

            <button class="btn btn-primary" type="submit" style="width:100%;margin-top:8px">สมัครใช้งาน →</button>
        </form>

        <p class="text-muted" style="font-size:12px;margin-top:16px;text-align:center">
            มีบัญชีแล้ว? <a href="<?= APP_BASE_PATH ?>/login" style="color:#1E40AF;font-weight:600">เข้าสู่ระบบ</a>
        </p>
    </div>
</div>
<script>
function toggleJoin() {
    var joining = document.querySelector('[name=join_code]').value.trim() !== '';
    var shopRow = document.getElementById('shop-name-row');
    var shopInput = document.querySelector('[name=shop_name]');
    shopRow.style.display = joining ? 'none' : '';
    shopInput.required = !joining;
    document.getElementById('join-title').textContent = joining ? 'ข้อมูลสาขาของคุณ' : 'ข้อมูลร้านค้า';
    document.getElementById('branch-name-label').textContent = joining ? 'ชื่อสาขาของคุณ *' : 'ชื่อสาขาแรก';
}
toggleJoin();
</script>
</body>
</html>
