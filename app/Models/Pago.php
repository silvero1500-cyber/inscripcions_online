<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Pago
{
    public const ESTADO_INICIADO   = 'iniciado';
    public const ESTADO_COMPLETADO = 'completado';
    public const ESTADO_FALLIDO    = 'fallido';
    public const ESTADO_CANCELADO  = 'cancelado';

    public static function findById(int $id): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM pagos WHERE id = ?', [$id])
            ->fetch();
        return $row ?: null;
    }

    public static function findByOrder(string $dsOrder): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM pagos WHERE ds_order = ?', [$dsOrder])
            ->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function listByInscrito(int $inscritoId): array
    {
        return Database::getInstance()->query(
            'SELECT * FROM pagos WHERE inscrito_id = ? ORDER BY id DESC',
            [$inscritoId]
        )->fetchAll();
    }

    public static function hayPagoCompletado(int $inscritoId): bool
    {
        $row = Database::getInstance()->query(
            "SELECT 1 FROM pagos
             WHERE inscrito_id = ? AND estado = 'completado'
             LIMIT 1",
            [$inscritoId]
        )->fetchColumn();
        return (bool) $row;
    }

    public static function hayPagoCompletadoPedido(int $pedidoId): bool
    {
        $row = Database::getInstance()->query(
            "SELECT 1 FROM pagos
             WHERE pedido_id = ? AND estado = 'completado'
             LIMIT 1",
            [$pedidoId]
        )->fetchColumn();
        return (bool) $row;
    }

    /**
     * Crea un pago 'iniciado' per a un PEDIDO (grup). `inscrito_id` apunta al
     * primer participant (representant) per compatibilitat amb la columna NOT NULL.
     */
    public static function crearIniciadoPedido(
        int $pedidoId,
        int $inscritoRepId,
        string $dsOrder,
        float $importe,
        string $merchantCode,
        string $terminal
    ): int {
        return Database::getInstance()->insert('pagos', [
            'inscrito_id'      => $inscritoRepId,
            'pedido_id'        => $pedidoId,
            'ds_order'         => $dsOrder,
            'ds_merchant_code' => $merchantCode,
            'ds_terminal'      => $terminal,
            'importe'          => number_format($importe, 2, '.', ''),
            'moneda'           => 'EUR',
            'estado'           => self::ESTADO_INICIADO,
        ]);
    }

    /**
     * Crea un registro de pago en estado 'iniciado'.
     */
    public static function crearIniciado(
        int $inscritoId,
        string $dsOrder,
        float $importe,
        string $merchantCode,
        string $terminal
    ): int {
        return Database::getInstance()->insert('pagos', [
            'inscrito_id'      => $inscritoId,
            'ds_order'         => $dsOrder,
            'ds_merchant_code' => $merchantCode,
            'ds_terminal'      => $terminal,
            'importe'          => number_format($importe, 2, '.', ''),
            'moneda'           => 'EUR',
            'estado'           => self::ESTADO_INICIADO,
        ]);
    }

    /**
     * Marca un pago como completado y guarda la respuesta completa.
     */
    public static function marcarCompletado(int $id, array $datosRedsys): int
    {
        return Database::getInstance()->update('pagos', [
            'estado'              => self::ESTADO_COMPLETADO,
            'ds_response'         => (string) ($datosRedsys['Ds_Response'] ?? ''),
            'ds_auth_code'        => isset($datosRedsys['Ds_AuthorisationCode'])
                                       ? (string) $datosRedsys['Ds_AuthorisationCode'] : null,
            'ds_transaction_type' => isset($datosRedsys['Ds_TransactionType'])
                                       ? (string) $datosRedsys['Ds_TransactionType'] : null,
            'ds_secure_payment'   => isset($datosRedsys['Ds_SecurePayment'])
                                       ? (int) $datosRedsys['Ds_SecurePayment'] : null,
            'ds_card_type'        => isset($datosRedsys['Ds_Card_Type'])
                                       ? (string) $datosRedsys['Ds_Card_Type'] : null,
            'ds_card_brand'       => isset($datosRedsys['Ds_Card_Brand'])
                                       ? (string) $datosRedsys['Ds_Card_Brand'] : null,
            'respuesta_completa'  => (string) json_encode($datosRedsys, JSON_UNESCAPED_UNICODE),
        ], ['id' => $id]);
    }

    public static function marcarFallido(int $id, array $datosRedsys): int
    {
        return Database::getInstance()->update('pagos', [
            'estado'             => self::ESTADO_FALLIDO,
            'ds_response'        => (string) ($datosRedsys['Ds_Response'] ?? ''),
            'respuesta_completa' => (string) json_encode($datosRedsys, JSON_UNESCAPED_UNICODE),
        ], ['id' => $id]);
    }
}
