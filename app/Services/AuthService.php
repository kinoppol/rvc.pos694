<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

class AuthService
{
    private PDO $db;

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
            return null;
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

    private function establishSession(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['merchant_id'] = (int) $user['merchant_id'];
        $_SESSION['branch_id'] = $user['branch_id'] !== null ? (int) $user['branch_id'] : null;
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
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
            'id' => $_SESSION['user_id'],
            'merchant_id' => $_SESSION['merchant_id'],
            'branch_id' => $_SESSION['branch_id'],
            'role' => $_SESSION['role'],
            'full_name' => $_SESSION['full_name'],
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
