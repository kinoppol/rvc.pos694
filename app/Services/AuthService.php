<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

class AuthService
{
    private PDO $db;
    public string $loginError = '';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function attemptLogin(string $username, string $password): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ? AND active = 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->loginError = 'credentials';
            return null;
        }

        // check merchant status (graceful if migration not yet run)
        try {
            $mStmt = $this->db->prepare('SELECT status FROM merchants WHERE id = ?');
            $mStmt->execute([$user['merchant_id']]);
            $merchant = $mStmt->fetch();
            if ($merchant && $merchant['status'] === 'pending') {
                $this->loginError = 'pending';
                return null;
            }
            if ($merchant && $merchant['status'] === 'suspended') {
                $this->loginError = 'suspended';
                return null;
            }
        } catch (\PDOException $e) {
            // migration not yet run — allow login
        }

        $this->establishSession($user);
        return $user;
    }

    /**
     * Cashier quick re-auth on a shared POS terminal (does not replace the
     * logged-in session — used to confirm sensitive actions like Void).
     */
    public function verifyPin(int $userId, string $pin): bool
    {
        $stmt = $this->db->prepare('SELECT pin_hash FROM users WHERE id = ? AND active = 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || !$row['pin_hash']) {
            return false;
        }
        return password_verify($pin, $row['pin_hash']);
    }

    public function loginWithPin(int $branchId, string $pin): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM users WHERE branch_id = ? AND role IN ('cashier','manager') AND active = 1 AND pin_hash IS NOT NULL"
        );
        $stmt->execute([$branchId]);
        foreach ($stmt->fetchAll() as $user) {
            if (password_verify($pin, $user['pin_hash'])) {
                $this->establishSession($user);
                return $user;
            }
        }
        return null;
    }

    /**
     * Platform admin "สวมสิทธิ์" (impersonate) a merchant user: the admin's own
     * session is stashed under `impersonator` so it can be restored on exit.
     */
    public function impersonate(array $targetUser): void
    {
        $original = $_SESSION['impersonator'] ?? [
            'user_id'     => $_SESSION['user_id'] ?? null,
            'merchant_id' => $_SESSION['merchant_id'] ?? null,
            'branch_id'   => $_SESSION['branch_id'] ?? null,
            'role'        => $_SESSION['role'] ?? null,
            'full_name'   => $_SESSION['full_name'] ?? null,
            'is_platform' => $_SESSION['is_platform'] ?? false,
        ];

        CartService::clear();
        $this->establishSession($targetUser);
        $_SESSION['impersonator'] = $original;
    }

    /** Restore the stashed platform-admin session. Returns false if not impersonating. */
    public static function stopImpersonation(): bool
    {
        if (empty($_SESSION['impersonator']['user_id'])) {
            return false;
        }
        $admin = $_SESSION['impersonator'];
        CartService::clear();
        session_regenerate_id(true);
        unset($_SESSION['impersonator']);
        $_SESSION['user_id']     = (int) $admin['user_id'];
        $_SESSION['merchant_id'] = (int) $admin['merchant_id'];
        $_SESSION['branch_id']   = $admin['branch_id'] !== null ? (int) $admin['branch_id'] : null;
        $_SESSION['role']        = $admin['role'];
        $_SESSION['full_name']   = $admin['full_name'];
        $_SESSION['is_platform'] = (bool) $admin['is_platform'];
        return true;
    }

    public static function isImpersonating(): bool
    {
        return !empty($_SESSION['impersonator']['user_id']);
    }

    public static function impersonator(): ?array
    {
        return $_SESSION['impersonator'] ?? null;
    }

    private function establishSession(array $user): void
    {
        session_regenerate_id(true);
        unset($_SESSION['impersonator']);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['merchant_id'] = (int) $user['merchant_id'];
        $_SESSION['branch_id'] = $user['branch_id'] !== null ? (int) $user['branch_id'] : null;
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];

        // store platform flag so every page can check without a DB query
        $_SESSION['is_platform'] = false;
        try {
            $mStmt = $this->db->prepare('SELECT is_platform FROM merchants WHERE id = ?');
            $mStmt->execute([$user['merchant_id']]);
            $merchant = $mStmt->fetch();
            $_SESSION['is_platform'] = $merchant && (bool) $merchant['is_platform'];
        } catch (\PDOException $e) {
            // migration not yet run
        }
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    public static function currentUser(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id'          => $_SESSION['user_id'],
            'merchant_id' => $_SESSION['merchant_id'],
            'branch_id'   => $_SESSION['branch_id'],
            'role'        => $_SESSION['role'],
            'full_name'   => $_SESSION['full_name'],
            'is_platform' => $_SESSION['is_platform'] ?? false,
            'impersonating' => self::isImpersonating(),
        ];
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }
}
