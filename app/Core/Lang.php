<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Carrega arrays de traduccions des de /lang/{code}.php
 * El locale actual es guarda en la cookie IO_LOCALE (12 mesos).
 * Si no n'hi ha, s'intenta detectar del Accept-Language; si tampoc, cau a 'ca'.
 */
final class Lang
{
    public const DEFAULT_LOCALE  = 'ca';
    public const ALLOWED_LOCALES = ['ca', 'es'];
    private const COOKIE_NAME    = 'IO_LOCALE';

    /** @var array<string, array<string,string>> Cache per request */
    private static array $cache = [];

    /** Cached resolved locale per request. */
    private static ?string $resolved = null;

    /**
     * Reseteja la resolució cachejada del locale actual.
     * Útil per a contexts on canviem $_COOKIE['IO_LOCALE'] manualment
     * (ex: enviar email amb el locale del destinatari).
     */
    public static function reset(): void
    {
        self::$resolved = null;
    }

    public static function current(): string
    {
        if (self::$resolved !== null) return self::$resolved;

        $cookie = $_COOKIE[self::COOKIE_NAME] ?? null;
        if ($cookie !== null && in_array($cookie, self::ALLOWED_LOCALES, true)) {
            return self::$resolved = $cookie;
        }

        $acceptLang = (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        foreach (explode(',', $acceptLang) as $part) {
            $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
            if (in_array($code, self::ALLOWED_LOCALES, true)) {
                return self::$resolved = $code;
            }
        }

        return self::$resolved = self::DEFAULT_LOCALE;
    }

    public static function setCookie(string $locale): void
    {
        if (!in_array($locale, self::ALLOWED_LOCALES, true)) return;
        setcookie(
            self::COOKIE_NAME,
            $locale,
            [
                'expires'  => time() + 365 * 24 * 3600,
                'path'     => '/',
                'secure'   => true,
                'httponly' => false, // ha de poder llegir-se per JS si volem
                'samesite' => 'Lax',
            ]
        );
        $_COOKIE[self::COOKIE_NAME] = $locale;
    }

    /** @return array<string,string> */
    public static function load(string $locale): array
    {
        if (!in_array($locale, self::ALLOWED_LOCALES, true)) {
            $locale = self::DEFAULT_LOCALE;
        }
        if (isset(self::$cache[$locale])) return self::$cache[$locale];

        $file = dirname(__DIR__, 2) . '/lang/' . $locale . '.php';
        if (!is_file($file)) {
            return self::$cache[$locale] = [];
        }
        $strings = require $file;
        return self::$cache[$locale] = is_array($strings) ? $strings : [];
    }

    /**
     * Tradueix una clau. Si no existeix en l'idioma actual, prova el default;
     * si tampoc, retorna la clau mateixa per fer evident el missing translation.
     *
     * @param array<string,string> $vars  Reemplaços tipus {name} → valor
     */
    public static function t(string $key, array $vars = []): string
    {
        $locale = self::current();
        $strings = self::load($locale);
        $val = $strings[$key] ?? null;
        if ($val === null && $locale !== self::DEFAULT_LOCALE) {
            $val = self::load(self::DEFAULT_LOCALE)[$key] ?? null;
        }
        if ($val === null) return $key;
        foreach ($vars as $k => $v) {
            $val = str_replace('{' . $k . '}', (string) $v, $val);
        }
        return $val;
    }
}
