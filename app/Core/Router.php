<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $uri, array|callable $handler, array $middleware = []): void
    {
        $this->add('GET', $uri, $handler, $middleware);
    }

    public function post(string $uri, array|callable $handler, array $middleware = []): void
    {
        $this->add('POST', $uri, $handler, $middleware);
    }

    private function add(string $method, string $uri, array|callable $handler, array $middleware): void
    {
        $this->routes[$method][$this->normalizePath($uri)] = [
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = $this->normalizePath(parse_url($uri, PHP_URL_PATH) ?: '/');
        $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

        if ($base !== '/' && $base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
            $path = $this->normalizePath($path);
        }

        $route = $this->routes[$method][$path] ?? null;

        if ($route === null) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Page introuvable'], 'layouts/auth');
            return;
        }

        $this->runMiddleware($route['middleware']);
        $this->call($route['handler']);
    }

    private function runMiddleware(array $middleware): void
    {
        foreach ($middleware as $name) {
            if ($name === 'auth' && !Auth::check()) {
                redirect('/login');
            }

            if ($name === 'guest' && Auth::check()) {
                redirect('/dashboard');
            }

            if (str_starts_with($name, 'permission:')) {
                $permission = substr($name, strlen('permission:'));

                if (!Auth::can($permission)) {
                    flash('error', 'Accès refusé pour cette action.');
                    redirect('/dashboard');
                }
            }
        }
    }

    private function call(array|callable $handler): void
    {
        if (is_callable($handler)) {
            $handler();
            return;
        }

        [$class, $method] = $handler;
        (new $class())->{$method}();
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '//' ? '/' : $path;
    }
}
