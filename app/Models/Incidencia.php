<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Incidències enviades des de la bústia del formulari públic. Es desen a més
 * d'enviar-se per correu, perquè quedin llistades a l'admin i no es perdin.
 */
final class Incidencia
{
    /** Crea una incidència. Tolerant: retorna l'id o 0 si falla (no bloqueja l'enviament). */
    public static function crear(?int $eventoId, ?string $eventoNom, string $missatge, ?string $ip): int
    {
        try {
            return Database::getInstance()->insert('incidencias', [
                'evento_id'  => $eventoId,
                'evento_nom' => $eventoNom !== null ? mb_substr($eventoNom, 0, 255) : null,
                'missatge'   => mb_substr($missatge, 0, 3000),
                'estado'     => 'nova',
                'ip'         => $ip,
            ]);
        } catch (\Throwable $e) {
            error_log('[Incidencia] No s\'ha pogut desar: ' . $e->getMessage());
            return 0;
        }
    }

    /** @return list<array<string,mixed>> */
    public static function listRecent(int $limit = 200, ?string $estado = null): array
    {
        $limit = max(1, min(500, $limit));
        $sql = 'SELECT * FROM incidencias';
        $params = [];
        if ($estado !== null && in_array($estado, ['nova', 'resolta'], true)) {
            $sql .= ' WHERE estado = ?';
            $params[] = $estado;
        }
        $sql .= ' ORDER BY (estado = \'nova\') DESC, created_at DESC LIMIT ' . $limit;
        return Database::getInstance()->query($sql, $params)->fetchAll();
    }

    public static function countNoves(): int
    {
        return (int) Database::getInstance()
            ->query("SELECT COUNT(*) FROM incidencias WHERE estado = 'nova'")
            ->fetchColumn();
    }

    public static function marcarResolta(int $id): void
    {
        Database::getInstance()->query(
            "UPDATE incidencias SET estado = 'resolta', resolt_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public static function marcarNova(int $id): void
    {
        Database::getInstance()->query(
            "UPDATE incidencias SET estado = 'nova', resolt_at = NULL WHERE id = ?",
            [$id]
        );
    }

    public static function eliminar(int $id): void
    {
        Database::getInstance()->query('DELETE FROM incidencias WHERE id = ?', [$id]);
    }
}
