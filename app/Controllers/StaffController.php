<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;
use App\Services\View;
use PDO;

/**
 * จัดการข้อมูลพนักงาน — CRUD over `users` scoped to the current merchant.
 * Owners see/manage every branch; managers are limited to their own branch and
 * may not touch owner accounts.
 */
class StaffController
{
    private const ROLES = [
        'owner'   => 'เจ้าของร้าน',
        'manager' => 'ผู้จัดการ',
        'cashier' => 'แคชเชียร์',
        'staff'   => 'พนักงาน',
    ];

    public function index(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();

        $q        = trim($_GET['q'] ?? '');
        $branchId = isset($_GET['branch_id']) && $_GET['branch_id'] !== '' ? (int) $_GET['branch_id'] : null;
        $status   = $_GET['status'] ?? '';

        $sql = 'SELECT u.*, b.name AS branch_name FROM users u
                LEFT JOIN branches b ON b.id = u.branch_id
                WHERE u.merchant_id = ?';
        $params = [$user['merchant_id']];

        if ($q !== '') {
            $sql .= ' AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        if ($branchId !== null) {
            $sql .= ' AND u.branch_id = ?';
            $params[] = $branchId;
        }
        if ($status === 'active' || $status === 'inactive') {
            $sql .= ' AND u.active = ?';
            $params[] = $status === 'active' ? 1 : 0;
        }
        // managers only ever see their own branch, and never owner accounts
        if ($user['role'] !== 'owner') {
            $sql .= " AND u.role <> 'owner' AND (u.branch_id = ? OR u.id = ?)";
            $params[] = $user['branch_id'];
            $params[] = $user['id'];
        }
        $sql .= " ORDER BY FIELD(u.role,'owner','manager','cashier','staff'), u.full_name";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $staff = $stmt->fetchAll();

        $counts = ['total' => count($staff), 'active' => 0, 'with_pin' => 0];
        foreach ($staff as $s) {
            if ((int) $s['active'] === 1) $counts['active']++;
            if ($s['pin_hash']) $counts['with_pin']++;
        }

        $branches = $this->branches($db, $user);
        $roles = self::ROLES;

        View::render('staff/index', compact('user', 'staff', 'branches', 'roles', 'q', 'branchId', 'status', 'counts'));
    }

    public function create(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        View::render('staff/form', [
            'user'     => $user,
            'staff'    => null,
            'branches' => $this->branches($db, $user),
            'roles'    => $this->assignableRoles($user),
            'error'    => $_SESSION['staff_error'] ?? null,
            'old'      => $this->takeOld(),
        ]);
        unset($_SESSION['staff_error']);
    }

    public function store(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();

        $data = $this->input($user, $db);
        $password = (string) ($_POST['password'] ?? '');

        $error = $this->validate($db, $user, $data, null);
        if ($error === null && strlen($password) < 6) {
            $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
        }
        if ($error !== null) {
            $this->back($error, APP_BASE_PATH . '/staff/new');
        }

        $stmt = $db->prepare('INSERT INTO users (merchant_id, branch_id, username, email, full_name, password_hash, pin_hash, role, active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $user['merchant_id'],
            $data['branch_id'],
            $data['username'],
            $data['email'],
            $data['full_name'],
            AuthService::hashPassword($password),
            $data['pin'] !== '' ? AuthService::hashPassword($data['pin']) : null,
            $data['role'],
            $data['active'],
        ]);

        $_SESSION['flash'] = 'เพิ่มพนักงานเรียบร้อย';
        header('Location: ' . APP_BASE_PATH . '/staff');
        exit;
    }

    public function edit(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $staff = $this->findOr404($db, $user, (int) $args['id']);
        if (!$staff) {
            return;
        }
        View::render('staff/form', [
            'user'     => $user,
            'staff'    => $staff,
            'branches' => $this->branches($db, $user),
            'roles'    => $this->assignableRoles($user),
            'error'    => $_SESSION['staff_error'] ?? null,
            'old'      => $this->takeOld(),
        ]);
        unset($_SESSION['staff_error']);
    }

    public function update(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $id = (int) $args['id'];
        $staff = $this->findOr404($db, $user, $id);
        if (!$staff) {
            return;
        }

        $data = $this->input($user, $db);
        $error = $this->validate($db, $user, $data, $id);

        // นับเจ้าของร้านที่ยังใช้งานอยู่ — ห้ามเหลือศูนย์
        if ($error === null && $staff['role'] === 'owner' && ($data['role'] !== 'owner' || !$data['active'])
            && $this->activeOwnerCount($db, (int) $user['merchant_id'], $id) === 0) {
            $error = 'ต้องมีเจ้าของร้านที่ใช้งานอยู่อย่างน้อย 1 คน';
        }
        if ($error === null && $id === (int) $user['id'] && (!$data['active'] || $data['role'] !== $staff['role'])) {
            $error = 'ไม่สามารถเปลี่ยนบทบาทหรือปิดการใช้งานบัญชีของตนเองได้';
        }
        if ($error !== null) {
            $this->back($error, APP_BASE_PATH . '/staff/' . $id . '/edit');
        }

        $db->prepare('UPDATE users SET branch_id = ?, username = ?, email = ?, full_name = ?, role = ?, active = ?
            WHERE id = ? AND merchant_id = ?')
           ->execute([
               $data['branch_id'], $data['username'], $data['email'], $data['full_name'],
               $data['role'], $data['active'], $id, $user['merchant_id'],
           ]);

        $password = (string) ($_POST['password'] ?? '');
        if ($password !== '') {
            if (strlen($password) < 6) {
                $this->back('รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร', APP_BASE_PATH . '/staff/' . $id . '/edit');
            }
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ? AND merchant_id = ?')
               ->execute([AuthService::hashPassword($password), $id, $user['merchant_id']]);
        }

        if (!empty($_POST['clear_pin'])) {
            $db->prepare('UPDATE users SET pin_hash = NULL WHERE id = ? AND merchant_id = ?')
               ->execute([$id, $user['merchant_id']]);
        } elseif ($data['pin'] !== '') {
            $db->prepare('UPDATE users SET pin_hash = ? WHERE id = ? AND merchant_id = ?')
               ->execute([AuthService::hashPassword($data['pin']), $id, $user['merchant_id']]);
        }

        $_SESSION['flash'] = 'บันทึกข้อมูลพนักงานเรียบร้อย';
        header('Location: ' . APP_BASE_PATH . '/staff');
        exit;
    }

    /** เปิด/ปิดการใช้งานบัญชี (ไม่ลบข้อมูล เพื่อคงประวัติการขาย/ลงเวลา) */
    public function toggle(array $args): void
    {
        $user = AuthService::currentUser();
        $db = Database::connection();
        $id = (int) $args['id'];
        $staff = $this->findOr404($db, $user, $id);
        if (!$staff) {
            return;
        }

        if ($id === (int) $user['id']) {
            $this->back('ไม่สามารถปิดการใช้งานบัญชีของตนเองได้', APP_BASE_PATH . '/staff');
        }
        $newActive = (int) $staff['active'] === 1 ? 0 : 1;
        if ($newActive === 0 && $staff['role'] === 'owner' && $this->activeOwnerCount($db, (int) $user['merchant_id'], $id) === 0) {
            $this->back('ต้องมีเจ้าของร้านที่ใช้งานอยู่อย่างน้อย 1 คน', APP_BASE_PATH . '/staff');
        }

        $db->prepare('UPDATE users SET active = ? WHERE id = ? AND merchant_id = ?')
           ->execute([$newActive, $id, $user['merchant_id']]);

        $_SESSION['flash'] = $newActive === 1 ? 'เปิดใช้งานบัญชีแล้ว' : 'ปิดใช้งานบัญชีแล้ว';
        header('Location: ' . APP_BASE_PATH . '/staff');
        exit;
    }

    // ---------- helpers ----------

    private function input(array $user, PDO $db): array
    {
        $branchId = isset($_POST['branch_id']) && $_POST['branch_id'] !== '' ? (int) $_POST['branch_id'] : null;
        if ($user['role'] !== 'owner') {
            $branchId = $user['branch_id'] !== null ? (int) $user['branch_id'] : $branchId;
        }
        return [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'username'  => trim($_POST['username'] ?? ''),
            'email'     => trim($_POST['email'] ?? '') ?: null,
            'role'      => (string) ($_POST['role'] ?? 'staff'),
            'branch_id' => $branchId,
            'pin'       => trim($_POST['pin'] ?? ''),
            'active'    => isset($_POST['active']) ? 1 : 0,
        ];
    }

    private function validate(PDO $db, array $user, array $data, ?int $ignoreId): ?string
    {
        if ($data['full_name'] === '' || $data['username'] === '') {
            return 'กรุณากรอกชื่อ-นามสกุล และชื่อผู้ใช้';
        }
        if (!array_key_exists($data['role'], $this->assignableRoles($user))) {
            return 'ไม่มีสิทธิ์กำหนดบทบาทนี้';
        }
        if ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return 'รูปแบบอีเมลไม่ถูกต้อง';
        }
        if ($data['pin'] !== '' && !preg_match('/^\d{4,6}$/', $data['pin'])) {
            return 'PIN ต้องเป็นตัวเลข 4-6 หลัก';
        }
        if ($data['branch_id'] !== null && !$this->branchBelongsToMerchant($db, (int) $data['branch_id'], (int) $user['merchant_id'])) {
            return 'ไม่พบสาขาที่เลือก';
        }

        $sql = 'SELECT id FROM users WHERE username = ?';
        $params = [$data['username']];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            return 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว';
        }
        return null;
    }

    /** @return array<string,string> */
    private function assignableRoles(array $user): array
    {
        if ($user['role'] === 'owner') {
            return self::ROLES;
        }
        return array_diff_key(self::ROLES, ['owner' => '']);
    }

    private function branches(PDO $db, array $user): array
    {
        $stmt = $db->prepare('SELECT id, name FROM branches WHERE merchant_id = ? ORDER BY name');
        $stmt->execute([$user['merchant_id']]);
        return $stmt->fetchAll();
    }

    private function branchBelongsToMerchant(PDO $db, int $branchId, int $merchantId): bool
    {
        $stmt = $db->prepare('SELECT id FROM branches WHERE id = ? AND merchant_id = ?');
        $stmt->execute([$branchId, $merchantId]);
        return (bool) $stmt->fetch();
    }

    private function activeOwnerCount(PDO $db, int $merchantId, int $excludeId): int
    {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE merchant_id = ? AND role = 'owner' AND active = 1 AND id <> ?");
        $stmt->execute([$merchantId, $excludeId]);
        return (int) $stmt->fetchColumn();
    }

    private function findOr404(PDO $db, array $user, int $id): ?array
    {
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND merchant_id = ?');
        $stmt->execute([$id, $user['merchant_id']]);
        $staff = $stmt->fetch();

        $managerBlocked = $user['role'] !== 'owner' && $staff
            && ($staff['role'] === 'owner'
                || ((int) $staff['id'] !== (int) $user['id'] && (int) $staff['branch_id'] !== (int) $user['branch_id']));

        if (!$staff || $managerBlocked) {
            http_response_code(404);
            echo 'ไม่พบพนักงาน';
            return null;
        }
        return $staff;
    }

    private function back(string $error, string $location): void
    {
        $_SESSION['staff_error'] = $error;
        $_SESSION['staff_old'] = $_POST;
        header('Location: ' . $location);
        exit;
    }

    private function takeOld(): array
    {
        $old = $_SESSION['staff_old'] ?? [];
        unset($_SESSION['staff_old']);
        return $old;
    }
}
