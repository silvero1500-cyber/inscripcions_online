<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Carrera;
use App\Models\Evento;

/**
 * Entrada per carrera des de la barra superior. En clicar una carrera s'obre la
 * seva "home": l'edició activa (la de l'any en curs o la més recent no arxivada)
 * amb KPIs i botons per operar (Veure / Inscrits / Recollida / KPIs / Editar).
 */
final class CarreraController
{
    public function enter(Request $req, array $params): void
    {
        $user = Auth::user();
        $slug = (string) ($params['slug'] ?? '');

        $carrera = Carrera::findBySlug($slug);
        if ($carrera === null) {
            Session::flash('error', 'Cursa no trobada.');
            Response::redirect(base_url('/admin/eventos'));
        }

        $edicion = Carrera::edicionActiva((int) $carrera['id']);
        if ($edicion === null) {
            Session::flash('error', 'La cursa «' . $carrera['nombre'] . '» encara no té cap edició. Crea\'n una.');
            Response::redirect(base_url('/admin/eventos'));
        }

        if (!Evento::userCanEdit($user->id, $user->rol, (int) $edicion['id'])) {
            Response::forbidden();
        }

        $eventoId = (int) $edicion['id'];
        $db = Database::getInstance();

        // KPIs de l'edició activa
        $stats = [
            'inscritos' => (int) $db->query(
                "SELECT COUNT(*) FROM inscritos WHERE estado='confirmado' AND evento_id = ?", [$eventoId]
            )->fetchColumn(),
            'recollits' => (int) $db->query(
                "SELECT COUNT(*) FROM inscritos WHERE estado='confirmado' AND dorsal_recollit_at IS NOT NULL AND evento_id = ?", [$eventoId]
            )->fetchColumn(),
            'darrers7'  => (int) $db->query(
                "SELECT COUNT(*) FROM inscritos WHERE estado='confirmado' AND evento_id = ? AND created_at >= (NOW() - INTERVAL 7 DAY)", [$eventoId]
            )->fetchColumn(),
        ];
        $stats['pendents'] = max(0, $stats['inscritos'] - $stats['recollits']);

        // Comparativa amb l'edició anterior de la mateixa carrera al MATEIX punt
        // del compte enrere (mateixos dies abans de la cursa). null si no n'hi ha.
        $stats['comp_delta']   = null;
        $stats['comp_any']     = null;
        $carreraId = $edicion['carrera_id'] ?? $carrera['id'] ?? null;
        $anioEd    = $edicion['anio_edicion'] ?? null;
        if (!empty($carreraId) && !empty($anioEd)) {
            $ant = $db->query(
                "SELECT id, fecha_evento, anio_edicion FROM eventos WHERE carrera_id = ? AND anio_edicion = ? LIMIT 1",
                [(int) $carreraId, (int) $anioEd - 1]
            )->fetch();
            if ($ant) {
                // Dies que falten avui per a la cursa actual (mínim 0)
                $diesFalten = (int) $db->query(
                    "SELECT GREATEST(0, DATEDIFF(?, CURDATE()))", [(string) $edicion['fecha_evento']]
                )->fetchColumn();
                // Data equivalent dins l'edició anterior
                $tall = (string) $db->query(
                    "SELECT DATE_SUB(?, INTERVAL ? DAY)", [(string) $ant['fecha_evento'], $diesFalten]
                )->fetchColumn();
                $antMateixPunt = (int) $db->query(
                    "SELECT COUNT(*) FROM inscritos
                     WHERE evento_id = ? AND estado IN ('pendiente','confirmado') AND created_at <= ?",
                    [(int) $ant['id'], $tall . ' 23:59:59']
                )->fetchColumn();
                $stats['comp_delta'] = $stats['inscritos'] - $antMateixPunt;
                $stats['comp_any']   = (int) $ant['anio_edicion'];
            }
        }

        // Altres edicions de la carrera (per canviar d'any)
        $edicions = Carrera::edicionesByCarrera((int) $carrera['id'], true);

        View::render('admin/carrera/home', [
            'user'     => $user,
            'carrera'  => $carrera,
            'edicion'  => $edicion,
            'edicions' => $edicions,
            'stats'    => $stats,
            'flash'    => Session::pullAllFlashes(),
        ], layout: 'admin');
    }
}
