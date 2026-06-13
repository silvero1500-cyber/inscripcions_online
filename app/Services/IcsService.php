<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Genera un fitxer iCalendar (.ics) per a un esdeveniment, per adjuntar-lo als
 * correus de confirmació ("Afegeix al calendari"). L'esdeveniment és de dia
 * complet (eventos.fecha_evento és DATE, sense hora).
 */
final class IcsService
{
    public static function build(array $evento, string $uidSeed = ''): string
    {
        $fecha    = (string) ($evento['fecha_evento'] ?? '');
        $dtStart  = self::dateCompact($fecha);              // YYYYMMDD
        $dtEnd    = self::dateCompact($fecha, '+1 day');    // dia complet → fi l'endemà
        $stamp    = gmdate('Ymd\THis\Z');
        $uid      = 'evento-' . (int) ($evento['id'] ?? 0) . '-'
                  . substr(md5($uidSeed . $fecha), 0, 10) . '@werun.cat';

        $summary  = self::esc((string) ($evento['titulo'] ?? 'Esdeveniment'));
        $location = self::esc((string) ($evento['localizacion'] ?? ''));
        $url      = base_url('/eventos/' . (string) ($evento['slug'] ?? ''));
        $descPlain = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) ($evento['descripcion'] ?? ''))));
        if ($descPlain === '') $descPlain = (string) ($evento['titulo'] ?? '');
        $description = self::esc($descPlain . "\n\n" . $url);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//WeRun//Inscripcions//CA',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $stamp,
            'DTSTART;VALUE=DATE:' . $dtStart,
            'DTEND;VALUE=DATE:' . $dtEnd,
            'SUMMARY:' . $summary,
        ];
        if ($location !== '') $lines[] = 'LOCATION:' . $location;
        $lines[] = 'DESCRIPTION:' . $description;
        $lines[] = 'URL:' . $url;
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        // Plega línies llargues (RFC 5545: màx 75 octets) i acaba amb CRLF
        $folded = array_map([self::class, 'fold'], $lines);
        return implode("\r\n", $folded) . "\r\n";
    }

    private static function dateCompact(string $date, string $modify = ''): string
    {
        $t = strtotime(trim($date . ' ' . $modify));
        if ($t === false) $t = time();
        return date('Ymd', $t);
    }

    private static function esc(string $s): string
    {
        $s = str_replace(['\\', ';', ','], ['\\\\', '\\;', '\\,'], $s);
        return str_replace(["\r\n", "\n", "\r"], '\\n', $s);
    }

    /** Plegat per octets: trossos de 73 bytes units amb CRLF + espai. */
    private static function fold(string $line): string
    {
        if (strlen($line) <= 75) return $line;
        return implode("\r\n ", str_split($line, 73));
    }
}
