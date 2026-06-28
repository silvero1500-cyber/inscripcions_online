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
        'franja_temps'     => ['label' => 'Franja de temps',    'default' => 'obligatori'],
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

    // ── Ordre dels camps estàndard al formulari ──────────────

    /** Camps sempre obligatoris (no configurables d'estat), però sí ordenables. */
    public const FIXOS = ['nombre', 'email'];

    /**
     * Ordre per defecte de TOTS els camps estàndard (inclou nombre i email).
     * 'email' representa el bloc email + repetir email (van sempre junts).
     */
    public const ORDEN_DEFAULT = [
        'nombre', 'apellido', 'email', 'telefono', 'dni',
        'fecha_nacimiento', 'sexo', 'talla_camiseta', 'poblacion', 'codigo_postal', 'club', 'franja_temps',
    ];

    public static function labelOf(string $key): string
    {
        if ($key === 'nombre') return 'Nom';
        if ($key === 'email')  return 'Email (+ repetir)';
        return self::CAMPS[$key]['label'] ?? $key;
    }

    /**
     * Ordre resolt dels camps estàndard: aplica l'ordre desat (claus vàlides)
     * i afegeix al final qualsevol camp que falti (compatibilitat enrere).
     * @return list<string>
     */
    public static function orden(?string $json): array
    {
        $stored = [];
        if ($json !== null && $json !== '') {
            $d = json_decode($json, true);
            if (is_array($d)) $stored = $d;
        }
        $out = [];
        foreach ($stored as $k) {
            $k = (string) $k;
            if (in_array($k, self::ORDEN_DEFAULT, true) && !in_array($k, $out, true)) $out[] = $k;
        }
        foreach (self::ORDEN_DEFAULT as $k) {
            if (!in_array($k, $out, true)) $out[] = $k;
        }
        return $out;
    }

    /**
     * Ordre COMPLET dels camps del formulari: estàndard + personalitzats
     * (clau 'campo_<id>'), respectant l'ordre desat i afegint els que falten.
     * @param list<array<string,mixed>> $campos  Camps personalitzats de l'event
     * @return list<string>
     */
    public static function ordenComplet(?string $json, array $campos): array
    {
        $customKeys = [];
        foreach ($campos as $c) $customKeys[] = 'campo_' . (int) $c['id'];

        $stored = [];
        if ($json !== null && $json !== '') {
            $d = json_decode($json, true);
            if (is_array($d)) $stored = $d;
        }
        $out = [];
        foreach ($stored as $k) {
            $k = (string) $k;
            if ((in_array($k, self::ORDEN_DEFAULT, true) || in_array($k, $customKeys, true)) && !in_array($k, $out, true)) {
                $out[] = $k;
            }
        }
        foreach (self::ORDEN_DEFAULT as $k) if (!in_array($k, $out, true)) $out[] = $k;
        foreach ($customKeys as $k)        if (!in_array($k, $out, true)) $out[] = $k;
        return $out;
    }

    /**
     * Construeix el JSON d'ordre final a desar: substitueix els marcadors
     * '__CUSTOM__' (camps personalitzats, en ordre) pels ids reals creats.
     * @param list<int> $createdCampoIds  ids dels camps personalitzats acabats de crear, EN ORDRE
     */
    public static function buildCamposOrden(array $post, array $createdCampoIds): ?string
    {
        $raw = $post['campos_orden'] ?? null;
        if (!is_array($raw)) return null;
        $ci = 0; $out = [];
        foreach ($raw as $k) {
            $k = (string) $k;
            if ($k === '__CUSTOM__') {
                if (isset($createdCampoIds[$ci])) $out[] = 'campo_' . (int) $createdCampoIds[$ci];
                $ci++;
            } elseif (in_array($k, self::ORDEN_DEFAULT, true) && !in_array($k, $out, true)) {
                $out[] = $k;
            }
        }
        foreach (self::ORDEN_DEFAULT as $k) if (!in_array($k, $out, true)) $out[] = $k;
        return $out ? (string) json_encode($out, JSON_UNESCAPED_UNICODE) : null;
    }

    /** Construeix el JSON d'ordre a partir del POST (campos_orden[] = clau). */
    public static function ordenFromPost(array $post): ?string
    {
        $raw = $post['campos_orden'] ?? null;
        if (!is_array($raw)) return null;
        $out = [];
        foreach ($raw as $k) {
            $k = (string) $k;
            if (in_array($k, self::ORDEN_DEFAULT, true) && !in_array($k, $out, true)) $out[] = $k;
        }
        foreach (self::ORDEN_DEFAULT as $k) {
            if (!in_array($k, $out, true)) $out[] = $k;
        }
        return $out ? (string) json_encode($out, JSON_UNESCAPED_UNICODE) : null;
    }
}
