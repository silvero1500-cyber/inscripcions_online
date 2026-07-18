<?php

declare(strict_types=1);

/**
 * Escapado HTML para usar dentro de vistas: <?= e($var) ?>
 * Definida en el namespace global para que las vistas (que no declaran namespace)
 * la encuentren al hacer la llamada sin prefijo.
 */
if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

/**
 * Construye una URL absoluta basada en APP_URL del .env.
 *   base_url()                  -> https://werun.cat/inscripcions
 *   base_url('/admin/login')    -> https://werun.cat/inscripcions/admin/login
 */
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $base = rtrim((string) \App\Core\Env::get('APP_URL', ''), '/');
        if ($path === '') return $base;
        return $base . '/' . ltrim($path, '/');
    }
}

/**
 * Devuelve el path-prefix del subdir (lo que va después del host en APP_URL).
 *   /inscripcions
 */
if (!function_exists('base_path_prefix')) {
    function base_path_prefix(): string
    {
        $url = (string) \App\Core\Env::get('APP_URL', '');
        $path = parse_url($url, PHP_URL_PATH);
        return $path ? '/' . trim($path, '/') : '';
    }
}

/**
 * Devuelve la URL absoluta de un asset estático.
 *   asset('css/admin.css') -> https://werun.cat/inscripcions/public/assets/css/admin.css
 */
if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $rel  = ltrim($path, '/');
        $file = BASE_PATH . '/public/assets/' . $rel;
        // Cache-busting: la data de modificació com a versió, perquè els
        // navegadors (sobretot mòbils) no serveixin JS/CSS antic després d'un deploy.
        $v = is_file($file) ? (string) filemtime($file) : '';
        return base_url('/public/assets/' . $rel) . ($v !== '' ? '?v=' . $v : '');
    }
}

/**
 * Formatea una fecha (YYYY-MM-DD o datetime) en l'idioma actual:
 * "12 de juny de 2026" (ca) / "12 de junio de 2026" (es).
 */
if (!function_exists('format_date_ca')) {
    function format_date_ca(string $isoDate, bool $withWeekday = false): string
    {
        $t = strtotime($isoDate);
        if ($t === false) return $isoDate;

        if (current_locale() === 'es') {
            $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                      'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
            $dies  = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        } else {
            $meses = ['gener', 'febrer', 'març', 'abril', 'maig', 'juny',
                      'juliol', 'agost', 'setembre', 'octubre', 'novembre', 'desembre'];
            $dies  = ['diumenge', 'dilluns', 'dimarts', 'dimecres', 'dijous', 'divendres', 'dissabte'];
        }

        $d = (int) date('j', $t);
        $m = (int) date('n', $t) - 1;
        $y = (int) date('Y', $t);

        $str = "{$d} de {$meses[$m]} de {$y}";
        if ($withWeekday) {
            $w = (int) date('w', $t);
            $str = ucfirst($dies[$w]) . ", {$str}";
        }
        return $str;
    }
}

/**
 * Tradueix una clau d'idioma al locale actual.
 *   t('home.title')                  -> "Inscripcions obertes"  (catalán)
 *   t('event.capacity_value', ['n' => 50])  -> "50 places disponibles"
 */
if (!function_exists('t')) {
    function t(string $key, array $vars = []): string
    {
        return \App\Core\Lang::t($key, $vars);
    }
}

if (!function_exists('current_locale')) {
    function current_locale(): string
    {
        return \App\Core\Lang::current();
    }
}

/**
 * Tradueix textos de camps personalitzats (etiquetes, opcions, ajudes), que
 * viuen a la BD en català. Busca la clau 'custom.<text>' al fitxer d'idioma
 * actual; si no hi ha traducció, retorna el text original tal qual.
 * Només tradueix el que ES MOSTRA — els values dels inputs mantenen l'original
 * perquè les dades desades i les validacions no canviïn.
 */
if (!function_exists('tc')) {
    function tc(string $text): string
    {
        $key = 'custom.' . $text;
        $val = \App\Core\Lang::t($key);
        return $val === $key ? $text : $val;
    }
}

/**
 * Carreres (marques) actives per a la barra superior de l'admin.
 * Es cau en memòria per petició i és tolerant: si la taula encara no existeix
 * (abans d'aplicar la migració 033) retorna [] sense petar la pàgina.
 *
 * @return list<array<string,mixed>>
 */
if (!function_exists('current_carreres')) {
    function current_carreres(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        try {
            $cache = \App\Models\Carrera::allActivas();
        } catch (\Throwable $e) {
            $cache = [];
        }
        return $cache;
    }
}

/**
 * Formatea un precio en €: 12.50 -> "12,50 €"
 */
if (!function_exists('format_price')) {
    function format_price(float|string|null $value): string
    {
        return number_format((float) $value, 2, ',', '.') . ' €';
    }
}

/**
 * Formatea un datetime guardado en UTC (la conexión BD usa time_zone '+00:00')
 * a la hora local de Madrid (gestiona el horario de verano automáticamente).
 * Ej.: "2026-06-10 18:00:00" (UTC) -> "10/06/2026 20:00".
 */
if (!function_exists('format_datetime_local')) {
    function format_datetime_local(?string $utc, string $fmt = 'd/m/Y H:i'): string
    {
        if ($utc === null || trim($utc) === '') return '';
        try {
            $dt = new DateTime($utc, new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('Europe/Madrid'));
            return $dt->format($fmt);
        } catch (\Throwable $e) {
            return (string) $utc;
        }
    }
}
