<?php
declare(strict_types=1);

namespace App;

/**
 * Minimal dependency-free router. Chosen over a Composer-based framework
 * (e.g. Slim) so the app runs on plain PHP 8 + MariaDB hosting with no
 * `composer install` step required at deploy time.
 */
class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, params:array, handler:callable, middleware:array}> */
    private array $routes = [];

    /** @var callable[] */
    private array $groupMiddleware = [];

    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    private function add(string $method, string $pattern, callable $handler, array $middleware): void
    {
        $paramNames = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) use (&$paramNames) {
            $paramNames[] = $m[1];
            return '([^/]+)';
        }, $pattern);
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => $regex,
            'params' => $paramNames,
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    public function group(array $middleware, callable $callback): void
    {
        $previous = $this->groupMiddleware;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        $callback($this);
        $this->groupMiddleware = $previous;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = defined('APP_BASE_PATH') ? APP_BASE_PATH : '';
        if ($base !== '' && strpos($path, $base) === 0) {
            $path = substr($path, strlen($base));
        }
        if ($path === '' ) {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches);
                $args = array_combine($route['params'], $matches) ?: [];

                $next = function () use ($route, $args) {
                    ($route['handler'])($args);
                };
                foreach (array_reverse($route['middleware']) as $mw) {
                    $next = function () use ($mw, $next) {
                        $mw($next);
                    };
                }
                $next();
                return;
            }
        }

        http_response_code(404);
        echo '404 Not Found: ' . htmlspecialchars($path);
    }
}
