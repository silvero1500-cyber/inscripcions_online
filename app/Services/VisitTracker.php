<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Comptador lleuger de connexions (visites) al formulari públic, per al KPI
 * d'hores punta. Disseny pensat per NO carregar el servidor:
 *
 *  - El controlador públic crida mark($eventoId) quan mostra el formulari.
 *  - El registre real (flush) s'executa dins del register_shutdown_function
 *    d'index.php, DESPRÉS de fastcgi_finish_request(): la pàgina ja s'ha enviat
 *    al visitant, així que no afegeix cap latència.
 *  - No es guarda una fila per visita: s'incrementa un comptador agregat
 *    (evento_id, dia, hora) amb INSERT ... ON DUPLICATE KEY UPDATE. Com a molt
 *    24 files per dia i esdeveniment. Sense IP ni dades personals (cap RGPD).
 *  - S'ignoren els bots i les peticions sense User-Agent.
 */
final class VisitTracker
{
    private static ?int $eventoId = null;

    /** El controlador públic marca que aquesta petició és una visita a un event. */
    public static function mark(int $eventoId): void
    {
        if ($eventoId > 0) {
            self::$eventoId = $eventoId;
        }
    }

    /** S'executa al final de la petició (shutdown). Mai ha de trencar res. */
    public static function flush(): void
    {
        if (self::$eventoId === null) {
            return;
        }

        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        // Sense UA o bot conegut → no compta (volem persones reals)
        if ($ua === '' || preg_match(
            '/bot|crawl|spider|slurp|bing|google|yandex|baidu|duckduck|'
            . 'facebookexternalhit|embedly|preview|monitor|pingdom|uptime|'
            . 'curl|wget|python-requests|http-client|headless|phantom|'
            . 'lighthouse|semrush|ahrefs|mj12|dotbot|petalbot/i',
            $ua
        )) {
            return;
        }

        try {
            // PHP corre en Europe/Madrid → data i hora locals directes
            $fecha = date('Y-m-d');
            $hora  = (int) date('G'); // 0..23
            Database::getInstance()->query(
                'INSERT INTO visitas_horas (evento_id, fecha, hora, n)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE n = n + 1',
                [self::$eventoId, $fecha, $hora]
            );
        } catch (\Throwable $e) {
            // L'analítica no pot fer caure la petició
            error_log('[VisitTracker] ' . $e->getMessage());
        }
    }
}
