<!DOCTYPE html>
<html lang="th">
<head>
<?php \App\Services\View::render('layout/head', ['title' => $member['full_name']]); ?>
</head>
<body>
<div class="app-shell">
    <?php \App\Services\View::render('layout/sidebar', ['active' => 'members', 'user' => $user]); ?>
    <div class="main-area">
        <div class="topbar"><a href="/members" style="font-size:13px">← กลับรายชื่อสมาชิก</a></div>
        <div class="content" style="display:flex;justify-content:center">
        <div style="width:720px">
        <div class="card" style="border-radius:16px;box-shadow:0 16px 44px rgba(15,23,42,.18);overflow:hidden">
            <div style="padding:20px 24px;background:linear-gradient(105deg,#1E40AF,#4338CA);color:#fff;display:flex;align-items:center;gap:14px">
                <div style="width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.18);display:grid;place-items:center;font-size:17px;font-weight:600"><?= \App\Services\View::e(mb_substr($member['full_name'], 0, 2)) ?></div>
                <div class="flex-1">
                    <div class="flex gap-8" style="align-items:center">
                        <span style="font-size:19px;font-weight:600"><?= \App\Services\View::e($member['full_name']) ?></span>
                        <?php if ($member['tier_name']): ?><span style="font-size:10.5px;font-weight:600;background:#FCD34D;color:#78350F;padding:3px 9px;border-radius:20px"><?= strtoupper(\App\Services\View::e($member['tier_name'])) ?></span><?php endif; ?>
                    </div>
                    <div style="font-size:12px;color:rgba(255,255,255,.82);margin-top:4px;display:flex;gap:14px">
                        <span><?= \App\Services\View::e($member['phone']) ?></span>
                        <?php if ($member['birthdate']): ?><span>เกิด <?= date('d M Y', strtotime($member['birthdate'])) ?></span><?php endif; ?>
                        <span>สมาชิกตั้งแต่ <?= date('Y', strtotime($member['member_since'])) ?></span>
                    </div>
                </div>
                <div style="text-align:right"><div style="font-size:11px;color:rgba(255,255,255,.75)">ยอดซื้อสะสม</div><div class="mono" style="font-size:22px;font-weight:700"><?= \App\Services\View::money((float) $member['total_spend']) ?></div></div>
            </div>

            <div class="flex gap-12" style="padding:16px 24px;border-bottom:1px solid var(--border)">
                <?php if ($member['birthdate'] && date('m') === date('m', strtotime($member['birthdate']))): ?>
                <div class="flex-1" style="background:#FDF2F8;border:1px solid #FBCFE8;border-radius:10px;padding:11px 13px">
                    <div style="font-size:11px;color:#9D174D">โปรฯ เดือนเกิด</div>
                    <div style="font-size:13.5px;font-weight:600;color:#831843;margin-top:2px">ส่วนลด 10% ใช้ได้ทันที</div>
                </div>
                <?php endif; ?>
                <div class="flex-1" style="background:var(--bg-lighter);border:1px solid var(--border);border-radius:10px;padding:11px 13px">
                    <div class="text-muted" style="font-size:11px">แต้มคงเหลือ</div>
                    <div class="mono" style="font-size:15px;font-weight:600;margin-top:2px"><?= number_format($member['points']) ?></div>
                </div>
                <div class="flex-1" style="background:var(--bg-lighter);border:1px solid var(--border);border-radius:10px;padding:11px 13px">
                    <div class="text-muted" style="font-size:11px">ความถี่ / บิลเฉลี่ย</div>
                    <div style="font-size:13.5px;font-weight:600;margin-top:2px"><?= $frequency ?> ครั้ง/เดือน · <?= \App\Services\View::money((float) $freq['avg_total']) ?></div>
                </div>
            </div>

            <div style="padding:18px 24px;display:grid;grid-template-columns:1fr 1fr;gap:22px">
                <div>
                    <div class="text-muted" style="font-size:12px;margin-bottom:10px">Top 5 สินค้าที่ซื้อซ้ำ</div>
                    <div class="flex" style="flex-direction:column;gap:10px">
                        <?php foreach ($topProducts as $tp): ?>
                        <div class="flex gap-8" style="align-items:center">
                            <div style="width:40px;height:40px;border-radius:8px;background:var(--border);flex:none"></div>
                            <div class="flex-1">
                                <div style="font-size:12.5px;font-weight:600"><?= \App\Services\View::e($tp['name']) ?></div>
                                <div style="height:5px;border-radius:3px;background:var(--bg-light);margin-top:5px"><div style="width:<?= round($tp['qty'] / $maxQty * 100) ?>%;height:100%;border-radius:3px;background:#1E40AF"></div></div>
                            </div>
                            <div class="mono" style="font-size:12px;color:var(--text-soft)"><?= (int) $tp['qty'] ?>×</div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (!$topProducts): ?><p class="text-muted" style="font-size:12px">ยังไม่มีประวัติการซื้อ</p><?php endif; ?>
                    </div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:12px;margin-bottom:10px">บิลล่าสุด</div>
                    <div class="flex" style="flex-direction:column;gap:8px">
                        <?php foreach ($recentSales as $s): $refund = $s['status'] === 'refunded'; ?>
                        <div style="border:1px solid <?= $refund ? '#FED7AA' : 'var(--border)' ?>;background:<?= $refund ? '#FFF7ED' : '#fff' ?>;border-radius:9px;padding:10px 12px">
                            <div class="flex" style="justify-content:space-between">
                                <span class="mono" style="font-size:11.5px;font-weight:600"><?= \App\Services\View::e($s['invoice_no']) ?></span>
                                <span class="mono" style="font-size:12.5px;font-weight:600;color:<?= $refund ? '#C2410C' : '#0F172A' ?>"><?= $refund ? '−' : '' ?><?= \App\Services\View::money((float) $s['grand_total']) ?></span>
                            </div>
                            <div style="font-size:11px;color:<?= $refund ? '#C2410C' : 'var(--text-muted)' ?>;margin-top:3px"><?= date('d M', strtotime($s['created_at'])) ?> · <?= \App\Services\View::e($s['branch_name']) ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (!$recentSales): ?><p class="text-muted" style="font-size:12px">ยังไม่มีประวัติบิล</p><?php endif; ?>
                    </div>
                    <div class="flex gap-8" style="margin-top:16px">
                        <a href="/pos" onclick="event.preventDefault(); fetch('/members/<?= $member['id'] ?>/use',{method:'POST'}).then(()=>location.href='/pos')" class="btn btn-primary flex-1" style="text-decoration:none;text-align:center">ใช้สมาชิกกับบิลนี้</a>
                    </div>
                </div>
            </div>
        </div>
        </div>
        </div>
    </div>
</div>
</body>
</html>
