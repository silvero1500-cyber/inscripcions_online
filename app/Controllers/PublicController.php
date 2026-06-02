<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Evento;
use App\Models\Tarifa;

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
             WHERE e.activo = 1
             ORDER BY e.fecha_evento ASC, e.id ASC"
        )->fetchAll();

        View::render('public/eventos/index', [
            'eventos' => $eventos,
        ], layout: 'public');
    }

    public function show(Request $req, array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        $evento = Evento::findBySlug($slug);

        if ($evento === null || (int) $evento['activo'] !== 1) {
            Response::notFound();
        }

        $campos = Database::getInstance()->query(
            'SELECT * FROM campos_personalizados
             WHERE evento_id = ? AND activo = 1
             ORDER BY orden ASC, id ASC',
            [$evento['id']]
        )->fetchAll();

        $tarifas = Tarifa::listDisponibles((int) $evento['id']);

        // Estado de inscripciones
        $abierto           = self::inscripcionesAbiertas($evento) && count($tarifas) > 0;
        $plazasDisponibles = self::plazasDisponibles($evento);

        // Recuperar datos del último intento fallido (si los hay)
        $oldJson    = Session::pullFlash('insc_old');
        $errorsJson = Session::pullFlash('insc_errors');

        View::render('public/eventos/show', [
            'evento'             => $evento,
            'campos'             => $campos,
            'tarifas'            => $tarifas,
            'abierto'            => $abierto,
            'plazasDisponibles'  => $plazasDisponibles,
            'old'                => $oldJson    ? (json_decode($oldJson, true) ?: [])    : [],
            'errors'             => $errorsJson ? (json_decode($errorsJson, true) ?: []) : [],
            'flashError'         => Session::pullFlash('error'),
            'mostraAutofill'     => ($req->query('prova') === '1'),
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
