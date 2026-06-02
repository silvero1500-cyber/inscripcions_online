<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public readonly string $method;
    public readonly string $path;
    public readonly string $ip;
    public readonly string $userAgent;

    public function __construct(string $basePath = '')
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Eliminar el prefijo del subdir (ej. /inscripcions)
        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }
        if ($uri === '' || $uri[0] !== '/') {
            $uri = '/' . $uri;
        }

        $this->path = rtrim($uri, '/') ?: '/';

        // IP confiando en el cliente directo (en producción detrás de proxy, ajustar)
        $this->ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $this->userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? null;
        return is_string($v) ? trim($v) : $default;
    }

    public function post(string $key, ?string $default = null): ?string
    {
        $v = $_POST[$key] ?? null;
        return is_string($v) ? trim($v) : $default;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $v = $_GET[$key] ?? null;
        return is_string($v) ? trim($v) : $default;
    }
}
