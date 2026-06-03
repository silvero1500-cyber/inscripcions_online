<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Grup d'aforament compartit: diverses tarifes resten d'un mateix cupo.
 * Una tarifa amb `grupo_aforo_id` ignora el seu aforo propi i compta contra el grup.
 */
final class GrupoAforo
{
    /** @return list<array<string,mixed>> */
    public static function listByEvento(int $eventoId): array
    {
        return Database::getInstance()->query(
            'SELECT * FROM grupos_aforo WHERE evento_id = ? ORDER BY id ASC',
            [$eventoId]
        )->fetchAll();
    }

    /**
     * Reemplaça els grups d'un esdeveniment. Atòmic.
     * Retorna el mapa [cid => grupoId] per enllaçar les tarifes.
     *
     * @param list<array{id?:int, cid?:string, nombre:string, aforo_maximo:int}> $grupos
     * @return array<string,int>
     */
    public static function syncForEvento(int $eventoId, array $grupos): array
    {
        $db = Database::getInstance();
        $map = [];

        $db->transaction(function ($db) use ($eventoId, $grupos, &$map): void {
            $existingIds = array_map('intval', $db->query(
                'SELECT id FROM grupos_aforo WHERE evento_id = ?',
                [$eventoId]
            )->fetchAll(\PDO::FETCH_COLUMN));

            $keepIds = [];
            foreach ($grupos as $g) {
                $payload = [
                    'evento_id'    => $eventoId,
                    'nombre'       => mb_substr((string)$g['nombre'], 0, 100),
                    'aforo_maximo' => max(1, (int)$g['aforo_maximo']),
                ];

                if (!empty($g['id']) && in_array((int)$g['id'], $existingIds, true)) {
                    $gid = (int)$g['id'];
                    $db->update('grupos_aforo', $payload, ['id' => $gid]);
                } else {
                    $gid = $db->insert('grupos_aforo', $payload);
                }
                $keepIds[] = $gid;
                if (!empty($g['cid'])) {
                    $map[(string)$g['cid']] = $gid;
                }
            }

            // Esborrar els que ja no hi són (FK ON DELETE SET NULL deixa les tarifes independents)
            foreach (array_diff($existingIds, $keepIds) as $id) {
                $db->query('DELETE FROM grupos_aforo WHERE id = ?', [$id]);
            }
        });

        return $map;
    }

    /**
     * Capacitat d'un grup DINS d'una transacció: bloqueja la fila del grup
     * (FOR UPDATE, serialitza inscripcions concurrents al mateix grup) i compta
     * inscrits actius de TOTES les seves tarifes.
     */
    public static function tieneCapacidad(int $grupoId): bool
    {
        $db = Database::getInstance();
        $aforo = $db->query(
            'SELECT aforo_maximo FROM grupos_aforo WHERE id = ? FOR UPDATE',
            [$grupoId]
        )->fetchColumn();

        if ($aforo === false || $aforo === null) return true; // grup inexistent → no bloqueja

        $usadas = (int) $db->query(
            "SELECT COUNT(*) FROM inscritos i
             JOIN tarifas_evento t ON t.id = i.tarifa_id
             WHERE t.grupo_aforo_id = ? AND i.estado IN ('pendiente', 'confirmado')",
            [$grupoId]
        )->fetchColumn();

        return $usadas < (int) $aforo;
    }

    /**
     * Places restants per grup d'un esdeveniment (no bloquejant, per mostrar esgotats).
     * @return array<int,int>  grupoId => restants (>= 0)
     */
    public static function plazasRestantesByEvento(int $eventoId): array
    {
        $rows = Database::getInstance()->query(
            "SELECT g.id, g.aforo_maximo,
                    (SELECT COUNT(*) FROM inscritos i
                       JOIN tarifas_evento t ON t.id = i.tarifa_id
                      WHERE t.grupo_aforo_id = g.id AND i.estado IN ('pendiente', 'confirmado')) AS usadas
             FROM grupos_aforo g
             WHERE g.evento_id = ?",
            [$eventoId]
        )->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['id']] = max(0, (int)$r['aforo_maximo'] - (int)$r['usadas']);
        }
        return $out;
    }
}
