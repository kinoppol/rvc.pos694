<?php
/** @var array|null $staff */
$isEdit = $staff !== null;
$val = function (string $key, $default = '') use ($old, $staff) {
    if (array_key_exists($key, $old)) return $old[$key];
    if ($staff !== null && array_key_exists($key, $staff)) return $staff[$key];
    return $default;
};
$action = $isEdit ? APP_BASE_PATH . '/staff/' . $staff['id'] : APP_BASE_PATH . '/staff';
?>
<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => $isEdit ? 'แก้ไขพนักงาน' : 'เพิ่มพนักงาน']); ?>
</head>
<body>
<div class="app-shell">
    <?php \App\Services\View::render('layout/sidebar', ['active' => 'staff', 'user' => $user]); ?>
    <div class="main-area">
        <div class="topbar">
            <div style="font-size:15px;font-weight:600"><?= $isEdit ? 'แก้ไขข้อมูลพนักงาน' : 'เพิ่มพนักงานใหม่' ?></div>
            <div class="flex-1"></div>
            <a href="<?= APP_BASE_PATH ?>/staff" class="btn btn-outline" style="height:34px;padding:0 14px;font-size:12.5px">ย้อนกลับ</a>
        </div>
        <div class="content">
            <?php if ($error): ?>
                <div class="card" style="padding:12px 16px;margin-bottom:16px;background:#FEE2E2;border-color:#FECACA;color:#991B1B;font-size:13px;max-width:640px"><?= \App\Services\View::e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= $action ?>" class="card" style="padding:20px;max-width:640px;display:grid;gap:14px">
                <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">ชื่อ-นามสกุล *
                    <input type="text" name="full_name" required value="<?= \App\Services\View::e((string) $val('full_name')) ?>" style="height:42px;font-weight:400">
                </label>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">ชื่อผู้ใช้ (username) *
                        <input type="text" name="username" required autocomplete="off" value="<?= \App\Services\View::e((string) $val('username')) ?>" style="height:42px;font-weight:400">
                    </label>
                    <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">อีเมล
                        <input type="email" name="email" value="<?= \App\Services\View::e((string) $val('email')) ?>" style="height:42px;font-weight:400">
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">บทบาท *
                        <select name="role" style="height:42px;font-weight:400">
                            <?php foreach ($roles as $key => $label): ?>
                                <option value="<?= $key ?>" <?= (string) $val('role', 'staff') === $key ? 'selected' : '' ?>><?= \App\Services\View::e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">สาขา
                        <select name="branch_id" style="height:42px;font-weight:400" <?= $user['role'] !== 'owner' ? 'disabled' : '' ?>>
                            <option value="">— ทุกสาขา —</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= (string) $val('branch_id') === (string) $b['id'] ? 'selected' : '' ?>><?= \App\Services\View::e($b['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">
                        รหัสผ่าน <?= $isEdit ? '(เว้นว่างหากไม่เปลี่ยน)' : '*' ?>
                        <input type="password" name="password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?> style="height:42px;font-weight:400">
                    </label>
                    <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">
                        PIN สำหรับ POS (4-6 หลัก)
                        <input type="text" name="pin" inputmode="numeric" pattern="\d{4,6}" autocomplete="off" placeholder="<?= $isEdit && $staff['pin_hash'] ? 'ตั้งไว้แล้ว — กรอกเพื่อเปลี่ยน' : 'ไม่บังคับ' ?>" style="height:42px;font-weight:400">
                    </label>
                </div>

                <label class="flex gap-8" style="align-items:center;font-size:12.5px;font-weight:600">
                    <input type="checkbox" name="active" value="1" <?= (int) $val('active', 1) === 1 ? 'checked' : '' ?>> เปิดใช้งานบัญชีนี้
                </label>
                <?php if ($isEdit && $staff['pin_hash']): ?>
                    <label class="flex gap-8" style="align-items:center;font-size:12.5px;font-weight:600">
                        <input type="checkbox" name="clear_pin" value="1"> ล้าง PIN ที่ตั้งไว้
                    </label>
                <?php endif; ?>

                <div class="flex gap-8" style="margin-top:4px">
                    <button class="btn btn-primary" type="submit" style="height:42px;padding:0 22px"><?= $isEdit ? 'บันทึกการแก้ไข' : 'เพิ่มพนักงาน' ?></button>
                    <a href="<?= APP_BASE_PATH ?>/staff" class="btn btn-outline" style="height:42px;padding:0 22px;display:inline-flex;align-items:center">ยกเลิก</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
