<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => 'ลงเวลาเข้างาน']); ?>
</head>
<body class="dark-shell">
<div style="max-width:430px;margin:0 auto;min-height:100vh;background:#0B1120;color:#E2E8F0;position:relative;padding-bottom:120px">
    <div style="height:54px;display:flex;align-items:flex-end;justify-content:space-between;padding:0 22px 8px" class="mono">
        <span style="font-weight:600;color:#fff"><?= date('H:i') ?></span>
    </div>
    <div style="padding:6px 20px 14px;display:flex;align-items:center;gap:11px">
        <div style="width:42px;height:42px;border-radius:50%;background:#1E293B;display:grid;place-items:center;font-size:13px;font-weight:600;color:#93C5FD"><?= \App\Services\View::e(mb_substr($user['full_name'], 0, 2)) ?></div>
        <div class="flex-1">
            <div style="font-size:15px;font-weight:600;color:#fff"><?= \App\Services\View::e($user['full_name']) ?></div>
            <div class="text-muted" style="font-size:11.5px"><?= ucfirst($user['role']) ?> · <?= \App\Services\View::e($branch['name'] ?? '-') ?></div>
        </div>
        <a href="<?= APP_BASE_PATH ?>/logout" style="width:36px;height:36px;border-radius:11px;background:#1E293B;display:grid;place-items:center;font-size:12px;color:#E2E8F0">ออก</a>
    </div>

    <div style="margin:0 20px;height:220px;border-radius:18px;overflow:hidden;position:relative;background:#132033;border:1px solid #1E293B">
        <div style="position:absolute;left:50%;top:52%;transform:translate(-50%,-50%);width:150px;height:150px;border-radius:50%;background:rgba(30,64,175,.22);border:2px solid #3B82F6"></div>
        <div style="position:absolute;left:50%;top:52%;transform:translate(-50%,-50%);width:20px;height:20px;border-radius:50%;background:#3B82F6;border:3px solid #fff;box-shadow:0 0 0 6px rgba(59,130,246,.25)"></div>
        <div id="geo-status" style="position:absolute;bottom:12px;left:12px;right:12px;background:rgba(5,46,35,.92);border:1px solid #065F46;border-radius:11px;padding:10px 13px;display:flex;align-items:center;gap:10px">
            <span style="width:9px;height:9px;border-radius:50%;background:#34D399;flex:none"></span>
            <div class="flex-1"><div style="font-size:12.5px;color:#fff;font-weight:600" id="geo-text">กำลังค้นหาตำแหน่ง…</div><div style="font-size:11px;color:#6EE7B7;margin-top:1px" id="geo-coords">รัศมีอนุญาต <?= (int) ($branch['geofence_radius_m'] ?? 50) ?> ม.</div></div>
        </div>
    </div>

    <div style="margin:16px 20px 0;display:flex;gap:10px">
        <div style="flex:1;background:#0F172A;border:1px solid #1E293B;border-radius:13px;padding:12px 13px">
            <div class="text-muted" style="font-size:11px">กะวันนี้</div>
            <div style="font-size:14px;font-weight:600;color:#fff;margin-top:3px"><?= $shift ? \App\Services\View::e($shift['name']) . ' ' . substr($shift['start_time'], 0, 5) . '–' . substr($shift['end_time'], 0, 5) : 'ไม่มีกะวันนี้' ?></div>
        </div>
    </div>

    <div style="margin:16px 20px 0;display:flex;flex-direction:column;align-items:center;gap:10px">
        <button id="clock-btn" style="width:190px;height:190px;border-radius:50%;background:linear-gradient(150deg,#1E40AF,#0E7490);border:none;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;box-shadow:0 0 0 10px rgba(30,64,175,.16),0 12px 34px rgba(30,64,175,.4);cursor:pointer">
            <div style="font-size:26px">⏱</div>
            <div style="font-size:19px;font-weight:700;color:#fff" id="clock-label"><?= $nextAction === 'in' ? 'ลงชื่อเข้างาน' : 'ลงชื่อออกงาน' ?></div>
            <div class="mono" style="font-size:12px;color:rgba(255,255,255,.8)" id="clock-time"><?= date('H:i:s') ?></div>
        </button>
        <div class="text-muted" style="font-size:11.5px">กดปุ่มเพื่อยืนยันตำแหน่งและลงเวลา</div>
    </div>

    <div style="position:absolute;bottom:0;left:0;right:0;background:#0F172A;border-top:1px solid #1E293B;padding:12px 20px 22px">
        <div class="flex" style="justify-content:space-between;font-size:11.5px;color:var(--dark-muted);margin-bottom:8px"><span>สรุปเดือนนี้</span></div>
        <div class="flex gap-8">
            <div style="flex:1;background:#111C2E;border-radius:10px;padding:9px;text-align:center"><div class="mono" style="font-size:16px;font-weight:700;color:#fff"><?= $stats['on_time'] ?></div><div class="text-muted" style="font-size:10.5px">มาปกติ</div></div>
            <div style="flex:1;background:#111C2E;border-radius:10px;padding:9px;text-align:center"><div class="mono" style="font-size:16px;font-weight:700;color:#F87171"><?= $stats['out_of_area'] ?></div><div class="text-muted" style="font-size:10.5px">นอกพื้นที่</div></div>
        </div>
    </div>
</div>
<script>
let currentPos = null;
function updateGeo(pos) {
    currentPos = pos;
    document.getElementById('geo-text').textContent = 'พร้อมลงเวลา · ความแม่นยำ ±' + Math.round(pos.coords.accuracy) + ' ม.';
    document.getElementById('geo-coords').textContent = pos.coords.latitude.toFixed(4) + ', ' + pos.coords.longitude.toFixed(4);
}
if (navigator.geolocation) {
    navigator.geolocation.watchPosition(updateGeo, (err) => {
        document.getElementById('geo-text').textContent = 'ไม่สามารถอ่านตำแหน่งได้: ' + err.message;
    }, { enableHighAccuracy: true });
} else {
    document.getElementById('geo-text').textContent = 'เบราว์เซอร์ไม่รองรับ GPS';
}

document.getElementById('clock-btn').addEventListener('click', async () => {
    if (!currentPos) { alert('กรุณารอให้ระบบอ่านตำแหน่ง GPS ก่อน'); return; }
    const type = document.getElementById('clock-label').textContent.includes('เข้า') ? 'in' : 'out';
    const body = new URLSearchParams({ lat: currentPos.coords.latitude, lng: currentPos.coords.longitude, type });
    const res = await fetch(BASE + '/attendance/clock', { method: 'POST', body });
    const data = await res.json();
    if (data.ok) {
        alert((data.within_geofence ? '✅ ' : '⚠️ นอกพื้นที่ ') + 'บันทึกเวลา' + (data.clock_type === 'in' ? 'เข้างาน' : 'ออกงาน') + ' ' + data.clocked_at + ' (ระยะห่าง ' + data.distance_m + ' ม.)');
        location.reload();
    } else {
        alert('เกิดข้อผิดพลาด');
    }
});
</script>
</body>
</html>
