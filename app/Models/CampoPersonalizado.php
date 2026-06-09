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
                $tarifaId = isset($c['tarifa_id']) && $c['tarifa_id'] !== null ? (int) $c['tarifa_id'] : null;
                if ($tarifaId !== null && !in_array($tarifaId, $tarifasValides, true)) {
                    $tarifaId = null; // tarifa inexistent → camp sense condició
                }
                $db->insert('campos_personalizados', [
                    'evento_id'    => $eventoId,
                    'tarifa_id'    => $tarifaId,
                    'nombre_campo' => $c['nombre_campo'],
                    'etiqueta'     => $c['etiqueta'],
                    'tipo'         => $c['tipo'],
                    'opciones'     => $c['opciones'],
                    'requerido'    => $c['requerido'],
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

    /** @return list<string> */
    public static function opcionesFromJson(?string $json): array
    {
        if ($json === null || $json === '') return [];
        $arr = json_decode($json, true);
        if (!is_array($arr)) return [];
        return array_values(array_map('strval', $arr));
    }
}
