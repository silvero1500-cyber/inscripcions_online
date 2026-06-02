<?php

declare(strict_types=1);

/**
 * Autoloader PSR-4 manual:
 *  - App\                       → app/
 *  - PHPMailer\PHPMailer\       → lib/PHPMailer/src/
 *  - chillerlan\QRCode\         → lib/chillerlan/QRCode/
 *  - chillerlan\Settings\       → lib/chillerlan/Settings/
 *
 * Si existe vendor/autoload.php (Composer instalado), también se carga.
 */

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'App\\'                  => dirname(__DIR__) . '/app/',
        'PHPMailer\\PHPMailer\\' => dirname(__DIR__) . '/lib/PHPMailer/src/',
        'chillerlan\\QRCode\\'   => dirname(__DIR__) . '/lib/chillerlan/QRCode/',
        'chillerlan\\Settings\\' => dirname(__DIR__) . '/lib/chillerlan/Settings/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (str_starts_with($class, $prefix)) {
            $relative = substr($class, strlen($prefix));
            $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
            return;
        }
    }
});

// Si Composer está disponible, cargar también sus dependencias
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

// Cargar helpers globales (base_url, e, format_*, ...) — necesarios para
// vistas y servicios que llamen a estas funciones desde cualquier contexto.
require_once __DIR__ . '/helpers.php';
