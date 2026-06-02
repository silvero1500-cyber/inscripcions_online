<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $t = Session::get(self::KEY);
        if (!is_string($t) || $t === '') {
            $t = bin2hex(random_bytes(32));
            Session::set(self::KEY, $t);
        }
        return $t;
    }

    public static function verify(?string $submitted): bool
    {
        $stored = Session::get(self::KEY);
        if (!is_string($stored) || !is_string($submitted)) {
            return false;
        }
        return hash_equals($stored, $submitted);
    }

    public static function rotate(): void
    {
        Session::forget(self::KEY);
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
