<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\Slugger;

/**
 * Producte de la botiga (simple o variable). Galeria d'imatges + atributs +
 * variacions amb preu/stock propis. Gestionat només pel superadmin.
 */
final class Producto
{
    public const TIPO_SIMPLE   = 'simple';
    public const TIPO_VARIABLE = 'variable';

    // ── Lectura ─────────────────────────────────────────────
    public static function findById(int $id): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM tienda_productos WHERE id = ?', [$id])->fetch();
        return $row ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM tienda_productos WHERE slug = ?', [$slug])->fetch();
        return $row ?: null;
    }

    /** Tots els productes (admin), amb imatge principal i nº variacions. */
    public static function listAll(): array
    {
        return Database::getInstance()->query(
            "SELECT p.*,
                    (SELECT ruta FROM tienda_producto_imagenes WHERE producto_id = p.id ORDER BY orden, id LIMIT 1) AS imagen,
                    (SELECT COUNT(*) FROM tienda_producto_variaciones WHERE producto_id = p.id) AS n_variaciones
             FROM tienda_productos p
             ORDER BY p.orden ASC, p.id DESC"
        )->fetchAll();
    }

    /** Productes actius per al catàleg públic, amb imatge principal i preu mínim. */
    public static function listActivos(): array
    {
        $rows = Database::getInstance()->query(
            "SELECT p.*,
                    (SELECT ruta FROM tienda_producto_imagenes WHERE producto_id = p.id ORDER BY orden, id LIMIT 1) AS imagen
             FROM tienda_productos p
             WHERE p.activo = 1
             ORDER BY p.destacado DESC, p.orden ASC, p.id DESC"
        )->fetchAll();
        foreach ($rows as &$p) {
            $p['precio_desde'] = self::precioDesde($p);
        }
        return $rows;
    }

    /** @return list<array{id:int, ruta:string, orden:int}> */
    public static function imagenes(int $productoId): array
    {
        return Database::getInstance()->query(
            'SELECT id, ruta, orden FROM tienda_producto_imagenes
             WHERE producto_id = ? ORDER BY orden ASC, id ASC',
            [$productoId]
        )->fetchAll();
    }

    public static function imagenById(int $id): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM tienda_producto_imagenes WHERE id = ?', [$id])->fetch();
        return $row ?: null;
    }

    /** Atributs amb els valors ja descodificats. @return list<array{id:int,nombre:string,valores:list<string>}> */
    public static function atributos(int $productoId): array
    {
        $rows = Database::getInstance()->query(
            'SELECT id, nombre, valores FROM tienda_producto_atributos
             WHERE producto_id = ? ORDER BY orden ASC, id ASC',
            [$productoId]
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $vals = json_decode((string) $r['valores'], true);
            $out[] = [
                'id'      => (int) $r['id'],
                'nombre'  => (string) $r['nombre'],
                'valores' => is_array($vals) ? array_values(array_map('strval', $vals)) : [],
            ];
        }
        return $out;
    }

    /** Variacions amb la combinació descodificada. @return list<array<string,mixed>> */
    public static function variaciones(int $productoId, bool $soloActivas = false): array
    {
        $sql = 'SELECT * FROM tienda_producto_variaciones WHERE producto_id = ?';
        if ($soloActivas) $sql .= ' AND activo = 1';
        $sql .= ' ORDER BY orden ASC, id ASC';
        $rows = Database::getInstance()->query($sql, [$productoId])->fetchAll();
        foreach ($rows as &$r) {
            $combo = json_decode((string) $r['combinacion'], true);
            $r['combinacion'] = is_array($combo) ? $combo : [];
            $r['etiqueta']    = implode(' / ', array_map('strval', $r['combinacion']));
        }
        return $rows;
    }

    public static function variacionById(int $id): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM tienda_producto_variaciones WHERE id = ?', [$id])->fetch();
        if (!$row) return null;
        $combo = json_decode((string) $row['combinacion'], true);
        $row['combinacion'] = is_array($combo) ? $combo : [];
        $row['etiqueta']    = implode(' / ', array_map('strval', $row['combinacion']));
        return $row;
    }

    /** Preu mínim a mostrar ("des de"): variable → min variació activa; simple → precio. */
    public static function precioDesde(array $producto): float
    {
        if (($producto['tipo'] ?? 'simple') === self::TIPO_VARIABLE) {
            $min = Database::getInstance()->query(
                'SELECT MIN(precio) FROM tienda_producto_variaciones
                 WHERE producto_id = ? AND activo = 1',
                [(int) $producto['id']]
            )->fetchColumn();
            return $min !== null ? (float) $min : (float) ($producto['precio'] ?? 0);
        }
        return (float) ($producto['precio'] ?? 0);
    }

    // ── Escriptura ──────────────────────────────────────────
    public static function create(array $data): int
    {
        $data['slug'] = self::uniqueSlug($data['slug'] ?? ($data['nombre'] ?? 'producte'), null);
        return Database::getInstance()->insert('tienda_productos', $data);
    }

    public static function update(int $id, array $data): void
    {
        if (isset($data['slug'])) {
            $data['slug'] = self::uniqueSlug($data['slug'], $id);
        }
        Database::getInstance()->update('tienda_productos', $data, ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        // Esborra els fitxers d'imatge físics abans del cascade
        foreach (self::imagenes($id) as $img) {
            \App\Services\ImageUploader::deleteEventImage($img['ruta']);
        }
        Database::getInstance()->query('DELETE FROM tienda_productos WHERE id = ?', [$id]);
    }

    public static function uniqueSlug(string $base, ?int $excludeId): string
    {
        $slug = Slugger::make($base) ?: 'producte';
        $db = Database::getInstance();
        $candidate = $slug;
        $i = 2;
        while (true) {
            $sql = 'SELECT 1 FROM tienda_productos WHERE slug = ?';
            $params = [$candidate];
            if ($excludeId !== null) { $sql .= ' AND id <> ?'; $params[] = $excludeId; }
            if (!$db->query($sql . ' LIMIT 1', $params)->fetchColumn()) break;
            $candidate = $slug . '-' . $i++;
        }
        return $candidate;
    }

    // ── Imatges ─────────────────────────────────────────────
    public static function addImagen(int $productoId, string $ruta, int $orden = 0): int
    {
        return Database::getInstance()->insert('tienda_producto_imagenes', [
            'producto_id' => $productoId,
            'ruta'        => $ruta,
            'orden'       => $orden,
        ]);
    }

    public static function deleteImagen(int $imagenId): void
    {
        $img = self::imagenById($imagenId);
        if ($img === null) return;
        \App\Services\ImageUploader::deleteEventImage((string) $img['ruta']);
        Database::getInstance()->query('DELETE FROM tienda_producto_imagenes WHERE id = ?', [$imagenId]);
    }

    // ── Atributs i variacions (reemplaça tot el conjunt) ────
    /** @param list<array{nombre:string, valores:list<string>}> $atributos */
    public static function syncAtributos(int $productoId, array $atributos): void
    {
        $db = Database::getInstance();
        $db->transaction(function ($db) use ($productoId, $atributos): void {
            $db->query('DELETE FROM tienda_producto_atributos WHERE producto_id = ?', [$productoId]);
            $orden = 0;
            foreach ($atributos as $a) {
                $nombre = trim((string) ($a['nombre'] ?? ''));
                $valores = array_values(array_filter(array_map(
                    fn($v) => trim((string) $v),
                    (array) ($a['valores'] ?? [])
                ), fn($v) => $v !== ''));
                if ($nombre === '' || $valores === []) continue;
                $db->insert('tienda_producto_atributos', [
                    'producto_id' => $productoId,
                    'nombre'      => mb_substr($nombre, 0, 100),
                    'valores'     => (string) json_encode($valores, JSON_UNESCAPED_UNICODE),
                    'orden'       => $orden++,
                ]);
            }
        });
    }

    /** @param list<array{combinacion:array<string,string>, precio:float, stock:?int, sku:?string}> $variaciones */
    public static function syncVariaciones(int $productoId, array $variaciones): void
    {
        $db = Database::getInstance();
        $db->transaction(function ($db) use ($productoId, $variaciones): void {
            $db->query('DELETE FROM tienda_producto_variaciones WHERE producto_id = ?', [$productoId]);
            $orden = 0;
            foreach ($variaciones as $v) {
                $combo = (array) ($v['combinacion'] ?? []);
                if ($combo === []) continue;
                $stock = $v['stock'] ?? null;
                $db->insert('tienda_producto_variaciones', [
                    'producto_id' => $productoId,
                    'combinacion' => (string) json_encode($combo, JSON_UNESCAPED_UNICODE),
                    'sku'         => ($v['sku'] ?? null) !== null && $v['sku'] !== '' ? mb_substr((string) $v['sku'], 0, 100) : null,
                    'precio'      => round((float) ($v['precio'] ?? 0), 2),
                    'stock'       => ($stock === null || $stock === '') ? null : max(0, (int) $stock),
                    'activo'      => 1,
                    'orden'       => $orden++,
                ]);
            }
        });
    }
}
