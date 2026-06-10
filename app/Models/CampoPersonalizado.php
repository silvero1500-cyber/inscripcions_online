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
     * Campos activos de un evento para el formulario público.
     * @return list<array<string,mixed>>
     */
    public static function getActivosPorEvento(int $eventoId): array
    {
        return Database::getInstance()
            ->query(
                'SELECT * FROM campos_personalizados
                 WHERE evento_id = ? AND activo = 1
                 ORDER BY orden ASC, id ASC',
                [$eventoId]
            )->fetchAll();
    }

    /**
     * Reemplaza todos los campos personalizados de un evento por la lista nueva.
     * Atómico: si algo falla, rollback.
     *
     * @param list<array{nombre_campo:string, etiqueta:string, tipo:string, opciones:?string, requerido:int, orden:int}> $campos
     */
    public static function syncForEvento(int $eventoId, array $campos): void
    {
        $db = Database::getInstance();
        $db->transaction(function ($db) use ($eventoId, $campos): void {
            // Tarifes vàlides de l'esdeveniment (per validar el camp condicional)
            $tarifasValides = array_map('intval', $db->query(
                'SELECT id FROM tarifas_evento WHERE evento_id = ?',
                [$eventoId]
            )->fetchAll(\PDO::FETCH_COLUMN));

            $db->query('DELETE FROM campos_personalizados WHERE evento_id = ?', [$eventoId]);
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

                $db->insert('campos_personalizados', [
                    'evento_id'       => $eventoId,
                    'tarifa_id'       => null,
                    'tarifa_ids'      => $tarifaIds ? (string) json_encode($tarifaIds) : null,
                    'nombre_campo'    => $c['nombre_campo'],
                    'etiqueta'        => $c['etiqueta'],
                    'tipo'            => $c['tipo'],
                    'opciones'        => $c['opciones'],
                    'opciones_tarifa' => $opcTarifa ? (string) json_encode($opcTarifa, JSON_UNESCAPED_UNICODE) : null,
                    'antes_estandar'  => !empty($c['antes_estandar']) ? 1 : 0,
                    'requerido'       => $c['requerido'],
                    'orden'        => $orden,
                    'activo'       => 1,
                    'placeholder'  => $c['placeholder'] ?? null,
                    'ayuda'        => $c['ayuda'] ?? null,
                ]);
            }
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
}
