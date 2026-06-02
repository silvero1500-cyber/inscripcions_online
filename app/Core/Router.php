<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<string, array{handler: callable|array, middlewares: list<callable>}>> */
    private array $routes = [
        'GET'  => [],
        'POST' => [],
    ];

    /** @var list<callable> */
    private array $globalMiddlewares = [];

    public function get(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    public function middleware(callable $mw): void
    {
        $this->globalMiddlewares[] = $mw;
    }

    private function add(string $method, string $path, callable|array $handler, array $middlewares): void
    {
        $path = '/' . trim($path, '/');
        if ($path === '/') {
            $key = '/';
        } else {
            $key = rtrim($path, '/');
        }
        $this->routes[$method][$key] = [
            'handler'     => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(Request $req): void
    {
        // 1. Buscar match exacto
        $route = $this->routes[$req->method][$req->path] ?? null;

        // 2. Buscar match con parámetros tipo /admin/check-in/{token}
        $params = [];
        if ($route === null) {
            foreach ($this->routes[$req->method] ?? [] as $pattern => $data) {
                if (!str_contains($pattern, '{')) {
                    continue;
                }
                $regex = '#^' . preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern) . '$#';
                if (preg_match($regex, $req->path, $m)) {
                    $route = $data;
                    foreach ($m as $k => $v) {
                        if (!is_int($k)) $params[$k] = $v;
                    }
                    break;
                }
            }
        }

        if ($route === null) {
            if ($req->method !== 'GET' && $req->method !== 'POST') {
                http_response_code(405);
                exit('Method Not Allowed');
            }
            Response::notFound();
        }

        // 3. Pipeline middlewares (global + ruta)
        foreach ([...$this->globalMiddlewares, ...$route['middlewares']] as $mw) {
            $mw($req);   // Middleware abortará con redirect/forbidden si procede
        }

        // 4. Invocar handler
        $handler = $route['handler'];
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $instance = new $class();
            $instance->$method($req, $params);
            return;
        }
        if (is_callable($handler)) {
            $handler($req, $params);
            return;
        }

        Response::serverError('Handler no invocable');
    }
}
