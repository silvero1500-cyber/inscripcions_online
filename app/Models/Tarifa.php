<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Tarifa
{
    /** @return list<array<string,mixed>> */
    public static function listByEvento(int $eventoId): array
    {
        return Database::getInstance()
            ->query(
                'SELECT * FROM tarifas_evento
                 WHERE evento_id = ?
                 ORDER BY orden ASC, id ASC',
                [$eventoId]
            )->fetchAll();
    }

    /**
     * Tarifas disponibles ahora mismo para el público: activas y dentro de su ventana de venta.
     * @return list<array<string,mixed>>
     */
    public static function listDisponibles(int $eventoId): array
    {
        $now = date('Y-m-d H:i:s');
        return Database::getInstance()->query(
            "SELECT * FROM tarifas_evento
             WHERE evento_id = ?
               AND activo = 1
               AND (fecha_inicio IS NULL OR fecha_inicio <= ?)
               AND (fecha_fin    IS NULL OR fecha_fin    >= ?)
             ORDER BY orden ASC, id ASC",
            [$eventoId, $now, $now]
        )->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM tarifas_evento WHERE id = ?', [$id])
            ->fetch();
        return $row ?: null;
    }

    /**
     * Comprueba que una tarifa pertenece a un evento concreto y está disponible.
     * Devuelve la fila si es válida, null si no.
     */
    public static function findDisponibleForEvento(int $tarifaId, int $eventoId): ?array
    {
        $now = date('Y-m-d H:i:s');
        $row = Database::getInstance()->query(
            "SELECT * FROM tarifas_evento
             WHERE id = ? AND evento_id = ?
               AND activo = 1
               AND (fecha_inicio IS NULL OR fecha_inicio <= ?)
               AND (fecha_fin    IS NULL OR fecha_fin    >= ?)
             LIMIT 1",
            [$tarifaId, $eventoId, $now, $now]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Verifica si una tarifa con aforo propio aún tiene plazas disponibles.
     * Si la tarifa no tiene aforo propio, devuelve true.
     */
    public static function tienePlazasDisponibles(int $tarifaId): bool
    {
        $t = self::findById($tarifaId);
        if ($t === null) return false;
        return self::tieneCapacidad($t);
    }

    /**
     * Igual que findDisponibleForEvento pero con SELECT FOR UPDATE.
     * Debe llamarse dentro de una transacción activa para bloquear la fila
     * y evitar race conditions cuando el aforo es limitado.
     */
    public static function findAndLockForInscripcion(int $tarifaId, int $eventoId): ?array
    {
        $now = date('Y-m-d H:i:s');
        $row = Database::getInstance()->query(
            "SELECT * FROM tarifas_evento
             WHERE id = ? AND evento_id = ?
               AND activo = 1
               AND (fecha_inicio IS NULL OR fecha_inicio <= ?)
               AND (fecha_fin    IS NULL OR fecha_fin    >= ?)
             LIMIT 1 FOR UPDATE",
            [$tarifaId, $eventoId, $now, $now]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Comprueba la capacidad a partir de una fila de tarifa ya cargada.
     * Útil dentro de una transacción donde la fila ya está bloqueada.
     */
    public static function tieneCapacidad(array $tarifa): bool
    {
        if (empty($tarifa['aforo_maximo'])) return true;

        $usadas = (int) Database::getInstance()->query(
            "SELECT COUNT(*) FROM inscritos
             WHERE tarifa_id = ? AND estado IN ('pendiente', 'confirmado')",
            [(int) $tarifa['id']]
        )->fetchColumn();

        return $usadas < (int) $tarifa['aforo_maximo'];
    }

    /**
     * Places que queden en una tarifa amb aforament propi.
     * Retorna null si la tarifa no té aforament propi (places il·limitades).
     */
    public static function plazasRestantes(array $tarifa): ?int
    {
        if (empty($tarifa['aforo_maximo'])) return null;

        $usadas = (int) Database::getInstance()->query(
            "SELECT COUNT(*) FROM inscritos
             WHERE tarifa_id = ? AND estado IN ('pendiente', 'confirmado')",
            [(int) $tarifa['id']]
        )->fetchColumn();

        return max(0, (int) $tarifa['aforo_maximo'] - $usadas);
    }

    /**
     * Devuelve el precio mínimo entre las tarifas activas disponibles ahora.
     * Útil para mostrar "des de X €" en el listado.
     */
    public static function precioMinimo(int $eventoId): ?float
    {
        $now = date('Y-m-d H:i:s');
        $min = Database::getInstance()->query(
            "SELECT MIN(precio) FROM tarifas_evento
             WHERE evento_id = ?
               AND activo = 1
               AND (fecha_inicio IS NULL OR fecha_inicio <= ?)
               AND (fecha_fin    IS NULL OR fecha_fin    >= ?)",
            [$eventoId, $now, $now]
        )->fetchColumn();

        return $min === false || $min === null ? null : (float) $min;
    }

    /**
     * Reemplaza todas las tarifas de un evento. Atómico.
     * Si una tarifa enviada tiene `id` >= 1 y existía, se actualiza; las que no
     * vienen en el nuevo set se borran (solo si no tienen inscritos).
     *
     * @param list<array{id?:int, nombre:string, descripcion:?string, precio:string|float, aforo_maximo:?int, fecha_inicio:?string, fecha_fin:?string, activo:int}> $tarifas
     */
    public static function syncForEvento(int $eventoId, array $tarifas): void
    {
        $db = Database::getInstance();

        $db->transaction(function ($db) use ($eventoId, $tarifas): void {
            // Obtener IDs actuales
            $existingIds = array_map('intval', $db->query(
                'SELECT id FROM tarifas_evento WHERE evento_id = ?',
                [$eventoId]
            )->fetchAll(\PDO::FETCH_COLUMN));

            $keepIds = [];
            foreach ($tarifas as $orden => $t) {
                $payload = [
                    'evento_id'    => $eventoId,
                    'nombre'       => $t['nombre'],
                    'descripcion'  => $t['descripcion'],
                    'precio'       => $t['precio'],
                    'aforo_maximo' => $t['aforo_maximo'],
                    'fecha_inicio' => $t['fecha_inicio'],
                    'fecha_fin'    => $t['fecha_fin'],
                    'orden'        => $orden,
                    'activo'       => $t['activo'],
                ];

                if (!empty($t['id']) && in_array((int)$t['id'], $existingIds, true)) {
                    $tid = (int) $t['id'];
                    $db->update('tarifas_evento', $payload, ['id' => $tid]);
                    $keepIds[] = $tid;
                } else {
                    $newId = $db->insert('tarifas_evento', $payload);
                    $keepIds[] = $newId;
                }
            }

            // Borrar las que ya no estén — pero solo si no tienen inscritos
            $toDelete = array_diff($existingIds, $keepIds);
            foreach ($toDelete as $id) {
                $used = (int) $db->query(
                    'SELECT COUNT(*) FROM inscritos WHERE tarifa_id = ?',
                    [$id]
                )->fetchColumn();
                if ($used === 0) {
                    $db->query('DELETE FROM tarifas_evento WHERE id = ?', [$id]);
                } else {
                    // Si tiene inscritos, marcar como inactiva en lugar de borrar
                    $db->update('tarifas_evento', ['activo' => 0], ['id' => $id]);
                }
            }
        });
    }
}
