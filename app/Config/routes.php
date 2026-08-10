<?php
declare(strict_types=1);

use App\Controllers\AttendanceController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\MemberController;
use App\Controllers\MigrationController;
use App\Controllers\PaymentController;
use App\Controllers\PosController;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Router;

/** @var Router $router */

$router->get('/', function () {
    header('Location: /pos');
    exit;
});

$router->get('/login', [new AuthController(), 'showLogin']);
$router->post('/login', [new AuthController(), 'login']);
$router->get('/logout', [new AuthController(), 'logout']);

$router->group([AuthMiddleware::handle()], function (Router $router) {
    $pos = new PosController();
    $router->get('/pos', [$pos, 'index']);
    $router->get('/pos/search', [$pos, 'search']);
    $router->post('/pos/cart/add', [$pos, 'cartAdd']);
    $router->post('/pos/cart/update', [$pos, 'cartUpdate']);
    $router->post('/pos/cart/clear', [$pos, 'cartClear']);
    $router->post('/pos/coupon', [$pos, 'applyCoupon']);
    $router->post('/pos/member', [$pos, 'setMember']);
    $router->post('/pos/hold', [$pos, 'hold']);

    $pay = new PaymentController();
    $router->get('/pos/pay', [$pay, 'show']);
    $router->post('/pos/pay/confirm', [$pay, 'confirm']);

    $dash = new DashboardController();
    $router->get('/dashboard', [$dash, 'index']);

    $att = new AttendanceController();
    $router->get('/attendance', [$att, 'index']);
    $router->post('/attendance/clock', [$att, 'clock']);

    $mem = new MemberController();
    $router->get('/members', [$mem, 'index']);
    $router->get('/members/{id}', [$mem, 'show']);
    $router->post('/members/{id}/use', [$mem, 'useWithCart']);

    $router->group([RoleMiddleware::only('owner')], function (Router $router) {
        $mig = new MigrationController();
        $router->get('/admin/migrations', [$mig, 'index']);
        $router->post('/admin/migrations/run', [$mig, 'run']);
        $router->post('/admin/migrations/rollback', [$mig, 'rollback']);
    });
});
