<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;

class PlatformAdminMiddleware
{
    public static function handle(): callable
    {
        return function (callable $next) {
            $user = AuthService::currentUser();
            if (!$user || !($user['is_platform'] ?? false) || $user['role'] !== 'owner') {
                http_response_code(403);
                echo 'ไม่มีสิทธิ์เข้าถึงหน้านี้';
                return;
            }
            $next();
        };
    }
}
