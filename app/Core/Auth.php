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
        // El throttling anti força bruta es gestiona a AuthController amb
        // LoginThrottle (persistent en BD). Aquí només es verifiquen credencials.
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

    /**
     * Middleware GLOBAL: tanca els usuaris amb rol 'recollida' a la seva zona.
     * Només poden accedir a /admin/recollida* (i login/logout); qualsevol altra
     * ruta /admin els redirigeix a la recollida. Les pàgines públiques es permeten.
     */
    public static function recollidaScope(Request $req): void
    {
        if (self::rol() !== 'recollida') return;
        $p = $req->path;
        if (str_starts_with($p, '/admin/recollida')) return;
        if ($p === '/admin/logout' || $p === '/admin/login') return;
        if (!str_starts_with($p, '/admin')) return; // públic: permès
        Response::redirect(base_url('/admin/recollida'));
    }
}
