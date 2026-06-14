<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Models\Producto;

/**
 * Carret de la botiga en sessió. Guarda mínim (producte_id + variació_id + quantitat)
 * i resol preu/stock/nom/imatge en viu en cada render (preus sempre actuals).
 */
final class Cart
{
    private const KEY = 'tienda_cart';

    /** @return array<string, array{producto_id:int, variacion_id:?int, cantidad:int}> */
    public static function raw(): array
    {
        $c = Session::get(self::KEY);
        return is_array($c) ? $c : [];
    }

    private static function save(array $c): void
    {
        Session::set(self::KEY, $c);
    }

    private static function key(int $productoId, ?int $variacionId): string
    {
        return $productoId . ':' . ($variacionId ?? 0);
    }

    public static function add(int $productoId, ?int $variacionId, int $cantidad = 1): void
    {
        $cantidad = max(1, $cantidad);
        $key = self::key($productoId, $variacionId);
        $c = self::raw();
        $prev = (int) ($c[$key]['cantidad'] ?? 0);
        $c[$key] = ['producto_id' => $productoId, 'variacion_id' => $variacionId, 'cantidad' => $prev + $cantidad];
        self::save($c);
    }

    public static function setQty(string $key, int $cantidad): void
    {
        $c = self::raw();
        if (!isset($c[$key])) return;
        if ($cantidad <= 0) unset($c[$key]);
        else $c[$key]['cantidad'] = $cantidad;
        self::save($c);
    }

    public static function remove(string $key): void
    {
        $c = self::raw();
        unset($c[$key]);
        self::save($c);
    }

    public static function clear(): void
    {
        Session::forget(self::KEY);
    }

    /**
     * Línies resoltes amb dades actuals. Descarta/ajusta automàticament les
     * invàlides (producte inactiu/esborrat, variació inactiva, stock insuficient).
     * @return list<array<string,mixed>>
     */
    public static function items(): array
    {
        $c = self::raw();
        $out = [];
        $changed = false;

        foreach ($c as $key => $line) {
            $prod = Producto::findById((int) $line['producto_id']);
            if ($prod === null || (int) $prod['activo'] !== 1) { unset($c[$key]); $changed = true; continue; }

            $varId    = $line['variacion_id'] !== null ? (int) $line['variacion_id'] : null;
            $precio   = (float) $prod['precio'];
            $variante = null;
            $stock    = $prod['stock'] !== null ? (int) $prod['stock'] : null;

            if ($prod['tipo'] === Producto::TIPO_VARIABLE) {
                if ($varId === null) { unset($c[$key]); $changed = true; continue; }
                $var = Producto::variacionById($varId);
                if ($var === null || (int) $var['producto_id'] !== (int) $prod['id'] || (int) $var['activo'] !== 1) {
                    unset($c[$key]); $changed = true; continue;
                }
                $precio   = (float) $var['precio'];
                $variante = $var['etiqueta'];
                $stock    = $var['stock'] !== null ? (int) $var['stock'] : null;
            }

            $cant = max(1, (int) $line['cantidad']);
            if ($stock !== null && $cant > $stock) {
                if ($stock <= 0) { unset($c[$key]); $changed = true; continue; }
                $cant = $stock;
                $c[$key]['cantidad'] = $cant;
                $changed = true;
            }

            $out[] = [
                'key'          => $key,
                'producto_id'  => (int) $prod['id'],
                'variacion_id' => $varId,
                'slug'         => (string) $prod['slug'],
                'nombre'       => (string) $prod['nombre'],
                'variante'     => $variante,
                'imagen'       => Producto::imagenPrincipal((int) $prod['id']),
                'precio'       => $precio,
                'cantidad'     => $cant,
                'stock'        => $stock,
                'subtotal'     => round($precio * $cant, 2),
            ];
        }

        if ($changed) self::save($c);
        return $out;
    }

    public static function total(): float
    {
        $t = 0.0;
        foreach (self::items() as $i) $t += (float) $i['subtotal'];
        return round($t, 2);
    }

    /** Nombre total d'unitats (per al comptador del carret). */
    public static function count(): int
    {
        $n = 0;
        foreach (self::raw() as $l) $n += max(1, (int) $l['cantidad']);
        return $n;
    }

    public static function isEmpty(): bool
    {
        return self::raw() === [];
    }
}
