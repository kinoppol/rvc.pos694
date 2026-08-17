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
            header('Location: ' . APP_BASE_PATH . '/install.php');
            exit;
        }
        if (AuthService::check()) {
            header('Location: ' . APP_BASE_PATH . '/pos');
            exit;
        }
        $flash = $_SESSION['register_flash'] ?? null;
        unset($_SESSION['register_flash']);
        View::render('auth/login', ['error' => $_GET['error'] ?? null, 'success' => $flash]);
    }

    public function login(array $args): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $auth = new AuthService(Database::connection());
        $user = $auth->attemptLogin($username, $password);
        if (!$user) {
            $messages = [
                'pending'     => 'ร้านค้าของคุณรอการอนุมัติจากผู้ดูแลระบบ กรุณารอสักครู่',
                'suspended'   => 'ร้านค้าของคุณถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ',
                'credentials' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง',
            ];
            $msg = $messages[$auth->loginError] ?? 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            header('Location: ' . APP_BASE_PATH . '/login?error=' . urlencode($msg));
            exit;
        }
        header('Location: ' . APP_BASE_PATH . '/pos');
        exit;
    }

    public function logout(array $args): void
    {
        // ออกจากระบบระหว่างสวมสิทธิ์ = กลับคืนสู่สิทธิ์ผู้ดูแลระบบ (ไม่ใช่ออกจากระบบจริง)
        if (AuthService::stopImpersonation()) {
            $_SESSION['flash'] = 'ออกจากการสวมสิทธิ์ กลับสู่สิทธิ์ผู้ดูแลระบบแล้ว';
            header('Location: ' . APP_BASE_PATH . '/admin/merchants');
            exit;
        }
        (new AuthService(Database::connection()))->logout();
        header('Location: ' . APP_BASE_PATH . '/login');
        exit;
    }
}
