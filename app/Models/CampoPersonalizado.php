<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class CampoPersonalizado
{
    public const TIPOS_VALIDOS = [
        'text', 'textarea', 'select', 'checkbox', 'radio',
        'date', 'number', 'email', 'tel',
    ];

    /** @return list<array<string,mixed>> */
    public static function listByEvento(int $eventoId): array
    {
        return Database::getInstance()
            ->query(
                'SELECT * FROM campos_personalizados
                 WHERE evento_id = ?
                 ORDER BY orden ASC, id ASC',
                [$eventoId]
            )->fetchAll();
    }

    /**
     * Campos activos y VISIBLES de un evento para el formulario público.
     * Los campos ocultos (oculto = 1) se excluyen: existen solo como columna
     * en el CSV de exportación/importación, no se piden al corredor.
     * @return list<array<string,mixed>>
     */
    public static function getActivosPorEvento(int $eventoId): array
    {
        return Database::getInstance()
            ->query(
                'SELECT * FROM campos_personalizados
                 WHERE evento_id = ? AND activo = 1 AND oculto = 0
                 ORDER BY orden ASC, id ASC',
                [$eventoId]
            )->fetchAll();
    }

    /**
     * Valores de los campos personalizados para un conjunto de inscritos.
     * @param list<int> $inscritoIds
     * @return array<int, array<int, string>>  [inscrito_id => [campo_id => valor]]
     */
    public static function valoresPorInscrito(array $inscritoIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $inscritoIds), fn($n) => $n > 0));
        if ($ids === []) return [];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::getInstance()->query(
            "SELECT inscrito_id, campo_id, valor
             FROM inscrito_campos_valores
             WHERE inscrito_id IN ($ph)",
            $ids
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['inscrito_id']][(int) $r['campo_id']] = (string) ($r['valor'] ?? '');
        }
        return $out;
    }

    /**
     * Reemplaza todos los campos personalizados de un evento por la lista nueva.
     * Atómico: si algo falla, rollback.
     *
     * @param list<array{nombre_campo:string, etiqueta:string, tipo:string, opciones:?string, requerido:int, orden:int}> $campos
     */
    /**
     * Sincronitza els camps personalitzats SENSE destruir dades: els camps que
     * arriben amb `id` existent s'ACTUALITZEN (conserven l'id i, per tant, les
     * respostes ja desades a inscrito_campos_valores); els nous s'insereixen; i
     * els que ja no vénen només s'esborren si cap inscrit hi té resposta (si en
     * tenen, es desactiven per no perdre les dades).
     * Retorna la llista d'ids EN ORDRE (per enllaçar l'ordre del formulari).
     * @return list<int>
     */
    public static function syncForEvento(int $eventoId, array $campos): array
    {
        $db = Database::getInstance();
        return $db->transaction(function ($db) use ($eventoId, $campos): array {
            // Tarifes vàlides de l'esdeveniment (per validar el camp condicional)
            $tarifasValides = array_map('intval', $db->query(
                'SELECT id FROM tarifas_evento WHERE evento_id = ?',
                [$eventoId]
            )->fetchAll(\PDO::FETCH_COLUMN));

            $existingIds = array_map('intval', $db->query(
                'SELECT id FROM campos_personalizados WHERE evento_id = ?',
                [$eventoId]
            )->fetchAll(\PDO::FETCH_COLUMN));

            $ids = [];
            $keepIds = [];
            foreach ($campos as $orden => $c) {
                // Llista de tarifes (condicional): només les vàlides de l'esdeveniment
                $tarifaIds = [];
                foreach ((array) ($c['tarifa_ids'] ?? []) as $tid) {
                    $tid = (int) $tid;
                    if ($tid > 0 && in_array($tid, $tarifasValides, true)) $tarifaIds[] = $tid;
                }
                $tarifaIds = array_values(array_unique($tarifaIds));

                // Opcions específiques per tarifa (només tarifes vàlides)
                $opcTarifa = [];
                foreach ((array) ($c['opciones_tarifa'] ?? []) as $tid => $opts) {
                    $tid = (int) $tid;
                    if ($tid > 0 && in_array($tid, $tarifasValides, true) && is_array($opts) && $opts) {
                        $opcTarifa[$tid] = array_values($opts);
                    }
                }

                // Mapa de calaix per opció: {opció => calaix 1..4}. Només valors vàlids.
                $calaixMap = [];
                foreach ((array) ($c['calaix_map'] ?? []) as $opt => $cal) {
                    $opt = trim((string) $opt);
                    $cal = (int) $cal;
                    if ($opt !== '' && $cal >= 1 && $cal <= 4) {
                        $calaixMap[$opt] = $cal;
                    }
                }

                $payload = [
                    'evento_id'       => $eventoId,
                    'tarifa_id'       => null,
                    'tarifa_ids'      => $tarifaIds ? (string) json_encode($tarifaIds) : null,
                    'nombre_campo'    => $c['nombre_campo'],
                    'etiqueta'        => $c['etiqueta'],
                    'tipo'            => $c['tipo'],
                    'opciones'        => $c['opciones'],
                    'opciones_tarifa' => $opcTarifa ? (string) json_encode($opcTarifa, JSON_UNESCAPED_UNICODE) : null,
                    'calaix_map'      => $calaixMap ? (string) json_encode($calaixMap, JSON_UNESCAPED_UNICODE) : null,
                    'antes_estandar'  => !empty($c['antes_estandar']) ? 1 : 0,
                    'requerido'       => $c['requerido'],
                    'orden'        => $orden,
                    'activo'       => 1,
                    'oculto'       => !empty($c['oculto']) ? 1 : 0,
                    'placeholder'  => $c['placeholder'] ?? null,
                    'ayuda'        => $c['ayuda'] ?? null,
                ];

                if (!empty($c['id']) && in_array((int) $c['id'], $existingIds, true)) {
                    $cid = (int) $c['id'];
                    $db->update('campos_personalizados', $payload, ['id' => $cid]);
                } else {
                    $cid = $db->insert('campos_personalizados', $payload);
                }
                $ids[] = $cid;
                $keepIds[] = $cid;
            }

            // Camps eliminats de l'editor: esborrar només si cap inscrit hi té
            // resposta; si en tenen, desactivar (les respostes es conserven).
            foreach (array_diff($existingIds, $keepIds) as $oldId) {
                $used = (int) $db->query(
                    'SELECT COUNT(*) FROM inscrito_campos_valores WHERE campo_id = ?',
                    [$oldId]
                )->fetchColumn();
                if ($used === 0) {
                    $db->query('DELETE FROM campos_personalizados WHERE id = ?', [$oldId]);
                } else {
                    $db->update('campos_personalizados', ['activo' => 0, 'oculto' => 1], ['id' => $oldId]);
                }
            }

            return $ids;
        });
    }

    /**
     * Convierte un array de opciones (strings) a JSON para la columna opciones.
     */
    public static function opcionesToJson(array $opciones): ?string
    {
        $clean = [];
        foreach ($opciones as $o) {
            $o = trim((string) $o);
            if ($o !== '') $clean[] = $o;
        }
        if ($clean === []) return null;
        return json_encode(array_values(array_unique($clean)), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Llista d'ids de tarifa per a les quals es mostra el camp (buit = totes).
     * Llegeix `tarifa_ids` (JSON) amb fallback a l'antic `tarifa_id`.
     * @return list<int>
     */
    public static function tarifasDeCampo(array $campo): array
    {
        $raw = $campo['tarifa_ids'] ?? null;
        if (!empty($raw)) {
            $arr = json_decode((string) $raw, true);
            if (is_array($arr)) {
                return array_values(array_filter(array_map('intval', $arr), fn($n) => $n > 0));
            }
        }
        if (!empty($campo['tarifa_id'])) return [(int) $campo['tarifa_id']];
        return [];
    }

    /** @return list<string> */
    public static function opcionesFromJson(?string $json): array
    {
        if ($json === null || $json === '') return [];
        $arr = json_decode($json, true);
        if (!is_array($arr)) return [];
        return array_values(array_map('strval', $arr));
    }

    /**
     * Map d'opcions específiques per tarifa: {tarifa_id:int => list<string>}.
     * (camp `opciones_tarifa`, JSON).
     * @return array<int, list<string>>
     */
    public static function opcionesPorTarifa(array $campo): array
    {
        $raw = $campo['opciones_tarifa'] ?? null;
        if (empty($raw)) return [];
        $arr = json_decode((string) $raw, true);
        if (!is_array($arr)) return [];
        $out = [];
        foreach ($arr as $tid => $opts) {
            $tid = (int) $tid;
            if ($tid > 0 && is_array($opts)) {
                $list = array_values(array_filter(array_map(fn($o) => trim((string) $o), $opts), fn($o) => $o !== ''));
                if ($list) $out[$tid] = $list;
            }
        }
        return $out;
    }

    /**
     * Opcions a mostrar per a una tarifa concreta: les específiques d'aquesta
     * tarifa si n'hi ha, si no les generals (`opciones`).
     * @return list<string>
     */
    public static function opcionesParaTarifa(array $campo, ?int $tarifaId): array
    {
        $map = self::opcionesPorTarifa($campo);
        if ($tarifaId !== null && isset($map[$tarifaId])) return $map[$tarifaId];
        return self::opcionesFromJson($campo['opciones'] ?? null);
    }

    /**
     * Colors dels calaixos de sortida (calaix 1..4). Per a badges a recollida.
     * @var array<int, array{nom:string, color:string, text:string}>
     */
    public const CALAIX_COLORS = [
        1 => ['nom' => 'Calaix 1', 'color' => '#facc15', 'text' => '#1f2937'], // groc
        2 => ['nom' => 'Calaix 2', 'color' => '#22c55e', 'text' => '#ffffff'], // verd
        3 => ['nom' => 'Calaix 3', 'color' => '#0ea5e9', 'text' => '#ffffff'], // blau
        4 => ['nom' => 'Calaix 4', 'color' => '#ec4899', 'text' => '#ffffff'], // rosa
    ];

    /**
     * Mapa de calaix per opció d'aquest camp: {opció => calaix 1..4}.
     * @return array<string, int>
     */
    public static function calaixMap(array $campo): array
    {
        $raw = $campo['calaix_map'] ?? null;
        if (empty($raw)) return [];
        $arr = json_decode((string) $raw, true);
        if (!is_array($arr)) return [];
        $out = [];
        foreach ($arr as $opt => $cal) {
            $opt = trim((string) $opt);
            $cal = (int) $cal;
            if ($opt !== '' && $cal >= 1 && $cal <= 4) $out[$opt] = $cal;
        }
        return $out;
    }

    /**
     * Calaix (1..4) corresponent al valor triat per l'inscrit, o null si no mapeja.
     */
    public static function calaixDeValor(array $campo, ?string $valor): ?int
    {
        if ($valor === null || trim($valor) === '') return null;
        $map = self::calaixMap($campo);
        return $map[trim($valor)] ?? null;
    }
}
