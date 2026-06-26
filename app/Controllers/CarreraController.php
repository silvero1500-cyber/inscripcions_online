<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Carrera;
use App\Models\Evento;

/**
 * Entrada per carrera des de la barra superior. En clicar una carrera s'obre la
 * seva edició activa (la de l'any en curs o la més recent no arxivada) → es
 * redirigeix al panell/KPIs d'aquella edició. Si la carrera encara no té cap
 * edició, torna al llistat d'esdeveniments amb un avís.
 */
final class CarreraController
{
    public function enter(Request $req, array $params): void
    {
        $user = Auth::user();
        $slug = (string) ($params['slug'] ?? '');

        $carrera = Carrera::findBySlug($slug);
        if ($carrera === null) {
            Session::flash('error', 'Carrera no trobada.');
            Response::redirect(base_url('/admin/eventos'));
        }

        $edicion = Carrera::edicionActiva((int) $carrera['id']);
        if ($edicion === null) {
            Session::flash('error', 'La carrera «' . $carrera['nombre'] . '» encara no té cap edició. Crea\'n una.');
            Response::redirect(base_url('/admin/eventos'));
        }

        if (!Evento::userCanEdit($user->id, $user->rol, (int) $edicion['id'])) {
            Response::forbidden();
        }

        // Panell de l'edició = pantalla de KPIs ja existent
        Response::redirect(base_url('/admin/eventos/' . (int) $edicion['id'] . '/kpis'));
    }
}
