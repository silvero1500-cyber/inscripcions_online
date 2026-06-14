<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Comanda de botiga (independent de les inscripcions). Crea la comanda des del
 * carret descomptant stock de forma segura (FOR UPDATE), gestiona estats i
 * caduca les pendents abandonades retornant l'stock.
 */
final class PedidoTienda
{
    /** Minuts que una comanda pot estar 'pendiente' (sense pagar) abans de caducar. */
    public const PENDING_EXPIRY_MINUTES = 30;

    public static function findById(int $id): ?array
    {
        $row = Database::getInstance()->query('SELECT * FROM tienda_pedidos WHERE id = ?', [$id])->fetch();
        return $row ?: null;
    }

    public static function findByCodigo(string $codigo): ?array
    {
        $row = Database::getInstance()->query('SELECT * FROM tienda_pedidos WHERE codigo = ?', [$codigo])->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function lineas(int $pedidoId): array
    {
        return Database::getInstance()->query(
            'SELECT * FROM tienda_pedido_lineas WHERE pedido_id = ? ORDER BY id ASC',
            [$pedidoId]
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function listAll(): array
    {
        return Database::getInstance()->query(
            'SELECT * FROM tienda_pedidos ORDER BY created_at DESC, id DESC'
        )->fetchAll();
    }

    /**
     * Crea la comanda des del carret (Cart::raw()) descomptant stock dins una
     * transacció amb bloqueig de fila. Llança DomainException si falta stock o
     * el carret és invàlid.
     *
     * @param array<string, array{producto_id:int, variacion_id:?int, cantidad:int}> $rawCart
     * @param array{nombre:string, email:string, telefono:?string, locale:?string} $contacto
     * @return array{id:int, codigo:string, total:float}
     */
    public static function crearDesdeCarrito(array $rawCart, array $contacto): array
    {
        if ($rawCart === []) {
            throw new \DomainException('carret_buit');
        }

        return Database::getInstance()->transaction(function ($db) use ($rawCart, $contacto): array {
            $lineas = [];
            $total  = 0.0;

            foreach ($rawCart as $line) {
                $prodId = (int) $line['producto_id'];
                $cant   = max(1, (int) $line['cantidad']);
                $varId  = $line['variacion_id'] !== null ? (int) $line['variacion_id'] : null;

                $prod = $db->query('SELECT * FROM tienda_productos WHERE id = ?', [$prodId])->fetch();
                if (!$prod || (int) $prod['activo'] !== 1) {
                    throw new \DomainException('producte_no_disponible');
                }

                if ($prod['tipo'] === Producto::TIPO_VARIABLE) {
                    if ($varId === null) throw new \DomainException('variant_requerida');
                    // Bloqueja la fila de la variació per serialitzar l'stock
                    $var = $db->query(
                        'SELECT * FROM tienda_producto_variaciones WHERE id = ? FOR UPDATE',
                        [$varId]
                    )->fetch();
                    if (!$var || (int) $var['producto_id'] !== $prodId || (int) $var['activo'] !== 1) {
                        throw new \DomainException('variant_no_disponible');
                    }
                    $precio   = (float) $var['precio'];
                    $combo    = json_decode((string) $var['combinacion'], true);
                    $variante = is_array($combo) ? implode(' / ', array_map('strval', $combo)) : null;
                    $stock    = $var['stock'] !== null ? (int) $var['stock'] : null;
                    if ($stock !== null) {
                        if ($stock < $cant) throw new \DomainException('sin_stock:' . $prod['nombre'] . ($variante ? " ($variante)" : ''));
                        $db->query('UPDATE tienda_producto_variaciones SET stock = stock - ? WHERE id = ?', [$cant, $varId]);
                    }
                } else {
                    // Bloqueja la fila del producte simple
                    $prodLock = $db->query('SELECT stock FROM tienda_productos WHERE id = ? FOR UPDATE', [$prodId])->fetch();
                    $precio   = (float) $prod['precio'];
                    $variante = null;
                    $stock    = $prodLock['stock'] !== null ? (int) $prodLock['stock'] : null;
                    if ($stock !== null) {
                        if ($stock < $cant) throw new \DomainException('sin_stock:' . $prod['nombre']);
                        $db->query('UPDATE tienda_productos SET stock = stock - ? WHERE id = ?', [$cant, $prodId]);
                    }
                }

                $lineas[] = [
                    'producto_id'  => $prodId,
                    'variacion_id' => $varId,
                    'nombre'       => (string) $prod['nombre'],
                    'variante'     => $variante,
                    'precio_unit'  => round($precio, 2),
                    'cantidad'     => $cant,
                ];
                $total += $precio * $cant;
            }

            $total = round($total, 2);
            $codigo = self::generarCodigo($db);

            $pedidoId = $db->insert('tienda_pedidos', [
                'codigo'   => $codigo,
                'nombre'   => mb_substr((string) $contacto['nombre'], 0, 150),
                'email'    => mb_substr((string) $contacto['email'], 0, 255),
                'telefono' => ($contacto['telefono'] ?? null) ? mb_substr((string) $contacto['telefono'], 0, 20) : null,
                'total'    => number_format($total, 2, '.', ''),
                'estado'   => 'pendiente',
                'locale'   => $contacto['locale'] ?? null,
            ]);

            foreach ($lineas as $l) {
                $l['pedido_id'] = $pedidoId;
                $db->insert('tienda_pedido_lineas', $l);
            }

            return ['id' => $pedidoId, 'codigo' => $codigo, 'total' => $total];
        });
    }

    public static function marcarPagado(int $id): void
    {
        Database::getInstance()->query(
            "UPDATE tienda_pedidos SET estado = 'pagado' WHERE id = ? AND estado = 'pendiente'",
            [$id]
        );
    }

    public static function marcarEntregat(int $id): void
    {
        Database::getInstance()->query(
            "UPDATE tienda_pedidos SET estado = 'entregado', recollit_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    /**
     * Caduca comandes 'pendiente' de més de N minuts sense pagament completat i
     * retorna l'stock de les seves línies. Oportunista (sense cron).
     * @return int comandes caducades
     */
    public static function expirarPendientes(?int $minutes = null): int
    {
        $min = $minutes ?? self::PENDING_EXPIRY_MINUTES;
        if ($min <= 0) return 0;

        return (int) Database::getInstance()->transaction(function ($db) use ($min): int {
            $pendientes = $db->query(
                "SELECT id FROM tienda_pedidos
                 WHERE estado = 'pendiente'
                   AND created_at < (NOW() - INTERVAL ? MINUTE)
                   AND NOT EXISTS (SELECT 1 FROM pagos p WHERE p.tienda_pedido_id = tienda_pedidos.id AND p.estado = 'completado')
                 LIMIT 100",
                [$min]
            )->fetchAll(\PDO::FETCH_COLUMN);

            $n = 0;
            foreach ($pendientes as $pid) {
                foreach ($db->query('SELECT * FROM tienda_pedido_lineas WHERE pedido_id = ?', [(int) $pid])->fetchAll() as $l) {
                    $cant = (int) $l['cantidad'];
                    if (!empty($l['variacion_id'])) {
                        $db->query('UPDATE tienda_producto_variaciones SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL', [$cant, (int) $l['variacion_id']]);
                    } elseif (!empty($l['producto_id'])) {
                        $db->query('UPDATE tienda_productos SET stock = stock + ? WHERE id = ? AND stock IS NOT NULL', [$cant, (int) $l['producto_id']]);
                    }
                }
                $db->query("UPDATE tienda_pedidos SET estado = 'cancelado' WHERE id = ?", [(int) $pid]);
                $n++;
            }
            return $n;
        });
    }

    private static function generarCodigo($db): string
    {
        for ($i = 0; $i < 8; $i++) {
            $codigo = 'B' . date('ym') . strtoupper(bin2hex(random_bytes(2))); // ex: B2606A1F3
            $exists = $db->query('SELECT 1 FROM tienda_pedidos WHERE codigo = ? LIMIT 1', [$codigo])->fetchColumn();
            if (!$exists) return $codigo;
        }
        return 'B' . date('ymdHis');
    }
}
