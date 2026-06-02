<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $url, int $code = 303): never
    {
        header("Location: {$url}", true, $code);
        exit;
    }

    public static function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function text(string $body, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $body;
        exit;
    }

    public static function notFound(): never
    {
        http_response_code(404);
        View::render('errors/404', []);
        exit;
    }

    public static function forbidden(): never
    {
        http_response_code(403);
        View::render('errors/403', []);
        exit;
    }

    public static function serverError(string $msg = ''): never
    {
        http_response_code(500);
        View::render('errors/500', ['msg' => $msg]);
        exit;
    }
}
