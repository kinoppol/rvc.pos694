<?php
/** @var string $active */
/** @var array $user */
$items = [
    'pos'        => ['POS',      '/pos'],
    'dashboard'  => ['ภาพรวม',   '/dashboard'],
    'attendance' => ['ลงเวลา',   '/attendance'],
    'members'    => ['สมาชิก',   '/members'],
    'products'   => ['สินค้า',    '/products'],
    'staff'      => ['พนักงาน',  '/staff'],
    'store'      => ['ร้านค้า',   '/store'],
    'migrations' => ['Migrations', '/admin/migrations'],
];
$isPlatformAdmin = ($user['is_platform'] ?? false) && ($user['role'] ?? '') === 'owner';

// fetch pending merchant count for badge (only for platform admin)
$pendingCount = 0;
if ($isPlatformAdmin) {
    try {
        $pendingCount = (int) \App\Services\Database::connection()
            ->query("SELECT COUNT(*) FROM merchants WHERE status='pending' AND is_platform=0")
            ->fetchColumn();
    } catch (\Throwable $e) { /* migration not yet run */ }
}
?>
<div class="sidebar">
    <div class="logo">POS</div>
    <?php foreach ($items as $key => [$label, $href]):
        if ($key === 'migrations' && ($user['role'] ?? '') !== 'owner') continue;
        if (in_array($key, ['staff', 'products'], true) && !in_array($user['role'] ?? '', ['owner', 'manager'], true)) continue;
        if ($key === 'store' && ($user['role'] ?? '') !== 'owner') continue;
    ?>
        <a class="nav-item<?= $active === $key ? ' active' : '' ?>" href="<?= APP_BASE_PATH . $href ?>"><?= \App\Services\View::e($label) ?></a>
    <?php endforeach; ?>

    <?php if ($isPlatformAdmin): ?>
        <div style="font-size:9.5px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.07em;padding:14px 14px 4px">Platform Admin</div>
        <a class="nav-item<?= $active === 'admin_merchants' ? ' active' : '' ?>" href="<?= APP_BASE_PATH ?>/admin/merchants" style="display:flex;justify-content:space-between;align-items:center">
            ร้านค้า
            <?php if ($pendingCount > 0): ?>
                <span style="background:#DC2626;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;min-width:18px;text-align:center"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-item<?= $active === 'admin_settings' ? ' active' : '' ?>" href="<?= APP_BASE_PATH ?>/admin/settings">ตั้งค่าระบบ</a>
    <?php endif; ?>

    <div class="spacer"></div>
    <?php if (\App\Services\AuthService::isImpersonating()):
        $imp = \App\Services\AuthService::impersonator(); ?>
        <div style="margin:0 8px 8px;padding:8px 10px;background:#FEF3C7;border:1px solid #FCD34D;border-radius:8px;text-align:center">
            <div style="font-size:9.5px;font-weight:700;color:#92400E;text-transform:uppercase;letter-spacing:.06em">สวมสิทธิ์อยู่</div>
            <div style="font-size:10.5px;color:#92400E;margin:2px 0 6px;word-break:break-word"><?= \App\Services\View::e($imp['merchant_name'] ?? '') ?></div>
            <form method="post" action="<?= APP_BASE_PATH ?>/admin/impersonate/stop">
                <button type="submit" class="btn btn-outline" style="width:100%;height:28px;padding:0 6px;font-size:11px;color:#92400E;border-color:#FCD34D;background:#fff">กลับเป็น Admin</button>
            </form>
        </div>
    <?php endif; ?>
    <a class="nav-item" href="<?= APP_BASE_PATH ?>/logout" title="<?= \App\Services\AuthService::isImpersonating() ? 'ออกจากการสวมสิทธิ์' : 'ออกจากระบบ' ?>">ออก</a>
    <div class="avatar"><?= \App\Services\View::e(mb_substr($user['full_name'] ?? '?', 0, 2)) ?></div>
</div>
