<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;

class RoleMiddleware
{
    public static function only(string ...$roles): callable
    {
        return function (callable $next) use ($roles) {
            $user = AuthService::currentUser();
            if (!$user || !in_array($user['role'], $roles, true)) {
                http_response_code(403);
                echo 'ไม่มีสิทธิ์เข้าถึงหน้านี้';
                return;
            }
            $next();
        };
    }
}
