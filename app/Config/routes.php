<?php
declare(strict_types=1);

use App\Controllers\AdminMerchantController;
use App\Controllers\AttendanceController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\MemberController;
use App\Controllers\MigrationController;
use App\Controllers\PaymentController;
use App\Controllers\PosController;
use App\Controllers\RegisterController;
use App\Controllers\StaffController;
use App\Controllers\StoreController;
use App\Middleware\AuthMiddleware;
use App\Middleware\PlatformAdminMiddleware;
use App\Middleware\RoleMiddleware;
use App\Router;

/** @var Router $router */

$router->get('/', function () {
    header('Location: ' . APP_BASE_PATH . '/pos');
    exit;
});

$router->get('/login', [new AuthController(), 'showLogin']);
$router->post('/login', [new AuthController(), 'login']);
$router->get('/logout', [new AuthController(), 'logout']);
$router->get('/register', [new RegisterController(), 'show']);
$router->post('/register', [new RegisterController(), 'store']);

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

    $router->group([RoleMiddleware::only('owner', 'manager')], function (Router $router) {
        $staff = new StaffController();
        $router->get('/staff', [$staff, 'index']);
        $router->get('/staff/new', [$staff, 'create']);
        $router->post('/staff', [$staff, 'store']);
        $router->get('/staff/{id}/edit', [$staff, 'edit']);
        $router->post('/staff/{id}', [$staff, 'update']);
        $router->post('/staff/{id}/toggle', [$staff, 'toggle']);
    });

    // available to the impersonated (merchant) session, hence outside the platform-admin group
    $router->post('/admin/impersonate/stop', [new AdminMerchantController(), 'stopImpersonate']);

    $router->group([RoleMiddleware::only('owner')], function (Router $router) {
        $store = new StoreController();
        $router->get('/store', [$store, 'index']);
        $router->post('/store', [$store, 'updateProfile']);
        $router->post('/store/branches', [$store, 'branchStore']);
        $router->post('/store/branches/{id}', [$store, 'branchUpdate']);
        $router->post('/store/branches/{id}/delete', [$store, 'branchDelete']);

        $mig = new MigrationController();
        $router->get('/admin/migrations', [$mig, 'index']);
        $router->post('/admin/migrations/run', [$mig, 'run']);
        $router->post('/admin/migrations/rollback', [$mig, 'rollback']);
    });

    $router->group([PlatformAdminMiddleware::handle()], function (Router $router) {
        $adm = new AdminMerchantController();
        $router->get('/admin/merchants', [$adm, 'index']);
        $router->post('/admin/merchants/{id}/approve', [$adm, 'approve']);
        $router->post('/admin/merchants/{id}/suspend', [$adm, 'suspend']);
        $router->post('/admin/merchants/{id}/impersonate', [$adm, 'impersonate']);
        $router->get('/admin/settings', [$adm, 'settings']);
        $router->post('/admin/settings', [$adm, 'saveSettings']);
    });
});
