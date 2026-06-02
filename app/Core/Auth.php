<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Usuario;

final class Auth
{
    private const SESSION_KEY = '_auth_user_id';
    private const ROL_KEY     = '_auth_user_rol';

    public static function attempt(string $email, string $password): bool
    {
        // Rate limit muy básico: 5 intentos por minuto por IP
        if (!self::checkRateLimit()) {
            return false;
        }

        $row = Usuario::findByEmail($email);
        if ($row === null) {
            // Pequeña espera para mitigar timing attack
            usleep(random_int(100_000, 300_000));
            return false;
        }

        if ((int)$row['activo'] !== 1) {
            return false;
        }

        if (!password_verify($password, (string)$row['password_hash'])) {
            return false;
        }

        // Rehash si el algoritmo se ha actualizado
        if (password_needs_rehash($row['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            Database::getInstance()->query(
                'UPDATE usuarios SET password_hash = ? WHERE id = ?',
                [$newHash, $row['id']]
            );
        }

        Session::regenerate();
        Session::set(self::SESSION_KEY, (int)$row['id']);
        Session::set(self::ROL_KEY, (string)$row['rol']);
        Usuario::updateUltimoLogin((int)$row['id']);

        return true;
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::forget(self::ROL_KEY);
        Csrf::rotate();
        Session::regenerate();
    }

    public static function check(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    public static function id(): ?int
    {
        $id = Session::get(self::SESSION_KEY);
        return is_int($id) ? $id : null;
    }

    public static function user(): ?Usuario
    {
        $id = self::id();
        if ($id === null) return null;
        $row = Usuario::findById($id);
        return $row ? Usuario::fromRow($row) : null;
    }

    public static function rol(): ?string
    {
        $r = Session::get(self::ROL_KEY);
        return is_string($r) ? $r : null;
    }

    public static function isSuperadmin(): bool
    {
        return self::rol() === 'superadmin';
    }

    // ── Middlewares listos para usar ────────────────────────

    public static function requireLogin(Request $req): void
    {
        if (!self::check()) {
            Session::flash('redirect_after_login', $req->path);
            Response::redirect(base_url('/admin/login'));
        }
    }

    public static function requireSuperadmin(Request $req): void
    {
        self::requireLogin($req);
        if (!self::isSuperadmin()) {
            Response::forbidden();
        }
    }

    // ── Rate limit muy ligero por IP en sesión ──────────────

    private static function checkRateLimit(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = '_rl_login_' . md5($ip);
        $now = time();
        $window = 60;
        $max = 5;

        $data = Session::get($key, ['count' => 0, 'reset' => $now + $window]);

        if ($now > $data['reset']) {
            $data = ['count' => 0, 'reset' => $now + $window];
        }

        $data['count']++;
        Session::set($key, $data);

        return $data['count'] <= $max;
    }
}
