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

    // ── Preus per trams de data (early bird) ─────────────────

    /** Trams de preu d'una tarifa, ordenats. @return list<array<string,mixed>> */
    public static function tramos(int $tarifaId): array
    {
        return Database::getInstance()->query(
            'SELECT * FROM tarifa_precios WHERE tarifa_id = ? ORDER BY orden ASC, id ASC',
            [$tarifaId]
        )->fetchAll();
    }

    /**
     * Trams de preu per a un conjunt de tarifes (carrega en bloc, evita N+1).
     * @param list<int> $tarifaIds
     * @return array<int, list<array<string,mixed>>>  [tarifa_id => trams]
     */
    public static function tramosByTarifas(array $tarifaIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $tarifaIds), fn($n) => $n > 0));
        if ($ids === []) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::getInstance()->query(
            "SELECT * FROM tarifa_precios WHERE tarifa_id IN ($ph) ORDER BY orden ASC, id ASC",
            $ids
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) $out[(int) $r['tarifa_id']][] = $r;
        return $out;
    }

    /**
     * Preu vigent ARA d'una tarifa: el tram amb la data límit més propera que
     * encara no ha passat; si no n'hi ha, el tram sense data; si tampoc, el preu
     * base. La data es compara a nivell de dia en hora local (la data límit
     * s'entén "fins a final d'aquell dia").
     *
     * @param array<string,mixed>            $tarifa
     * @param list<array<string,mixed>>|null $tramos  (si no es passa, es carrega)
     */
    public static function precioVigente(array $tarifa, ?array $tramos = null): float
    {
        if ($tramos === null) $tramos = self::tramos((int) $tarifa['id']);

        $today    = date('Y-m-d');
        $best     = null;
        $bestDate = null;
        $fallback = null;

        foreach ($tramos as $tr) {
            $precio = (float) $tr['precio'];
            $fh     = trim((string) ($tr['fecha_hasta'] ?? ''));
            if ($fh === '') { $fallback = $precio; continue; }
            $fh = substr($fh, 0, 10);
            if ($fh >= $today) { // encara no ha vençut
                if ($bestDate === null || $fh < $bestDate) { $bestDate = $fh; $best = $precio; }
            }
        }

        if ($best !== null) return $best;
        if ($fallback !== null) return $fallback;
        return (float) ($tarifa['precio'] ?? 0);
    }

    /**
     * Reemplaça els trams de preu d'una tarifa (dins una transacció ja oberta).
     * @param list<array{precio:float|string, fecha_hasta:?string}> $tramos
     */
    public static function syncTramos($db, int $tarifaId, array $tramos): void
    {
        $db->query('DELETE FROM tarifa_precios WHERE tarifa_id = ?', [$tarifaId]);
        $orden = 0;
        foreach ($tramos as $tr) {
            $precio = round((float) ($tr['precio'] ?? 0), 2);
            $fh = trim((string) ($tr['fecha_hasta'] ?? ''));
            $fh = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fh) ? $fh : null;
            $db->insert('tarifa_precios', [
                'tarifa_id'   => $tarifaId,
                'precio'      => $precio,
                'fecha_hasta' => $fh,
                'orden'       => $orden++,
            ]);
        }
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
                    'evento_id'     => $eventoId,
                    'nombre'        => $t['nombre'],
                    'descripcion'   => $t['descripcion'],
                    'precio'        => $t['precio'],
                    'aforo_maximo'  => $t['aforo_maximo'],
                    'grupo_aforo_id' => $t['grupo_aforo_id'] ?? null,
                    'anio_nac_min'  => $t['anio_nac_min'] ?? null,
                    'anio_nac_max'  => $t['anio_nac_max'] ?? null,
                    'fecha_inicio'  => $t['fecha_inicio'],
                    'fecha_fin'     => $t['fecha_fin'],
                    'orden'         => $orden,
                    'activo'        => $t['activo'],
                ];

                if (!empty($t['id']) && in_array((int)$t['id'], $existingIds, true)) {
                    $tid = (int) $t['id'];
                    $db->update('tarifas_evento', $payload, ['id' => $tid]);
                    $keepIds[] = $tid;
                } else {
                    $tid = $db->insert('tarifas_evento', $payload);
                    $keepIds[] = $tid;
                }
                // Trams de preu d'aquesta tarifa
                self::syncTramos($db, $tid, $t['tramos'] ?? []);
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
