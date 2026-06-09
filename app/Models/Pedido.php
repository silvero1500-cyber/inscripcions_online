<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Comanda d'inscripció: agrupa N inscrits (participants) sota un sol contacte
 * i un sol pagament. Una inscripció individual també és un pedido (amb 1 inscrit).
 */
final class Pedido
{
    public static function generarToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Insereix un pedido i retorna el seu id. Pensat per cridar-se dins d'una
     * transacció (juntament amb la creació dels inscrits).
     *
     * @param array<string,mixed> $data
     */
    public static function crear(array $data): int
    {
        return Database::getInstance()->insert('pedidos', $data);
    }

    public static function findById(int $id): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM pedidos WHERE id = ?', [$id])
            ->fetch();
        return $row ?: null;
    }

    public static function findByToken(string $token): ?array
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{32,64}$/', $token)) {
            return null;
        }
        $row = Database::getInstance()
            ->query('SELECT * FROM pedidos WHERE token = ?', [$token])
            ->fetch();
        return $row ?: null;
    }

    public static function actualizarTotal(int $id, float $total): void
    {
        Database::getInstance()->update('pedidos', ['importe_total' => $total], ['id' => $id]);
    }

    public static function marcarConfirmado(int $id): void
    {
        Database::getInstance()->update('pedidos', ['estado' => 'confirmado'], ['id' => $id]);
    }

    public static function marcarCancelado(int $id): void
    {
        Database::getInstance()->update('pedidos', ['estado' => 'cancelado'], ['id' => $id]);
    }

    /**
     * Inscrits (participants) d'un pedido, en ordre d'alta.
     * @return list<array<string,mixed>>
     */
    public static function inscritos(int $pedidoId): array
    {
        return Database::getInstance()->query(
            'SELECT * FROM inscritos WHERE pedido_id = ? ORDER BY id ASC',
            [$pedidoId]
        )->fetchAll();
    }
}
