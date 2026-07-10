<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Preferències d'interfície per usuari (persistents al servidor), com la
 * configuració de columnes del grid d'inscrits per evento. Tolerant: si la
 * taula encara no existeix (migració pendent), retorna null i no peta.
 */
final class UserPref
{
    public static function get(int $usuarioId, string $key): ?string
    {
        try {
            $v = Database::getInstance()->query(
                'SELECT valor FROM user_prefs WHERE usuario_id = ? AND pref_key = ?',
                [$usuarioId, $key]
            )->fetchColumn();
            return $v === false ? null : (string) $v;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function set(int $usuarioId, string $key, string $valor): void
    {
        try {
            Database::getInstance()->query(
                'INSERT INTO user_prefs (usuario_id, pref_key, valor) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
                [$usuarioId, mb_substr($key, 0, 100), $valor]
            );
        } catch (\Throwable $e) {
            error_log('[UserPref] set: ' . $e->getMessage());
        }
    }
}
