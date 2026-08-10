<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

use App\Services\Database;
use App\Services\InstallState;
use App\Services\MigrationRunner;
use App\Services\PermissionChecker;

$step = $_GET['step'] ?? 'welcome';
$errors = [];
$notice = '';

// ---- Reinstall guard --------------------------------------------------
$alreadyInstalled = InstallState::isFullyInstalled();
$wantsReset = isset($_GET['reset']) || isset($_POST['confirm_reset']);
$wantsReconfigure = isset($_GET['reconfigure']);

if ($alreadyInstalled && $step === 'welcome' && !$wantsReset && !$wantsReconfigure) {
    render_reinstall_notice();
    exit;
}

if ($wantsReset && $step === 'welcome') {
    if (($_POST['confirm_phrase'] ?? '') === 'RESET' && isset($_POST['confirm_reset'])) {
        try {
            require_once APP_CONFIG_FILE;
            $pdo = Database::connection();
            $runner = new MigrationRunner($pdo);
            // Roll back every applied batch, deepest first, then continue to a clean install.
            do {
                $result = $runner->rollbackLastBatch();
            } while (!empty($result['rolled_back']));
            InstallState::removeLock();
            $notice = 'ล้างข้อมูลเดิมเรียบร้อยแล้ว กรุณาติดตั้งใหม่อีกครั้ง';
        } catch (\Throwable $e) {
            $errors[] = 'ล้างข้อมูลไม่สำเร็จ: ' . $e->getMessage();
            render_reinstall_notice($errors);
            exit;
        }
    } else {
        render_reset_confirm_form();
        exit;
    }
}

// ---- Step: welcome / environment check --------------------------------
if ($step === 'welcome') {
    $checks = PermissionChecker::checkAll();
    $ok = PermissionChecker::allOk();
    render_layout('ตรวจสอบระบบก่อนติดตั้ง', function () use ($checks, $ok, $notice) {
        if ($notice) {
            echo '<div class="ok-banner">' . htmlspecialchars($notice) . '</div>';
        }
        echo '<table class="check-table"><tbody>';
        foreach ($checks as $row) {
            $icon = $row['ok'] ? '✅' : '❌';
            echo '<tr><td>' . $icon . ' ' . htmlspecialchars($row['label']) . '</td><td class="detail">' . htmlspecialchars($row['detail']) . '</td></tr>';
        }
        echo '</tbody></table>';
        if ($ok) {
            echo '<a class="btn" href="?step=db">เริ่มการติดตั้ง →</a>';
        } else {
            echo '<p class="err">กรุณาแก้ไขรายการที่มีเครื่องหมาย ❌ ก่อน แล้วโหลดหน้านี้ใหม่</p>';
            echo '<a class="btn secondary" href="?step=welcome">ตรวจสอบอีกครั้ง</a>';
        }
    });
    exit;
}

// ---- Step: database connection -----------------------------------------
if ($step === 'db') {
    if (!PermissionChecker::allOk()) {
        header('Location: ?step=welcome');
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $host = trim($_POST['db_host'] ?? '');
        $port = trim($_POST['db_port'] ?? '3306');
        $name = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = (string) ($_POST['db_pass'] ?? '');

        if ($host === '' || $name === '' || $user === '') {
            $errors[] = 'กรุณากรอก Host, ชื่อฐานข้อมูล และ Username ให้ครบถ้วน';
        } else {
            try {
                Database::connectWith($host, $port, $name, $user, $pass, true);
                $_SESSION['install_db'] = compact('host', 'port', 'name', 'user', 'pass');
                header('Location: ?step=seed');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $e->getMessage();
            }
        }
    }
    render_layout('ตั้งค่าฐานข้อมูล MariaDB', function () use ($errors) {
        render_errors($errors);
        $d = $_SESSION['install_db'] ?? ['host' => 'localhost', 'port' => '3306', 'name' => 'pos_erp', 'user' => 'root', 'pass' => ''];
        ?>
        <form method="post">
            <label>DB Host<input name="db_host" value="<?= htmlspecialchars($d['host']) ?>" required></label>
            <label>DB Port<input name="db_port" value="<?= htmlspecialchars($d['port']) ?>" required></label>
            <label>ชื่อฐานข้อมูล (จะสร้างให้อัตโนมัติถ้ายังไม่มี)<input name="db_name" value="<?= htmlspecialchars($d['name']) ?>" required></label>
            <label>Username<input name="db_user" value="<?= htmlspecialchars($d['user']) ?>" required></label>
            <label>Password<input type="password" name="db_pass" value=""></label>
            <button class="btn" type="submit">ทดสอบและดำเนินการต่อ →</button>
        </form>
        <?php
    });
    exit;
}

// ---- Step: schema + seed (merchant / branch / admin) --------------------
if ($step === 'seed') {
    if (empty($_SESSION['install_db'])) {
        header('Location: ?step=db');
        exit;
    }
    $d = $_SESSION['install_db'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $shopName = trim($_POST['shop_name'] ?? '');
        $taxId = trim($_POST['tax_id'] ?? '');
        $branchName = trim($_POST['branch_name'] ?? '');
        $branchAddress = trim($_POST['branch_address'] ?? '');
        $adminName = trim($_POST['admin_name'] ?? '');
        $adminUser = trim($_POST['admin_username'] ?? '');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPass = (string) ($_POST['admin_password'] ?? '');
        $adminPin = trim($_POST['admin_pin'] ?? '');
        $seedDemo = isset($_POST['seed_demo']);

        if ($shopName === '' || $branchName === '' || $adminUser === '' || strlen($adminPass) < 8) {
            $errors[] = 'กรุณากรอกชื่อร้าน, ชื่อสาขา, Username ผู้ดูแลระบบ และรหัสผ่านอย่างน้อย 8 ตัวอักษร';
        } else {
            $pdo = null;
            try {
                $pdo = Database::connectWith($d['host'], $d['port'], $d['name'], $d['user'], $d['pass']);
                $runner = new MigrationRunner($pdo);
                $result = $runner->migrate();
                if ($result['error']) {
                    throw new \RuntimeException('Migration ล้มเหลว: ' . $result['error']);
                }

                $pdo->beginTransaction();
                $pdo->prepare('INSERT INTO merchants (name, tax_id) VALUES (?, ?)')->execute([$shopName, $taxId ?: null]);
                $merchantId = (int) $pdo->lastInsertId();

                $pdo->prepare('INSERT INTO branches (merchant_id, name, address, lat, lng, geofence_radius_m) VALUES (?, ?, ?, ?, ?, 50)')
                    ->execute([$merchantId, $branchName, $branchAddress ?: null, 13.8221, 100.5610]);
                $branchId = (int) $pdo->lastInsertId();

                $passHash = password_hash($adminPass, PASSWORD_DEFAULT);
                $pinHash = $adminPin !== '' ? password_hash($adminPin, PASSWORD_DEFAULT) : null;
                $pdo->prepare('INSERT INTO users (merchant_id, branch_id, username, email, full_name, password_hash, pin_hash, role) VALUES (?, ?, ?, ?, ?, ?, ?, "owner")')
                    ->execute([$merchantId, $branchId, $adminUser, $adminEmail ?: null, $adminName ?: $adminUser, $passHash, $pinHash]);

                $pdo->commit();

                if ($seedDemo) {
                    require_once APP_ROOT . '/app/Services/DemoSeeder.php';
                    (new \App\Services\DemoSeeder($pdo))->seed($merchantId, $branchId);
                }

                InstallState::writeConfig($d['host'], $d['port'], $d['name'], $d['user'], $d['pass']);
                InstallState::writeLock();
                unset($_SESSION['install_db']);

                header('Location: ?step=done');
                exit;
            } catch (\Throwable $e) {
                if ($pdo instanceof \PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'ติดตั้งไม่สำเร็จ: ' . $e->getMessage();
            }
        }
    }

    render_layout('สร้างร้านค้าและผู้ดูแลระบบ', function () use ($errors) {
        render_errors($errors);
        ?>
        <form method="post">
            <h3>ข้อมูลกิจการ</h3>
            <label>ชื่อร้าน/กิจการ<input name="shop_name" required></label>
            <label>เลขประจำตัวผู้เสียภาษี (ถ้ามี)<input name="tax_id"></label>
            <label>ชื่อสาขาแรก<input name="branch_name" value="สาขาหลัก" required></label>
            <label>ที่อยู่สาขา<input name="branch_address"></label>

            <h3>บัญชีผู้ดูแลระบบ (Owner)</h3>
            <label>ชื่อ-นามสกุล<input name="admin_name" required></label>
            <label>Username<input name="admin_username" required></label>
            <label>Email<input type="email" name="admin_email"></label>
            <label>รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)<input type="password" name="admin_password" required minlength="8"></label>
            <label>PIN สำหรับหน้าเครื่อง POS (4-6 หลัก ไม่บังคับ)<input name="admin_pin" pattern="[0-9]{4,6}"></label>

            <label class="checkbox"><input type="checkbox" name="seed_demo" checked> สร้างข้อมูลตัวอย่าง (สินค้า/สมาชิก/ยอดขาย) เพื่อดูตัวอย่างหน้าจอ</label>

            <button class="btn" type="submit">ติดตั้งระบบ →</button>
        </form>
        <?php
    });
    exit;
}

// ---- Step: done -----------------------------------------------------------
if ($step === 'done') {
    if (!InstallState::isLocked()) {
        header('Location: ?step=welcome');
        exit;
    }
    render_layout('ติดตั้งเสร็จสมบูรณ์', function () {
        echo '<div class="ok-banner">ติดตั้งระบบสำเร็จแล้ว 🎉</div>';
        echo '<p>คุณสามารถลบหรือจำกัดสิทธิ์การเข้าถึงไฟล์ <code>install.php</code> ได้ตามความเหมาะสม ระบบจะแสดงหน้าจอนี้เป็นหน้าแจ้งเตือน "ติดตั้งแล้ว" ทุกครั้งที่เปิดไฟล์นี้ซ้ำ</p>';
        echo '<a class="btn" href="/login">ไปหน้าเข้าสู่ระบบ →</a>';
    });
    exit;
}

http_response_code(400);
echo 'Unknown install step';
exit;

// =========================================================================
function render_errors(array $errors): void
{
    if (!$errors) {
        return;
    }
    echo '<div class="err-banner"><ul>';
    foreach ($errors as $e) {
        echo '<li>' . htmlspecialchars($e) . '</li>';
    }
    echo '</ul></div>';
}

function render_reinstall_notice(array $errors = []): void
{
    render_layout('ระบบติดตั้งแล้ว', function () use ($errors) {
        render_errors($errors);
        echo '<div class="ok-banner">ตรวจพบว่าระบบนี้ติดตั้งเรียบร้อยแล้ว</div>';
        echo '<p>เลือกดำเนินการ:</p>';
        echo '<a class="btn" href="/login">ไปหน้าเข้าสู่ระบบ</a>';
        echo '<a class="btn secondary" href="?reconfigure=1&step=db">ตั้งค่าฐานข้อมูลใหม่ (Reconfigure — คงข้อมูลเดิม)</a>';
        echo '<a class="btn danger" href="?reset=1">ล้างข้อมูลทั้งหมดและติดตั้งใหม่ (Reset)</a>';
    });
}

function render_reset_confirm_form(): void
{
    render_layout('ยืนยันการล้างข้อมูล', function () {
        echo '<div class="err-banner">การดำเนินการนี้จะลบข้อมูลทั้งหมดในระบบอย่างถาวรและไม่สามารถกู้คืนได้</div>';
        ?>
        <form method="post" action="?step=welcome&reset=1">
            <label>พิมพ์คำว่า <strong>RESET</strong> เพื่อยืนยัน<input name="confirm_phrase" autocomplete="off" required></label>
            <button class="btn danger" type="submit" name="confirm_reset" value="1">ยืนยันล้างข้อมูลทั้งหมด</button>
        </form>
        <a class="btn secondary" href="/install.php">ยกเลิก</a>
        <?php
    });
}

function render_layout(string $title, callable $body): void
{
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> · ตัวติดตั้งระบบ POS/ERP</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
    <style>
        body{margin:0;background:#e8eaf0;font-family:'IBM Plex Sans Thai',system-ui,sans-serif;color:#0F172A}
        .wrap{max-width:640px;margin:48px auto;padding:0 20px}
        .card{background:#fff;border:1px solid rgba(15,23,42,.1);border-radius:14px;box-shadow:0 2px 8px rgba(15,23,42,.08);padding:28px 30px}
        h1{font-size:19px;margin:0 0 18px}
        h3{font-size:14px;color:#334155;margin:18px 0 10px}
        label{display:flex;flex-direction:column;gap:5px;font-size:12.5px;color:#475569;margin-bottom:12px;font-weight:500}
        label.checkbox{flex-direction:row;align-items:center;gap:8px}
        input[type=text],input[type=password],input[type=email],input:not([type]){height:40px;border:1px solid #CBD5E1;border-radius:8px;padding:0 12px;font-size:13.5px;font-family:inherit}
        .btn{display:inline-block;margin:14px 8px 0 0;height:42px;line-height:42px;padding:0 18px;background:linear-gradient(100deg,#1E40AF,#4338CA);color:#fff;border:none;border-radius:9px;font-size:13.5px;font-weight:600;text-decoration:none;cursor:pointer}
        .btn.secondary{background:#fff;color:#334155;border:1px solid #CBD5E1}
        .btn.danger{background:#DC2626}
        .check-table{width:100%;border-collapse:collapse;margin-bottom:10px}
        .check-table td{padding:8px 4px;border-bottom:1px solid #F1F5F9;font-size:13px;vertical-align:top}
        .check-table td.detail{color:#64748B;text-align:right;font-size:11.5px}
        .ok-banner{background:#ECFDF5;border:1px solid #A7F3D0;color:#047857;padding:12px 14px;border-radius:9px;font-size:13px;margin-bottom:14px}
        .err-banner{background:#FEF2F2;border:1px solid #FECACA;color:#B91C1C;padding:12px 14px;border-radius:9px;font-size:13px;margin-bottom:14px}
        .err-banner ul{margin:0;padding-left:18px}
        .err{color:#B91C1C;font-size:13px}
        code{background:#F1F5F9;padding:2px 6px;border-radius:5px;font-size:12px}
    </style>
    </head>
    <body>
    <div class="wrap">
        <div class="card">
            <h1><?= htmlspecialchars($title) ?></h1>
            <?php $body(); ?>
        </div>
    </div>
    </body>
    </html>
    <?php
}
