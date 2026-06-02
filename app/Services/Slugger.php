<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Evento;

final class Slugger
{
    public static function make(string $text): string
    {
        $text = (string) $text;

        // Transliterar acentos a ASCII si está disponible iconv
        if (function_exists('iconv')) {
            $tr = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
            if ($tr !== false) $text = $tr;
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text) ?? '';
        $text = trim($text, '-');

        if ($text === '') {
            $text = 'evento-' . substr(bin2hex(random_bytes(4)), 0, 6);
        }

        return substr($text, 0, 200);
    }

    /**
     * Genera un slug único para eventos, añadiendo sufijo numérico si ya existe.
     */
    public static function uniqueForEvento(string $text, ?int $exceptId = null): string
    {
        $base = self::make($text);
        $slug = $base;
        $i    = 2;

        while (Evento::isSlugTaken($slug, $exceptId)) {
            $slug = $base . '-' . $i;
            $i++;
            if ($i > 1000) {
                $slug = $base . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
                break;
            }
        }

        return $slug;
    }
}
