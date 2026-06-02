<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Cargador minimalista de fichero .env.
 * Evita dependencia externa (vlucas/phpdotenv) para el bootstrap.
 */
final class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("No se puede leer el fichero .env en: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Eliminar comillas opcionales
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $m)) {
                $value = $m[2];
            }

            if (!array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
                putenv("{$key}={$value}");
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        return match (strtolower((string)$value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    public static function require(string $key): string
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            throw new RuntimeException("Variable de entorno requerida no definida: {$key}");
        }

        return (string)$value;
    }
}
