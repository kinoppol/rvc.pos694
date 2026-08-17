<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;
use App\Services\View;
use PDO;

/**
 * ข้อมูลร้านค้า & สาขา — owner-only editing of the merchant profile and of each
 * branch, including the lat/lng + geofence radius used by AttendanceController.
 */
class StoreController
{
    public function index(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();

        $stmt = $db->prepare('SELECT * FROM merchants WHERE id = ?');
        $stmt->execute([$user['merchant_id']]);
        $merchant = $stmt->fetch();

        $stmt = $db->prepare('SELECT b.*,
                (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.id) AS user_count,
                (SELECT COUNT(*) FROM sales s WHERE s.branch_id = b.id) AS sale_count
            FROM branches b WHERE b.merchant_id = ? ORDER BY b.name');
        $stmt->execute([$user['merchant_id']]);
        $branches = $stmt->fetchAll();

        View::render('store/index', [
            'user'     => $user,
            'merchant' => $merchant,
            'branches' => $branches,
            'error'    => $this->takeError(),
        ]);
    }

    public function updateProfile(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();

        $name    = trim($_POST['name'] ?? '');
        $taxId   = trim($_POST['tax_id'] ?? '') ?: null;
        $phone   = trim($_POST['phone'] ?? '') ?: null;
        $email   = trim($_POST['email'] ?? '') ?: null;
        $address = trim($_POST['address'] ?? '') ?: null;

        if ($name === '') {
            $this->back('กรุณากรอกชื่อร้านค้า');
        }
        if ($taxId !== null && !preg_match('/^\d{13}$/', $taxId)) {
            $this->back('เลขประจำตัวผู้เสียภาษีต้องเป็นตัวเลข 13 หลัก');
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->back('รูปแบบอีเมลไม่ถูกต้อง');
        }

        $db->prepare('UPDATE merchants SET name = ?, tax_id = ?, phone = ?, email = ?, address = ? WHERE id = ?')
           ->execute([$name, $taxId, $phone, $email, $address, $user['merchant_id']]);

        $_SESSION['flash'] = 'บันทึกข้อมูลร้านค้าเรียบร้อย';
        header('Location: ' . APP_BASE_PATH . '/store');
        exit;
    }

    public function branchStore(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $data = $this->branchInput();

        $db->prepare('INSERT INTO branches (merchant_id, name, address, lat, lng, geofence_radius_m) VALUES (?, ?, ?, ?, ?, ?)')
           ->execute([$user['merchant_id'], $data['name'], $data['address'], $data['lat'], $data['lng'], $data['radius']]);

        $_SESSION['flash'] = 'เพิ่มสาขาเรียบร้อย';
        header('Location: ' . APP_BASE_PATH . '/store');
        exit;
    }

    public function branchUpdate(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $id = (int) $args['id'];
        if (!$this->branchBelongsToMerchant($db, $id, (int) $user['merchant_id'])) {
            http_response_code(404);
            echo 'ไม่พบสาขา';
            return;
        }
        $data = $this->branchInput();

        $db->prepare('UPDATE branches SET name = ?, address = ?, lat = ?, lng = ?, geofence_radius_m = ?
                WHERE id = ? AND merchant_id = ?')
           ->execute([$data['name'], $data['address'], $data['lat'], $data['lng'], $data['radius'], $id, $user['merchant_id']]);

        $_SESSION['flash'] = 'บันทึกข้อมูลสาขาเรียบร้อย';
        header('Location: ' . APP_BASE_PATH . '/store');
        exit;
    }

    /** ลบได้เฉพาะสาขาที่ยังไม่มีพนักงานและยังไม่มีการขาย เพื่อไม่ให้ข้อมูลอ้างอิงหาย */
    public function branchDelete(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $id = (int) $args['id'];
        if (!$this->branchBelongsToMerchant($db, $id, (int) $user['merchant_id'])) {
            http_response_code(404);
            echo 'ไม่พบสาขา';
            return;
        }

        $stmt = $db->prepare('SELECT (SELECT COUNT(*) FROM users WHERE branch_id = ?) +
                                     (SELECT COUNT(*) FROM sales WHERE branch_id = ?)');
        $stmt->execute([$id, $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            $this->back('ลบไม่ได้ — สาขานี้มีพนักงานหรือประวัติการขายอยู่');
        }

        $countStmt = $db->prepare('SELECT COUNT(*) FROM branches WHERE merchant_id = ?');
        $countStmt->execute([$user['merchant_id']]);
        if ((int) $countStmt->fetchColumn() <= 1) {
            $this->back('ต้องมีสาขาอย่างน้อย 1 สาขา');
        }

        $db->prepare('DELETE FROM branches WHERE id = ? AND merchant_id = ?')->execute([$id, $user['merchant_id']]);
        $_SESSION['flash'] = 'ลบสาขาเรียบร้อย';
        header('Location: ' . APP_BASE_PATH . '/store');
        exit;
    }

    // ---------- helpers ----------

    private function branchInput(): array
    {
        $name    = trim($_POST['name'] ?? '');
        $address = trim($_POST['address'] ?? '') ?: null;
        $latRaw  = trim($_POST['lat'] ?? '');
        $lngRaw  = trim($_POST['lng'] ?? '');
        $radius  = (int) ($_POST['geofence_radius_m'] ?? 50);

        if ($name === '') {
            $this->back('กรุณากรอกชื่อสาขา');
        }
        if (($latRaw === '') !== ($lngRaw === '')) {
            $this->back('กรุณากรอกทั้งละติจูดและลองจิจูด หรือเว้นว่างทั้งคู่');
        }
        $lat = $latRaw === '' ? null : (float) $latRaw;
        $lng = $lngRaw === '' ? null : (float) $lngRaw;
        if ($lat !== null && ($lat < -90 || $lat > 90)) {
            $this->back('ละติจูดต้องอยู่ระหว่าง -90 ถึง 90');
        }
        if ($lng !== null && ($lng < -180 || $lng > 180)) {
            $this->back('ลองจิจูดต้องอยู่ระหว่าง -180 ถึง 180');
        }
        if ($radius < 10 || $radius > 5000) {
            $this->back('รัศมี geofence ต้องอยู่ระหว่าง 10 - 5000 เมตร');
        }

        return compact('name', 'address', 'lat', 'lng', 'radius');
    }

    private function branchBelongsToMerchant(PDO $db, int $branchId, int $merchantId): bool
    {
        $stmt = $db->prepare('SELECT id FROM branches WHERE id = ? AND merchant_id = ?');
        $stmt->execute([$branchId, $merchantId]);
        return (bool) $stmt->fetch();
    }

    private function back(string $error): void
    {
        $_SESSION['store_error'] = $error;
        header('Location: ' . APP_BASE_PATH . '/store');
        exit;
    }

    private function takeError(): ?string
    {
        $error = $_SESSION['store_error'] ?? null;
        unset($_SESSION['store_error']);
        return $error;
    }
}
