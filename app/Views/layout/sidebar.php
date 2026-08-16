<?php
/** @var string $active */
/** @var array $user */
$items = [
    'pos'        => ['POS',      '/pos'],
    'dashboard'  => ['ภาพรวม',   '/dashboard'],
    'attendance' => ['ลงเวลา',   '/attendance'],
    'members'    => ['สมาชิก',   '/members'],
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
    <a class="nav-item" href="<?= APP_BASE_PATH ?>/logout" title="ออกจากระบบ">ออก</a>
    <div class="avatar"><?= \App\Services\View::e(mb_substr($user['full_name'] ?? '?', 0, 2)) ?></div>
</div>
