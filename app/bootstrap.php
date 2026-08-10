<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_STORAGE', APP_ROOT . '/storage');
define('APP_CONFIG_FILE', APP_ROOT . '/app/Config/config.php');
define('APP_INSTALL_LOCK', APP_STORAGE . '/installed.lock');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = APP_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

if (!defined('APP_BASE_PATH')) {
    $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])) : '';
    define('APP_BASE_PATH', $scriptDir === '/' ? '' : rtrim($scriptDir, '/'));
}

date_default_timezone_set('Asia/Bangkok');

if (session_status() === PHP_SESSION_NONE) {
    session_name('pos_session');
    session_start();
}

if (is_file(APP_CONFIG_FILE)) {
    require APP_CONFIG_FILE;
}
