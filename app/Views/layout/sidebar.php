<?php
/** @var string $active */
/** @var array $user */
$items = [
    'pos' => ['POS', '/pos'],
    'dashboard' => ['ภาพรวม', '/dashboard'],
    'attendance' => ['ลงเวลา', '/attendance'],
    'members' => ['สมาชิก', '/members'],
    'migrations' => ['Migrations', '/admin/migrations'],
];
?>
<div class="sidebar">
    <div class="logo">POS</div>
    <?php foreach ($items as $key => [$label, $href]):
        if ($key === 'migrations' && ($user['role'] ?? '') !== 'owner') continue;
    ?>
        <a class="nav-item<?= $active === $key ? ' active' : '' ?>" href="<?= $href ?>"><?= \App\Services\View::e($label) ?></a>
    <?php endforeach; ?>
    <div class="spacer"></div>
    <a class="nav-item" href="/logout" title="ออกจากระบบ">ออก</a>
    <div class="avatar"><?= \App\Services\View::e(mb_substr($user['full_name'] ?? '?', 0, 2)) ?></div>
</div>
