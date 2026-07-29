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

    /**
     * El formulari està desbloquejat per a aquest visitant? (true si l'event no
     * té contrasenya, si ja l'ha encertada aquesta sessió, o si és un admin logat)
     */
    public static function formDesbloquejat(array $evento): bool
    {
        $pass = trim((string) ($evento['form_password'] ?? ''));
        if ($pass === '') return true;
        if (Auth::check()) return true;
        return Session::get('evt_acces_' . (int) $evento['id']) === true;
    }

    /** POST /eventos/{slug}/acces — valida la contrasenya d'accés al formulari. */
    public function acces(Request $req, array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        $evento = Evento::findBySlug($slug);
        if ($evento === null || (int) $evento['activo'] !== 1 || !empty($evento['archivado_at'])) {
            Response::notFound();
        }
        $back = base_url('/eventos/' . $slug);

        if (!\App\Core\Csrf::verify($req->post('_csrf'))) {
            Session::flash('acces_error', t('event.access.expired'));
            Response::redirect($back);
        }

        // Límit per IP contra força bruta de la contrasenya
        $rlKey = 'evtacces:' . $req->ip;
        if (\App\Models\RateLimit::tooMany($rlKey, 10, 600)) {
            Session::flash('acces_error', t('event.access.rate_limited'));
            Response::redirect($back);
        }
        \App\Models\RateLimit::hit($rlKey);

        $pass = trim((string) ($evento['form_password'] ?? ''));
        $introduida = trim((string) $req->post('acces_password', ''));
        if ($pass !== '' && $introduida !== '' && hash_equals($pass, $introduida)) {
            Session::set('evt_acces_' . (int) $evento['id'], true);
            Response::redirect($back . '#formulari');
        }

        Session::flash('acces_error', t('event.access.wrong'));
        Response::redirect($back);
    }

    public function show(Request $req, array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        $evento = Evento::findBySlug($slug);

        if ($evento === null || (int) $evento['activo'] !== 1 || !empty($evento['archivado_at'])) {
            Response::notFound();
        }

        // Contrasenya d'accés al formulari (opcional per event): si no s'ha
        // desbloquejat encara, es mostra la pantalla d'accés en lloc del formulari.
        if (!self::formDesbloquejat($evento)) {
            View::render('public/eventos/acces', [
                'evento'     => $evento,
                'accesError' => Session::pullFlash('acces_error'),
                'pageTitle'  => e($evento['titulo']) . ' · WeRun',
            ], layout: 'public');
            return;
        }

        // Registra la visita al formulari (KPI de connexions / hores punta).
        // El registre real es fa al shutdown, després d'enviar la resposta.
        \App\Services\VisitTracker::mark((int) $evento['id']);

        $campos = Database::getInstance()->query(
            'SELECT * FROM campos_personalizados
             WHERE evento_id = ? AND activo = 1 AND oculto = 0
             ORDER BY orden ASC, id ASC',
            [$evento['id']]
        )->fetchAll();

        // Caduca pendents abandonats abans de calcular places/aforament (sense cron)
        \App\Models\Inscrito::expirarPendientes((int) $evento['id']);

        $tarifas    = Tarifa::listDisponibles((int) $evento['id']);
        $gruposRest = GrupoAforo::plazasRestantesByEvento((int) $evento['id']);
        $tramosMap  = Tarifa::tramosByTarifas(array_map(fn($t) => (int) $t['id'], $tarifas));

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
            // Preu vigent ARA (segons els trams de data); cau al preu base si no n'hi ha
            $t['precio_actual'] = Tarifa::precioVigente($t, $tramosMap[(int) $t['id']] ?? []);
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
            'esperaOk'           => Session::pullFlash('espera_ok'),
            'esperaError'        => Session::pullFlash('espera_error'),
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
