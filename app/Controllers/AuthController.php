<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\Database;
use App\Services\InstallState;
use App\Services\View;

class AuthController
{
    public function showLogin(array $args): void
    {
        if (!InstallState::isFullyInstalled()) {
            header('Location: /install.php');
            exit;
        }
        if (AuthService::check()) {
            header('Location: /pos');
            exit;
        }
        View::render('auth/login', ['error' => $_GET['error'] ?? null]);
    }

    public function login(array $args): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $auth = new AuthService(Database::connection());
        $user = $auth->attemptLogin($username, $password);
        if (!$user) {
            header('Location: /login?error=' . urlencode('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'));
            exit;
        }
        header('Location: /pos');
        exit;
    }

    public function logout(array $args): void
    {
        (new AuthService(Database::connection()))->logout();
        header('Location: /login');
        exit;
    }
}
