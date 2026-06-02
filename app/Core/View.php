<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    private static string $viewsPath = '';
    private static ?string $layout = null;
    private static string $content = '';

    public static function setViewsPath(string $path): void
    {
        self::$viewsPath = rtrim($path, '/\\');
    }

    /**
     * Renderiza una vista y la imprime.
     *
     * @param array<string,mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = null): void
    {
        $content = self::capture($view, $data);

        if ($layout !== null) {
            $data['content'] = $content;
            echo self::capture("layouts/{$layout}", $data);
            return;
        }

        echo $content;
    }

    /**
     * Renderiza una vista a string sin layout (útil para emails, JSON, etc.).
     *
     * @param array<string,mixed> $data
     */
    public static function renderToString(string $view, array $data = []): string
    {
        return self::capture($view, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function capture(string $view, array $data): string
    {
        $file = self::$viewsPath . '/' . str_replace('.', '/', $view) . '.php';

        if (!is_file($file)) {
            throw new \RuntimeException("Vista no encontrada: {$view}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
