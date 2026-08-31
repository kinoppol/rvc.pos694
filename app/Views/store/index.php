<?php
$e = fn ($v) => \App\Services\View::e((string) $v);
/** ฟอร์มสาขา ใช้ร่วมกันทั้งเพิ่มใหม่และแก้ไข */
$branchFields = function (array $b = []) use ($e) {
    $uid = $b['id'] ?? 'new';
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">ชื่อสาขา *
            <input type="text" name="name" required value="<?= $e($b['name'] ?? '') ?>" style="height:40px;font-weight:400">
        </label>
        <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">ที่อยู่
            <input type="text" name="address" value="<?= $e($b['address'] ?? '') ?>" style="height:40px;font-weight:400">
        </label>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
        <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">ละติจูด (lat)
            <input type="text" inputmode="decimal" name="lat" id="lat-<?= $uid ?>" value="<?= $e($b['lat'] ?? '') ?>" placeholder="13.7563" style="height:40px;font-weight:400" class="mono">
        </label>
        <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">ลองจิจูด (lng)
            <input type="text" inputmode="decimal" name="lng" id="lng-<?= $uid ?>" value="<?= $e($b['lng'] ?? '') ?>" placeholder="100.5018" style="height:40px;font-weight:400" class="mono">
        </label>
        <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">รัศมี geofence (เมตร)
            <input type="number" name="geofence_radius_m" min="10" max="5000" value="<?= $e($b['geofence_radius_m'] ?? 50) ?>" style="height:40px;font-weight:400" class="mono">
        </label>
    </div>
    <div class="flex gap-8" style="flex-wrap:wrap;align-items:center">
        <button type="button" class="btn btn-outline" style="height:36px;padding:0 14px;font-size:12.5px" onclick="fillLocation('<?= $uid ?>', this)">📍 ใช้ตำแหน่งปัจจุบัน</button>
        <?php if (!empty($b['lat']) && !empty($b['lng'])): ?>
            <a class="btn btn-outline" style="height:36px;padding:0 14px;font-size:12.5px;display:inline-flex;align-items:center" target="_blank" rel="noopener"
               href="https://www.google.com/maps?q=<?= $e($b['lat']) ?>,<?= $e($b['lng']) ?>">ดูบนแผนที่</a>
        <?php endif; ?>
        <span class="text-muted" style="font-size:11.5px">พิกัดนี้ใช้ตรวจระยะตอนพนักงานลงเวลาเข้า-ออก</span>
    </div>
    <?php
};
?>
<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => 'ข้อมูลร้านค้า']); ?>
</head>
<body>
<div class="app-shell">
    <?php \App\Services\View::render('layout/sidebar', ['active' => 'store', 'user' => $user]); ?>
    <div class="main-area">
        <div class="topbar"><div style="font-size:15px;font-weight:600">ข้อมูลร้านค้า &amp; สาขา</div></div>
        <div class="content" style="display:grid;gap:18px;max-width:860px">

            <?php if (!empty($_SESSION['flash'])): ?>
                <div class="card" style="padding:12px 16px;background:var(--success-bg);border-color:var(--success-border);color:#047857;font-size:13px"><?= $e($_SESSION['flash']) ?></div>
                <?php unset($_SESSION['flash']); ?>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="card" style="padding:12px 16px;background:#FEE2E2;border-color:#FECACA;color:#991B1B;font-size:13px"><?= $e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="<?= APP_BASE_PATH ?>/store" class="card" style="padding:20px;display:grid;gap:14px">
                <div style="font-size:14px;font-weight:600">ข้อมูลร้านค้า</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">ชื่อร้านค้า *
                        <input type="text" name="name" required value="<?= $e($merchant['name'] ?? '') ?>" style="height:42px;font-weight:400">
                    </label>
                    <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">เลขประจำตัวผู้เสียภาษี (13 หลัก)
                        <input type="text" name="tax_id" inputmode="numeric" value="<?= $e($merchant['tax_id'] ?? '') ?>" class="mono" style="height:42px;font-weight:400">
                    </label>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">เบอร์โทรร้าน
                        <input type="text" name="phone" value="<?= $e($merchant['phone'] ?? '') ?>" style="height:42px;font-weight:400">
                    </label>
                    <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">อีเมล
                        <input type="email" name="email" value="<?= $e($merchant['email'] ?? '') ?>" style="height:42px;font-weight:400">
                    </label>
                </div>
                <label style="display:grid;gap:5px;font-size:12.5px;font-weight:600">ที่อยู่ร้าน
                    <input type="text" name="address" value="<?= $e($merchant['address'] ?? '') ?>" style="height:42px;font-weight:400">
                </label>
                <div><button class="btn btn-primary" type="submit" style="height:42px;padding:0 22px">บันทึกข้อมูลร้านค้า</button></div>
            </form>

            <div class="card" style="padding:20px;display:grid;gap:12px">
                <div style="font-size:14px;font-weight:600">รหัสเข้าร่วมกลุ่มสาขา</div>
                <div class="text-muted" style="font-size:12px;line-height:1.6">
                    ส่งรหัสนี้ให้ผู้จัดการของอีกร้านหนึ่งเพื่อให้เขาไปกรอกตอน<a href="<?= APP_BASE_PATH ?>/register" target="_blank" rel="noopener" style="color:#1E40AF;font-weight:600">สมัครใช้งาน</a>
                    ร้านของเขาจะกลายเป็น "สาขา" ของร้านนี้ และเขาจะเป็นผู้จัดการของสาขานั้น
                </div>
                <?php if (!empty($merchant['join_code'])): ?>
                    <div class="flex gap-8" style="align-items:center;flex-wrap:wrap">
                        <span class="mono" style="font-size:20px;font-weight:700;letter-spacing:2px;background:#F1F5F9;border:1px solid var(--border);border-radius:8px;padding:8px 16px"><?= $e($merchant['join_code']) ?></span>
                        <form method="post" action="<?= APP_BASE_PATH ?>/store/join-code"
                              onsubmit="return confirm('สร้างรหัสใหม่? รหัสเดิมจะใช้ไม่ได้อีก')">
                            <button class="btn btn-outline" type="submit" style="height:36px;padding:0 14px;font-size:12.5px">สร้างรหัสใหม่</button>
                        </form>
                        <form method="post" action="<?= APP_BASE_PATH ?>/store/join-code/disable"
                              onsubmit="return confirm('ปิดรหัสเข้าร่วม? จะไม่มีใครเข้าร่วมด้วยรหัสนี้ได้อีก')">
                            <button class="btn btn-outline" type="submit" style="height:36px;padding:0 14px;font-size:12.5px;color:#DC2626;border-color:#FECACA">ปิดรหัส</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div>
                        <form method="post" action="<?= APP_BASE_PATH ?>/store/join-code">
                            <button class="btn btn-primary" type="submit" style="height:38px;padding:0 18px;font-size:12.5px">สร้างรหัสเข้าร่วม</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <div class="card" style="padding:20px;display:grid;gap:14px">
                <div style="font-size:14px;font-weight:600">สาขา (<?= count($branches) ?>)</div>
                <?php foreach ($branches as $b): ?>
                    <details style="border:1px solid var(--border);border-radius:8px;padding:12px 14px">
                        <summary style="cursor:pointer;font-size:13px;font-weight:600;display:flex;gap:10px;align-items:center">
                            <span><?= $e($b['name']) ?></span>
                            <?php if ($b['lat'] !== null && $b['lng'] !== null): ?>
                                <span class="mono text-muted" style="font-size:11px"><?= $e($b['lat']) ?>, <?= $e($b['lng']) ?> · <?= (int) $b['geofence_radius_m'] ?> ม.</span>
                            <?php else: ?>
                                <span style="font-size:11px;font-weight:600;background:#FEF3C7;color:#92400E;padding:2px 8px;border-radius:5px">ยังไม่ได้ตั้งพิกัด</span>
                            <?php endif; ?>
                            <span class="text-muted" style="font-size:11px;margin-left:auto"><?= (int) $b['user_count'] ?> พนักงาน · <?= (int) $b['sale_count'] ?> บิล</span>
                        </summary>
                        <form method="post" action="<?= APP_BASE_PATH ?>/store/branches/<?= $b['id'] ?>" style="display:grid;gap:12px;margin-top:12px">
                            <?php $branchFields($b); ?>
                            <div class="flex gap-8">
                                <button class="btn btn-primary" type="submit" style="height:38px;padding:0 18px;font-size:12.5px">บันทึกสาขา</button>
                            </div>
                        </form>
                        <?php if ((int) $b['user_count'] === 0 && (int) $b['sale_count'] === 0 && count($branches) > 1): ?>
                            <form method="post" action="<?= APP_BASE_PATH ?>/store/branches/<?= $b['id'] ?>/delete" style="margin-top:8px"
                                  onsubmit="return confirm('ลบสาขา <?= $e(addslashes($b['name'])) ?>?')">
                                <button class="btn btn-outline" type="submit" style="height:34px;padding:0 14px;font-size:12px;color:#DC2626;border-color:#FECACA">ลบสาขานี้</button>
                            </form>
                        <?php endif; ?>
                    </details>
                <?php endforeach; ?>

                <details style="border:1px dashed var(--border);border-radius:8px;padding:12px 14px">
                    <summary style="cursor:pointer;font-size:13px;font-weight:600">+ เพิ่มสาขาใหม่</summary>
                    <form method="post" action="<?= APP_BASE_PATH ?>/store/branches" style="display:grid;gap:12px;margin-top:12px">
                        <?php $branchFields(); ?>
                        <div><button class="btn btn-primary" type="submit" style="height:38px;padding:0 18px;font-size:12.5px">เพิ่มสาขา</button></div>
                    </form>
                </details>
            </div>
        </div>
    </div>
</div>
<script>
function fillLocation(uid, btn) {
    if (!navigator.geolocation) { alert('เบราว์เซอร์นี้ไม่รองรับการระบุตำแหน่ง'); return; }
    var original = btn.textContent;
    btn.textContent = 'กำลังระบุตำแหน่ง…';
    btn.disabled = true;
    navigator.geolocation.getCurrentPosition(function (pos) {
        document.getElementById('lat-' + uid).value = pos.coords.latitude.toFixed(7);
        document.getElementById('lng-' + uid).value = pos.coords.longitude.toFixed(7);
        btn.textContent = original;
        btn.disabled = false;
    }, function () {
        alert('ไม่สามารถระบุตำแหน่งได้ กรุณาอนุญาตการเข้าถึงตำแหน่งในเบราว์เซอร์');
        btn.textContent = original;
        btn.disabled = false;
    }, { enableHighAccuracy: true, timeout: 10000 });
}
</script>
</body>
</html>
