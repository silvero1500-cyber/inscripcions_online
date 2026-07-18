<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Incidencia;

/**
 * Bústia d'incidències del formulari públic, vista des de l'admin (superadmin).
 */
final class IncidenciasController
{
    public function index(Request $req): void
    {
        $estado = $req->query('estado') ?: null;

        View::render('admin/incidencias/index', [
            'user'        => Auth::user(),
            'incidencias' => Incidencia::listRecent(300, $estado),
            'estado'      => $estado,
            'noves'       => Incidencia::countNoves(),
            'flash'       => Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    public function resolta(Request $req, array $params): void
    {
        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();
        Incidencia::marcarResolta((int) ($params['id'] ?? 0));
        Session::flash('success', 'Incidència marcada com a resolta.');
        Response::redirect(base_url('/admin/incidencies'));
    }

    public function nova(Request $req, array $params): void
    {
        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();
        Incidencia::marcarNova((int) ($params['id'] ?? 0));
        Session::flash('success', 'Incidència reoberta.');
        Response::redirect(base_url('/admin/incidencies'));
    }

    public function eliminar(Request $req, array $params): void
    {
        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();
        Incidencia::eliminar((int) ($params['id'] ?? 0));
        Session::flash('success', 'Incidència eliminada.');
        Response::redirect(base_url('/admin/incidencies'));
    }
}
