<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Evento
{
    /**
     * Lista eventos visibles para un usuario.
     * - Superadmin: todos
     * - Organizador: solo los de la tabla organizador_evento + los que él es propietario
     */
    public static function listForUser(int $userId, string $rol, bool $onlyArchived = false): array
    {
        $db = Database::getInstance();
        // Condició d'arxivat (cadena fixa, sense input d'usuari)
        $arch = $onlyArchived ? 'e.archivado_at IS NOT NULL' : 'e.archivado_at IS NULL';

        if ($rol === 'superadmin') {
            return $db->query(
                "SELECT e.*, u.nombre AS propietario_nombre,
                        (SELECT MIN(precio) FROM tarifas_evento WHERE evento_id = e.id AND activo = 1) AS precio_min,
                        (SELECT COUNT(*)   FROM tarifas_evento WHERE evento_id = e.id AND activo = 1) AS tarifas_activas
                 FROM eventos e
                 JOIN usuarios u ON u.id = e.propietario_id
                 WHERE {$arch}
                 ORDER BY e.fecha_evento DESC, e.id DESC"
            )->fetchAll();
        }

        return $db->query(
            "SELECT DISTINCT e.*, u.nombre AS propietario_nombre,
                    (SELECT MIN(precio) FROM tarifas_evento WHERE evento_id = e.id AND activo = 1) AS precio_min,
                    (SELECT COUNT(*)   FROM tarifas_evento WHERE evento_id = e.id AND activo = 1) AS tarifas_activas
             FROM eventos e
             JOIN usuarios u ON u.id = e.propietario_id
             LEFT JOIN organizador_evento oe ON oe.evento_id = e.id
             WHERE (e.propietario_id = :uid OR oe.usuario_id = :uid) AND {$arch}
             ORDER BY e.fecha_evento DESC, e.id DESC",
            ['uid' => $userId]
        )->fetchAll();
    }

    public static function archive(int $id): int
    {
        return Database::getInstance()->update('eventos', ['archivado_at' => date('Y-m-d H:i:s')], ['id' => $id]);
    }

    public static function unarchive(int $id): int
    {
        return Database::getInstance()->update('eventos', ['archivado_at' => null], ['id' => $id]);
    }

    public static function findById(int $id): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM eventos WHERE id = ?', [$id])
            ->fetch();
        return $row ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM eventos WHERE slug = ?', [$slug])
            ->fetch();
        return $row ?: null;
    }

    public static function userCanEdit(int $userId, string $rol, int $eventoId): bool
    {
        if ($rol === 'superadmin') return true;
        $db = Database::getInstance();
        $row = $db->query(
            'SELECT 1 FROM eventos WHERE id = :eid AND propietario_id = :uid
             UNION
             SELECT 1 FROM organizador_evento WHERE evento_id = :eid AND usuario_id = :uid AND puede_editar = 1
             LIMIT 1',
            ['eid' => $eventoId, 'uid' => $userId]
        )->fetchColumn();
        return (bool) $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        return Database::getInstance()->insert('eventos', $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): int
    {
        return Database::getInstance()->update('eventos', $data, ['id' => $id]);
    }

    public static function delete(int $id): int
    {
        $stmt = Database::getInstance()->query('DELETE FROM eventos WHERE id = ?', [$id]);
        return $stmt->rowCount();
    }

    public static function countInscritos(int $eventoId): int
    {
        return (int) Database::getInstance()
            ->query("SELECT COUNT(*) FROM inscritos WHERE evento_id = ? AND estado = 'confirmado'", [$eventoId])
            ->fetchColumn();
    }

    public static function isSlugTaken(string $slug, ?int $exceptId = null): bool
    {
        $db = Database::getInstance();
        if ($exceptId === null) {
            return (bool) $db->query('SELECT 1 FROM eventos WHERE slug = ?', [$slug])->fetchColumn();
        }
        return (bool) $db->query('SELECT 1 FROM eventos WHERE slug = ? AND id <> ?', [$slug, $exceptId])->fetchColumn();
    }
}
