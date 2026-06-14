<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Limitador de freqüència genèric i persistent (per IP o per clau arbitrària).
 * Funciona encara que el client no guardi cookies. Si la taula encara no existeix
 * (migració pendent), degrada amb gràcia: no bloqueja res.
 */
final class RateLimit
{
    /** Quantes accions amb aquesta clau s'han fet dins de la finestra (segons). */
    public static function tooMany(string $key, int $max, int $windowSeconds): bool
    {
        try {
            // Finestra calculada amb l'hora del propi MySQL (sessió en UTC),
            // per coincidir amb created_at (UTC). No es fa servir date() de PHP
            // perquè la zona horària de PHP pot no ser UTC.
            $n = (int) Database::getInstance()->query(
                'SELECT COUNT(*) FROM rate_limits
                 WHERE rate_key = ? AND created_at >= (NOW() - INTERVAL ? SECOND)',
                [$key, $windowSeconds]
            )->fetchColumn();
            return $n >= $max;
        } catch (\Throwable $e) {
            return false; // taula absent o error → no bloqueja
        }
    }

    /** Registra una acció amb aquesta clau. */
    public static function hit(string $key): void
    {
        try {
            Database::getInstance()->insert('rate_limits', [
                'rate_key' => mb_substr($key, 0, 150),
            ]);
            // Neteja oportunista (1%) de registres antics
            if (random_int(1, 100) === 1) {
                Database::getInstance()->query(
                    'DELETE FROM rate_limits WHERE created_at < ?',
                    [date('Y-m-d H:i:s', time() - 86400)]
                );
            }
        } catch (\Throwable $e) {
            // sense persistència → no passa res
        }
    }
}
