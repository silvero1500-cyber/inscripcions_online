<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Evento;
use App\Models\GrupoAforo;
use App\Models\Tarifa;
use App\Services\ImageUploader;

final class PublicController
{
    public function index(Request $req): void
    {
        $eventos = Database::getInstance()->query(
            "SELECT e.id, e.titulo, e.slug, e.descripcion, e.fecha_evento, e.imagen_portada,
                    e.aforo_maximo, e.inscripciones_abiertas,
                    (SELECT MIN(precio) FROM tarifas_evento
                     WHERE evento_id = e.id AND activo = 1) AS precio_min
             FROM eventos e
             WHERE e.activo = 1 AND e.archivado_at IS NULL
             ORDER BY e.fecha_evento ASC, e.id ASC"
        )->fetchAll();

        View::render('public/eventos/index', [
            'eventos'         => $eventos,
            'pageTitle'       => t('home.title') . ' · WeRun',
            'metaDescription' => t('home.subtitle'),
            'ogTitle'         => t('home.title'),
            'ogDescription'   => t('home.subtitle'),
            'ogUrl'           => base_url('/'),
        ], layout: 'public');
    }

    public function show(Request $req, array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        $evento = Evento::findBySlug($slug);

        if ($evento === null || (int) $evento['activo'] !== 1 || !empty($evento['archivado_at'])) {
            Response::notFound();
        }

        $campos = Database::getInstance()->query(
            'SELECT * FROM campos_personalizados
             WHERE evento_id = ? AND activo = 1
             ORDER BY orden ASC, id ASC',
            [$evento['id']]
        )->fetchAll();

        $tarifas    = Tarifa::listDisponibles((int) $evento['id']);
        $gruposRest = GrupoAforo::plazasRestantesByEvento((int) $evento['id']);

        // Places per tarifa (per marcar les esgotades al desplegable).
        // Si la tarifa té grup, fa servir les places restants del grup (compartides).
        $hayDisponibles = false;
        foreach ($tarifas as &$t) {
            if (!empty($t['grupo_aforo_id']) && isset($gruposRest[(int)$t['grupo_aforo_id']])) {
                $rest = $gruposRest[(int)$t['grupo_aforo_id']];
            } else {
                $rest = Tarifa::plazasRestantes($t);
            }
            $t['_plazas']  = $rest;                          // null = sense límit
            $t['_agotada'] = ($rest !== null && $rest <= 0);
            if (!$t['_agotada']) $hayDisponibles = true;
        }
        unset($t);

        // Estado de inscripciones (tancat si no queda cap tarifa amb places)
        $abierto           = self::inscripcionesAbiertas($evento) && $hayDisponibles;
        $plazasDisponibles = self::plazasDisponibles($evento);

        // Recuperar datos del último intento fallido (si los hay)
        $oldJson    = Session::pullFlash('insc_old');
        $errorsJson = Session::pullFlash('insc_errors');

        // ── Open Graph (preview en compartir) ──
        $ogImage = ImageUploader::publicUrl($evento['imagen_portada'] ?? null);
        $plain   = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) ($evento['descripcion'] ?? ''))));
        if ($plain === '') {
            $plain = $evento['titulo'] . ' · ' . format_date_ca((string) $evento['fecha_evento']);
            if (!empty($evento['localizacion'])) {
                $plain .= ' · ' . $evento['localizacion'];
            }
        }
        $metaDesc = mb_substr($plain, 0, 180);

        View::render('public/eventos/show', [
            'evento'             => $evento,
            'campos'             => $campos,
            'tarifas'            => $tarifas,
            'abierto'            => $abierto,
            'plazasDisponibles'  => $plazasDisponibles,
            'old'                => $oldJson    ? (json_decode($oldJson, true) ?: [])    : [],
            'errors'             => $errorsJson ? (json_decode($errorsJson, true) ?: []) : [],
            'flashError'         => Session::pullFlash('error'),
            // Autoreblert de proves: a tots els esdeveniments si ets admin (logat),
            // o per a qualsevol amb ?prova=1. (Temporal, mentre estem en proves.)
            'mostraAutofill'     => ($req->query('prova') === '1') || Auth::check(),
            'pageTitle'          => $evento['titulo'] . ' · WeRun',
            'metaDescription'    => $metaDesc,
            'ogTitle'            => $evento['titulo'],
            'ogDescription'      => $metaDesc,
            'ogImage'            => $ogImage,
            'ogUrl'              => base_url('/eventos/' . $evento['slug']),
            'ogType'             => 'article',
        ], layout: 'public');
    }

    public static function inscripcionesAbiertas(array $evento): bool
    {
        if ((int) $evento['inscripciones_abiertas'] !== 1) return false;

        if (!empty($evento['fecha_limite_inscripcion'])) {
            $limite = strtotime((string) $evento['fecha_limite_inscripcion']);
            if ($limite !== false && $limite < time()) return false;
        }

        // Verificar aforo
        if (!empty($evento['aforo_maximo'])) {
            $usadas = \App\Models\Inscrito::countActivasByEvento((int) $evento['id']);
            if ($usadas >= (int) $evento['aforo_maximo']) return false;
        }

        return true;
    }

    public static function plazasDisponibles(array $evento): ?int
    {
        if (empty($evento['aforo_maximo'])) return null;
        $usadas = \App\Models\Inscrito::countActivasByEvento((int) $evento['id']);
        return max(0, (int) $evento['aforo_maximo'] - $usadas);
    }
}
