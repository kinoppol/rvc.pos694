<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

use App\Router;
use App\Services\InstallState;

if (!InstallState::isFullyInstalled()) {
    header('Location: ' . (defined('APP_BASE_PATH') ? APP_BASE_PATH : '') . '/install.php');
    exit;
}

$router = new Router();
require APP_ROOT . '/app/Config/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
