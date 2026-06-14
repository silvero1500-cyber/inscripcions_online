<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Throttling persistent d'intents de login (anti força bruta).
 * Compta fallades per email i per IP dins d'una finestra de temps.
 * A diferència d'un límit en sessió, funciona encara que el bot no guardi cookies.
 */
final class LoginThrottle
{
    private const WINDOW_MINUTES = 15;
    private const MAX_PER_EMAIL   = 5;   // bloqueja un email concret
    private const MAX_PER_IP      = 20;  // bloqueja atacs distribuïts des d'una IP

    /** ¿S'ha de bloquejar aquest intent (massa fallades recents)? */
    public static function blocked(string $ip, string $email): bool
    {
        $db     = Database::getInstance();
        // Finestra amb l'hora de MySQL (sessió en UTC) per coincidir amb created_at;
        // date() de PHP podria estar en una altra zona horària i la finestra no casaria.
        $window = self::WINDOW_MINUTES * 60;
        $email  = mb_strtolower(trim($email));

        if ($email !== '') {
            $byEmail = (int) $db->query(
                'SELECT COUNT(*) FROM intentos_login
                 WHERE email = ? AND created_at >= (NOW() - INTERVAL ? SECOND)',
                [$email, $window]
            )->fetchColumn();
            if ($byEmail >= self::MAX_PER_EMAIL) return true;
        }

        $byIp = (int) $db->query(
            'SELECT COUNT(*) FROM intentos_login
             WHERE ip = ? AND created_at >= (NOW() - INTERVAL ? SECOND)',
            [$ip, $window]
        )->fetchColumn();

        return $byIp >= self::MAX_PER_IP;
    }

    /** Registra una fallada de login. */
    public static function recordFailure(string $ip, string $email): void
    {
        Database::getInstance()->insert('intentos_login', [
            'ip'    => mb_substr($ip, 0, 45),
            'email' => mb_substr(mb_strtolower(trim($email)), 0, 255) ?: null,
        ]);

        // Neteja oportunista (1%) de registres antics per no fer créixer la taula
        if (random_int(1, 100) === 1) {
            $old = date('Y-m-d H:i:s', time() - 86400);
            Database::getInstance()->query(
                'DELETE FROM intentos_login WHERE created_at < ?',
                [$old]
            );
        }
    }

    /** Esborra les fallades d'un email (després d'un login correcte). */
    public static function clearFailures(string $email): void
    {
        Database::getInstance()->query(
            'DELETE FROM intentos_login WHERE email = ?',
            [mb_strtolower(trim($email))]
        );
    }

    public static function windowMinutes(): int
    {
        return self::WINDOW_MINUTES;
    }
}
