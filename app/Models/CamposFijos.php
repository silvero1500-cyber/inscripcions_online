<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Configuració dels camps fixos (estàndard) del corredor per a cada esdeveniment.
 *
 * `nombre` i `email` són SEMPRE obligatoris i no es configuren.
 * La resta de camps poden ser: 'obligatori', 'opcional' o 'ocult'.
 *
 * Es desa a `eventos.campos_fijos` com a JSON { camp: estat }.
 */
final class CamposFijos
{
    public const ESTADOS = ['obligatori', 'opcional', 'ocult'];

    /**
     * Camps configurables, en l'ordre en què apareixen al formulari.
     * 'default' = estat quan l'esdeveniment encara no té config (compatibilitat enrere).
     *
     * @var array<string, array{label:string, default:string}>
     */
    public const CAMPS = [
        'apellido'         => ['label' => 'Cognoms',            'default' => 'obligatori'],
        'dni'              => ['label' => 'DNI / NIE',          'default' => 'obligatori'],
        'fecha_nacimiento' => ['label' => 'Data de naixement',  'default' => 'obligatori'],
        'sexo'             => ['label' => 'Sexe',               'default' => 'obligatori'],
        'talla_camiseta'   => ['label' => 'Talla samarreta',   'default' => 'obligatori'],
        'telefono'         => ['label' => 'Telèfon',            'default' => 'obligatori'],
        'club'             => ['label' => 'Club',               'default' => 'opcional'],
        'poblacion'        => ['label' => 'Població',           'default' => 'opcional'],
        'codigo_postal'    => ['label' => 'Codi postal',        'default' => 'opcional'],
    ];

    /**
     * Resol la configuració d'un esdeveniment a [camp => estat] per a TOTS els
     * camps configurables, aplicant els valors per defecte quan falten.
     *
     * @return array<string,string>
     */
    public static function resolve(?string $json): array
    {
        $stored = [];
        if ($json !== null && $json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) $stored = $decoded;
        }

        $out = [];
        foreach (self::CAMPS as $key => $meta) {
            $val = $stored[$key] ?? null;
            $out[$key] = in_array($val, self::ESTADOS, true) ? $val : $meta['default'];
        }
        return $out;
    }

    /**
     * Construeix el JSON a desar a partir del POST (camps_fijos[camp] = estat),
     * sanejant valors no vàlids al seu estat per defecte.
     */
    public static function fromPost(array $post): string
    {
        $raw = $post['campos_fijos'] ?? [];
        if (!is_array($raw)) $raw = [];

        $clean = [];
        foreach (self::CAMPS as $key => $meta) {
            $val = $raw[$key] ?? null;
            $clean[$key] = in_array($val, self::ESTADOS, true) ? $val : $meta['default'];
        }
        return (string) json_encode($clean, JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string,string> $config */
    public static function visible(array $config, string $campo): bool
    {
        return ($config[$campo] ?? 'obligatori') !== 'ocult';
    }

    /** @param array<string,string> $config */
    public static function requerido(array $config, string $campo): bool
    {
        return ($config[$campo] ?? 'obligatori') === 'obligatori';
    }
}
