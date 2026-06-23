<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Registre d'auditoria de les accions de recollida de dorsals: qui (snapshot del
 * nom + id), què (recollit/desfet/dorsal...), a qui i quan. Tolerant a errors:
 * mai bloqueja l'acció principal si el registre falla.
 */
final class RecollidaLog
{
    public static function registrar(int $eventoId, array $inscrito, $user, string $accion, ?int $dorsal = null): void
    {
        try {
            Database::getInstance()->insert('recollida_log', [
                'evento_id'    => $eventoId,
                'inscrito_id'  => (int) ($inscrito['id'] ?? 0) ?: null,
                'inscrito_nom' => mb_substr(trim(($inscrito['nombre'] ?? '') . ' ' . ($inscrito['apellido'] ?? '')), 0, 255),
                'dorsal'       => $dorsal,
                'usuario_id'   => $user->id ?? null,
                'usuario_nom'  => mb_substr((string) ($user->nombre ?? ''), 0, 150),
                'accion'       => mb_substr($accion, 0, 30),
            ]);
        } catch (\Throwable $e) {
            error_log('[RecollidaLog] ' . $e->getMessage());
        }
    }

    /** @return list<array<string,mixed>> */
    public static function listByEvento(int $eventoId, int $limit = 500): array
    {
        try {
            return Database::getInstance()->query(
                'SELECT * FROM recollida_log WHERE evento_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit),
                [$eventoId]
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
