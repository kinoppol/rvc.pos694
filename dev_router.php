<?php
// Dev-only router for `php -S`, mimicking the .htaccess rules (serve real
// files as-is, otherwise hand off to index.php). Not used in production.
$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}
require __DIR__ . '/index.php';
