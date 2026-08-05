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
            $db = Database::getInstance();
            $db->query(
                'INSERT INTO visitas_horas (evento_id, fecha, hora, n)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE n = n + 1',
                [self::$eventoId, $fecha, $hora]
            );
            // Domini de la web oficial de l'event → compta com a "web pròpia"
            $ownHost = null;
            $webUrl = $db->query('SELECT web_oficial_url FROM eventos WHERE id = ?', [self::$eventoId])->fetchColumn();
            if (is_string($webUrl) && $webUrl !== '') {
                $h = strtolower((string) parse_url($webUrl, PHP_URL_HOST));
                $h = preg_replace('/^www\./', '', $h) ?? $h;
                if ($h !== '' && strlen($h) >= 4 && str_contains($h, '.')) {
                    $ownHost = $h;
                }
            }
            // Origen (font de trànsit): UTM prioritari, si no el referer
            $db->query(
                'INSERT INTO visitas_origen (evento_id, fecha, font, n)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE n = n + 1',
                [self::$eventoId, $fecha, self::detectFont($ownHost)]
            );
        } catch (\Throwable $e) {
            // L'analítica no pot fer caure la petició
            error_log('[VisitTracker] ' . $e->getMessage());
        }
    }

    /**
     * Determina l'origen de la visita:
     *  1) Paràmetres UTM de l'enllaç (utm_source / utm_medium) — l'únic fiable
     *     per al mailing (els correus no envien Referer).
     *  2) Si no n'hi ha, el Referer (autodetecció de Google, Facebook, etc.).
     *  3) Sense cap pista → 'directe'.
     *
     * @param string|null $ownHost Domini de la web oficial de l'event (sense
     *   "www."), que es comptabilitza com a "web pròpia" (p. ex. la web de la cursa).
     */
    private static function detectFont(?string $ownHost = null): string
    {
        // ── 1) UTM ──
        $utm = strtolower(trim((string) ($_GET['utm_source'] ?? '')));
        if ($utm !== '') {
            $f = self::normalitza($utm);
            if ($f !== null) return $f;
            // utm_source desconegut: es guarda sanejat i escurçat
            $clean = preg_replace('/[^a-z0-9]/', '', $utm) ?: 'altres';
            return substr($clean, 0, 20);
        }
        $med = strtolower((string) ($_GET['utm_medium'] ?? ''));
        if (str_contains($med, 'mail') || str_contains($med, 'email') || str_contains($med, 'news')) {
            return 'mailing';
        }

        // ── 2) Referer ──
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref === '') return 'directe';
        $host = strtolower((string) parse_url($ref, PHP_URL_HOST));
        if ($host === '') return 'directe';

        // Navegació dins del propi domini (werun.cat) o des de la web oficial de
        // l'event (p. ex. cursafestamajorsabadell.cat) → "web pròpia".
        $self = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (($self !== '' && $host === $self)
            || str_contains($host, 'werun.cat')
            || ($ownHost !== null && str_contains($host, $ownHost))) {
            return 'web';
        }

        $map = [
            'google.' => 'google', 'facebook.' => 'facebook', 'fb.me' => 'facebook',
            'fb.com' => 'facebook', 'instagram' => 'instagram', 'wa.me' => 'whatsapp',
            'whatsapp' => 'whatsapp', 't.co' => 'twitter', 'twitter.' => 'twitter',
            'x.com' => 'twitter', 'youtube.' => 'youtube', 'youtu.be' => 'youtube',
            'tiktok' => 'tiktok', 'linkedin' => 'linkedin', 't.me' => 'telegram',
            'bing.' => 'cerca', 'yahoo.' => 'cerca', 'duckduckgo' => 'cerca', 'ecosia' => 'cerca',
        ];
        foreach ($map as $needle => $font) {
            if (str_contains($host, $needle)) return $font;
        }
        return 'altres';
    }

    /** Normalitza un utm_source conegut a la nostra font canònica (o null). */
    private static function normalitza(string $s): ?string
    {
        if ($s === 'fb' || str_contains($s, 'face')) return 'facebook';
        if ($s === 'ig' || str_contains($s, 'insta')) return 'instagram';
        if (str_contains($s, 'google')) return 'google';
        if (str_contains($s, 'mail') || str_contains($s, 'news') || str_contains($s, 'email') || str_contains($s, 'butlleti')) return 'mailing';
        if ($s === 'wa' || str_contains($s, 'whats')) return 'whatsapp';
        if ($s === 'x' || str_contains($s, 'twitter')) return 'twitter';
        if (str_contains($s, 'youtube') || str_contains($s, 'youtu')) return 'youtube';
        if (str_contains($s, 'tiktok')) return 'tiktok';
        if (str_contains($s, 'linkedin')) return 'linkedin';
        if (str_contains($s, 'telegram')) return 'telegram';
        return null;
    }
}
