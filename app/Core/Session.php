<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(bool $secureCookie = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('IOSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start([
            'use_strict_mode'    => true,
            'use_only_cookies'   => true,
            'cookie_httponly'    => true,
            'cookie_secure'      => $secureCookie,
            'cookie_samesite'    => 'Lax',
            'sid_length'         => 48,
            'sid_bits_per_character' => 6,
            'gc_maxlifetime'     => 7200,
        ]);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], (bool)$p['secure'], (bool)$p['httponly']);
        }
        session_destroy();
    }

    // ── Flash messages ──────────────────────────────────────

    public static function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    public static function pullFlash(string $key): ?string
    {
        $msg = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $msg;
    }

    public static function pullAllFlashes(): array
    {
        $all = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $all;
    }
}
