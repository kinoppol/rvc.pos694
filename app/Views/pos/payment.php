<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => 'ชำระเงิน']); ?>
</head>
<body>
<div style="min-height:100vh;background:#F1F5F9;display:flex;align-items:center;justify-content:center;padding:26px">
<div style="width:560px;display:flex;flex-direction:column;gap:16px">

    <div class="card" style="border-radius:14px;box-shadow:0 12px 34px rgba(15,23,42,.16);overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center">
            <span style="font-size:15px;font-weight:600">ชำระเงิน</span>
            <div class="flex-1"></div>
            <span class="mono" style="font-size:19px;font-weight:700;color:#1E40AF" id="pay-total"><?= \App\Services\View::money($totals['grand_total']) ?></span>
        </div>
        <div class="flex gap-8" style="padding:14px 20px 0">
            <button class="btn method-btn active" data-method="promptpay" style="flex:1;height:40px;background:#1E40AF;color:#fff">PromptPay QR</button>
            <button class="btn method-btn btn-outline" data-method="cash" style="flex:1;height:40px">เงินสด</button>
            <button class="btn method-btn btn-outline" data-method="card" style="flex:1;height:40px">บัตรเครดิต</button>
        </div>

        <div id="pane-promptpay" style="padding:20px;display:flex;gap:18px;align-items:center">
            <div style="width:172px;height:172px;border-radius:12px;border:1px solid var(--border);background:var(--bg-lighter);display:grid;place-items:center;text-align:center;color:#94A3B8;font-size:11px;line-height:1.5">Dynamic QR<br>(placeholder)</div>
            <div class="flex-1">
                <div class="text-muted" style="font-size:12px">รอลูกค้าสแกน…</div>
                <div class="mono" style="font-size:30px;font-weight:700;margin:4px 0 10px" id="countdown">05:00</div>
                <div style="height:6px;border-radius:3px;background:var(--border);overflow:hidden;margin-bottom:14px"><div id="progress-bar" style="width:0%;height:100%;background:linear-gradient(90deg,#1E40AF,#0891B2)"></div></div>
                <div style="font-size:12px;color:var(--text-soft);line-height:1.9">
                    <div class="flex" style="justify-content:space-between"><span>Ref</span><span class="mono" style="font-weight:600"><?= \App\Services\View::e($ref) ?></span></div>
                </div>
                <div class="flex gap-8" style="margin-top:14px">
                    <a href="/pos" class="btn btn-outline flex-1">ยกเลิก</a>
                    <button class="btn btn-success flex-1" id="confirm-promptpay">ยืนยันรับเงินแล้ว</button>
                </div>
            </div>
        </div>

        <div id="pane-card" style="display:none;padding:20px">
            <p class="text-muted">รูดบัตร / แตะบัตรที่เครื่องอ่านบัตร แล้วกดยืนยัน</p>
            <button class="btn btn-success" id="confirm-card" style="width:100%">ยืนยันชำระด้วยบัตร</button>
        </div>

        <div style="padding:12px 20px;background:var(--bg-lighter);border-top:1px solid var(--border);display:flex;gap:16px;font-size:11.5px;color:var(--text-soft);align-items:center">
            <span>ส่ง e-Receipt:</span>
            <span style="padding:4px 9px;background:#fff;border:1px solid #CBD5E1;border-radius:6px">SMS</span>
            <span style="padding:4px 9px;background:#ECFDF5;border:1px solid #A7F3D0;border-radius:6px;color:#047857;font-weight:600">LINE ✓</span>
        </div>
    </div>

    <div id="pane-cash" class="card" style="display:none;padding:18px 20px">
        <div style="font-size:13px;font-weight:600;margin-bottom:12px">โหมดเงินสด — คำนวณเงินทอน</div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:12px" id="cash-quick">
            <?php
            $grand = $totals['grand_total'];
            $suggest = [ceil($grand / 100) * 100, ceil($grand / 500) * 500, ceil($grand / 1000) * 1000, ceil($grand / 100) * 100 + 1000];
            $suggest = array_values(array_unique(array_filter($suggest, fn($v) => $v >= $grand)));
            foreach ($suggest as $amt): ?>
                <button type="button" class="btn btn-outline cash-amt mono" data-amt="<?= $amt ?>"><?= \App\Services\View::money((float) $amt) ?></button>
            <?php endforeach; ?>
        </div>
        <input type="number" id="cash-tendered" placeholder="รับเงินมา (บาท)" class="mono" style="width:100%;height:44px;margin-bottom:12px">
        <div class="flex" style="justify-content:space-between;align-items:baseline;padding:12px 14px;background:var(--success-bg);border-radius:10px">
            <span style="font-size:13px;color:#047857;font-weight:600">เงินทอน</span>
            <span class="mono" id="change-amount" style="font-size:24px;font-weight:700;color:#047857">฿0.00</span>
        </div>
        <button class="btn btn-success" id="confirm-cash" style="width:100%;margin-top:12px">ยืนยันรับเงินสด</button>
    </div>
</div>
</div>
<script>
const grandTotal = <?= json_encode($totals['grand_total']) ?>;

document.querySelectorAll('.method-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.method-btn').forEach(b => { b.classList.remove('active'); b.style.background=''; b.style.color=''; b.classList.add('btn-outline'); });
        btn.classList.remove('btn-outline'); btn.style.background = '#1E40AF'; btn.style.color = '#fff';
        document.getElementById('pane-promptpay').style.display = btn.dataset.method === 'promptpay' ? 'flex' : 'none';
        document.getElementById('pane-cash').style.display = btn.dataset.method === 'cash' ? 'block' : 'none';
        document.getElementById('pane-card').style.display = btn.dataset.method === 'card' ? 'block' : 'none';
    });
});

let seconds = 300;
const cdEl = document.getElementById('countdown');
const pbEl = document.getElementById('progress-bar');
const timer = setInterval(() => {
    seconds--;
    if (seconds <= 0) { clearInterval(timer); }
    const m = String(Math.floor(seconds/60)).padStart(2,'0');
    const s = String(seconds%60).padStart(2,'0');
    cdEl.textContent = m + ':' + s;
    pbEl.style.width = Math.round((1 - seconds/300) * 100) + '%';
}, 1000);

document.querySelectorAll('.cash-amt').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('cash-tendered').value = btn.dataset.amt;
        updateChange();
    });
});
document.getElementById('cash-tendered').addEventListener('input', updateChange);
function updateChange() {
    const t = parseFloat(document.getElementById('cash-tendered').value || '0');
    const change = Math.max(0, t - grandTotal);
    document.getElementById('change-amount').textContent = '฿' + change.toLocaleString(undefined,{minimumFractionDigits:2});
}

async function confirmPayment(method, tendered) {
    const body = new URLSearchParams({ method, tendered: tendered ?? '' });
    const res = await fetch('/pos/pay/confirm', { method: 'POST', body });
    const data = await res.json();
    if (data.ok) {
        alert('ชำระเงินสำเร็จ: ' + data.invoice_no + (data.change !== null && data.change !== undefined ? ' · เงินทอน ฿' + Number(data.change).toFixed(2) : ''));
        location.href = '/pos';
    } else {
        alert(data.error || 'เกิดข้อผิดพลาด');
    }
}
document.getElementById('confirm-promptpay').addEventListener('click', () => confirmPayment('promptpay', null));
document.getElementById('confirm-card').addEventListener('click', () => confirmPayment('card', null));
document.getElementById('confirm-cash').addEventListener('click', () => {
    const t = parseFloat(document.getElementById('cash-tendered').value || '0');
    if (t < grandTotal) { alert('รับเงินไม่พอ'); return; }
    confirmPayment('cash', t);
});
</script>
</body>
</html>
