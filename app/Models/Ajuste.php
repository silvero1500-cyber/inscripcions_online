<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Configuració global clau/valor (taula `ajustes`). Tolerant a errors: si la taula
 * encara no existeix (migració pendent), retorna el valor per defecte.
 */
final class Ajuste
{
    // Claus conegudes
    public const TIENDA_LUGAR    = 'tienda_lugar_recogida';
    public const TIENDA_HORARIO  = 'tienda_horario_recogida';
    public const TIENDA_ACTIVA   = 'tienda_activa';
    public const PRIVACIDAD_URL   = 'privacidad_url';
    public const PRIVACIDAD_TEXTO = 'privacidad_texto';

    /** La botiga està activada? (per defecte sí, si mai s'ha tocat). */
    public static function tiendaActiva(): bool
    {
        return self::get(self::TIENDA_ACTIVA, '1') !== '0';
    }

    /** @var array<string,?string>|null */
    private static ?array $cache = null;

    public static function get(string $clave, ?string $default = null): ?string
    {
        self::load();
        return self::$cache[$clave] ?? $default;
    }

    public static function set(string $clave, ?string $valor): void
    {
        Database::getInstance()->query(
            'INSERT INTO ajustes (clave, valor) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
            [$clave, $valor]
        );
        self::$cache[$clave] = $valor;
    }

    private static function load(): void
    {
        if (self::$cache !== null) return;
        self::$cache = [];
        try {
            $rows = Database::getInstance()->query('SELECT clave, valor FROM ajustes')->fetchAll();
            foreach ($rows as $r) {
                self::$cache[(string) $r['clave']] = $r['valor'] !== null ? (string) $r['valor'] : null;
            }
        } catch (\Throwable $e) {
            // taula absent → cache buida (defaults)
        }
    }
}
