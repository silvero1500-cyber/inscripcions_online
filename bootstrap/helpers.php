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
        return base_url('/public/assets/' . ltrim($path, '/'));
    }
}

/**
 * Formatea una fecha (YYYY-MM-DD o datetime) en català: "12 de juny de 2026".
 */
if (!function_exists('format_date_ca')) {
    function format_date_ca(string $isoDate, bool $withWeekday = false): string
    {
        $t = strtotime($isoDate);
        if ($t === false) return $isoDate;

        $meses = ['gener', 'febrer', 'març', 'abril', 'maig', 'juny',
                  'juliol', 'agost', 'setembre', 'octubre', 'novembre', 'desembre'];
        $dies  = ['diumenge', 'dilluns', 'dimarts', 'dimecres', 'dijous', 'divendres', 'dissabte'];

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
 * Formatea un precio en €: 12.50 -> "12,50 €"
 */
if (!function_exists('format_price')) {
    function format_price(float|string|null $value): string
    {
        return number_format((float) $value, 2, ',', '.') . ' €';
    }
}
