<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use RuntimeException;

/**
 * Cupons de descompte per evento.
 * Codis sempre en majúscules. Comprovació de validesa via SELECT FOR UPDATE
 * dins de la transacció de creació d'inscripció per evitar race conditions
 * quan usos_max és limitat.
 */
final class DescuentoEvento
{
    public static function findById(int $id): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM descuentos_evento WHERE id = ?', [$id])
            ->fetch();
        return $row ?: null;
    }

    /**
     * Cerca codi pel parell (evento_id, codigo) — sense bloqueig, per pre-validació.
     */
    public static function findByCodigo(int $eventoId, string $codigo): ?array
    {
        $row = Database::getInstance()->query(
            'SELECT * FROM descuentos_evento WHERE evento_id = ? AND codigo = ? LIMIT 1',
            [$eventoId, strtoupper(trim($codigo))]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Versió amb FOR UPDATE: per usar dins la transacció d'inscripció.
     */
    public static function findAndLockForUse(int $eventoId, string $codigo): ?array
    {
        $row = Database::getInstance()->query(
            'SELECT * FROM descuentos_evento WHERE evento_id = ? AND codigo = ? LIMIT 1 FOR UPDATE',
            [$eventoId, strtoupper(trim($codigo))]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Comprova si un descuento (fila ja carregada) és vàlid ara mateix.
     * Retorna [valid:bool, error:?string].
     * @return array{valid:bool, error:?string}
     */
    public static function checkUsable(array $d): array
    {
        if ((int)$d['activo'] !== 1) {
            return ['valid' => false, 'error' => 'El codi no està actiu.'];
        }
        $now = time();
        if (!empty($d['valido_desde']) && strtotime((string)$d['valido_desde']) > $now) {
            return ['valid' => false, 'error' => 'El codi encara no és vàlid.'];
        }
        if (!empty($d['valido_hasta']) && strtotime((string)$d['valido_hasta']) < $now) {
            return ['valid' => false, 'error' => 'El codi ha caducat.'];
        }
        if ($d['usos_max'] !== null && (int)$d['usos_actuales'] >= (int)$d['usos_max']) {
            return ['valid' => false, 'error' => 'El codi ha arribat al màxim d\'usos.'];
        }
        return ['valid' => true, 'error' => null];
    }

    public static function incrementarUsos(int $id): void
    {
        Database::getInstance()->query(
            'UPDATE descuentos_evento SET usos_actuales = usos_actuales + 1 WHERE id = ?',
            [$id]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function listByEvento(int $eventoId): array
    {
        return Database::getInstance()->query(
            'SELECT * FROM descuentos_evento WHERE evento_id = ? ORDER BY activo DESC, created_at DESC',
            [$eventoId]
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function listByLote(int $eventoId, string $lote): array
    {
        return Database::getInstance()->query(
            'SELECT * FROM descuentos_evento WHERE evento_id = ? AND lote = ? ORDER BY codigo ASC',
            [$eventoId, $lote]
        )->fetchAll();
    }

    public static function toggleActivo(int $id, int $activo): void
    {
        Database::getInstance()->update(
            'descuentos_evento',
            ['activo' => $activo === 1 ? 1 : 0],
            ['id' => $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query('DELETE FROM descuentos_evento WHERE id = ?', [$id]);
    }

    /**
     * Genera un lot de N codis únics amb un prefix opcional.
     * Retorna la llista dels codis generats (només els nous, els que ja existien es salten).
     *
     * @param array{
     *   evento_id: int,
     *   cantidad: int,
     *   porcentaje: float,
     *   prefijo?: string,
     *   usos_max?: ?int,
     *   valido_desde?: ?string,
     *   valido_hasta?: ?string,
     *   nota?: ?string
     * } $opts
     * @return list<string>
     */
    public static function generarLote(array $opts): array
    {
        $eventoId  = (int) $opts['evento_id'];
        $cantidad  = max(1, min(500, (int) $opts['cantidad']));
        $porc      = round((float) $opts['porcentaje'], 2);
        $prefijo   = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $opts['prefijo'] ?? '') ?? '');
        $usosMax   = isset($opts['usos_max']) && $opts['usos_max'] !== '' && $opts['usos_max'] !== null
                     ? (int) $opts['usos_max'] : null;
        $vDesde    = !empty($opts['valido_desde']) ? $opts['valido_desde'] : null;
        $vHasta    = !empty($opts['valido_hasta']) ? $opts['valido_hasta'] : null;
        $nota      = $opts['nota'] ?? null;

        if ($porc <= 0 || $porc > 100) {
            throw new RuntimeException('Percentatge fora de rang (0-100).');
        }

        $lote = strtolower(bin2hex(random_bytes(4))); // identificador del batch

        $db = Database::getInstance();
        $creados = [];
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sense I, O, 0, 1 (confusió visual)

        $db->transaction(function ($db) use (&$creados, $eventoId, $cantidad, $porc, $prefijo, $usosMax, $vDesde, $vHasta, $nota, $lote, $alphabet) {
            $generats = 0;
            $intents = 0;
            $maxIntents = $cantidad * 5;

            while ($generats < $cantidad && $intents < $maxIntents) {
                $intents++;
                // Generar codi: prefix-suffix (8 chars del alphabet)
                $suffix = '';
                for ($j = 0; $j < 8; $j++) {
                    $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $codigo = $prefijo !== '' ? ($prefijo . '-' . $suffix) : $suffix;
                $codigo = mb_substr($codigo, 0, 40);

                // Comprovar unicitat dins el evento (UNIQUE constraint també protegeix, però evitem warning)
                $exists = $db->query(
                    'SELECT 1 FROM descuentos_evento WHERE evento_id = ? AND codigo = ? LIMIT 1',
                    [$eventoId, $codigo]
                )->fetchColumn();
                if ($exists) continue;

                try {
                    $db->insert('descuentos_evento', [
                        'evento_id'    => $eventoId,
                        'codigo'       => $codigo,
                        'porcentaje'   => $porc,
                        'usos_max'     => $usosMax,
                        'valido_desde' => $vDesde,
                        'valido_hasta' => $vHasta,
                        'activo'       => 1,
                        'lote'         => $lote,
                        'nota'         => $nota,
                    ]);
                    $creados[] = $codigo;
                    $generats++;
                } catch (\Throwable $e) {
                    // Probable colisió UNIQUE, continuar
                    continue;
                }
            }
        });

        return $creados;
    }
}
