<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthService;

class AuthMiddleware
{
    public static function handle(): callable
    {
        return function (callable $next) {
            if (!AuthService::check()) {
                header('Location: ' . APP_BASE_PATH . '/login');
                exit;
            }
            $next();
        };
    }
}
