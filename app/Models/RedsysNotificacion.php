<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Log de auditoría de notificaciones IPN entrantes de Redsys.
 * Se registran TODAS — válidas, inválidas y repetidas — para forensics.
 */
final class RedsysNotificacion
{
    public static function log(
        string $dsOrder,
        array $payloadRaw,
        ?string $ipOrigen,
        bool $procesado,
        ?string $error = null
    ): int {
        return Database::getInstance()->insert('redsys_notificaciones', [
            'ds_order'    => $dsOrder !== '' ? $dsOrder : '-',
            'payload_raw' => (string) json_encode($payloadRaw, JSON_UNESCAPED_UNICODE),
            'ip_origen'   => $ipOrigen,
            'procesado'   => $procesado ? 1 : 0,
            'error'       => $error,
        ]);
    }
}
