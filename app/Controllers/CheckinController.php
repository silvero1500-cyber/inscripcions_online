<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Evento;
use App\Models\Inscrito;
use App\Models\Tarifa;
use App\Models\Usuario;

/**
 * Check-in de corredores el día de la carrera vía QR.
 *
 * Flujo:
 *   1) Organizador obre /admin/checkin → vista amb càmera (html5-qrcode)
 *   2) Escaneja el QR del corredor → URL /admin/checkin/{token}
 *   3) Veu la fitxa de l'inscrit + botó "Marcar check-in"
 *   4) Click → POST /admin/checkin/{token} → marcat amb timestamp
 */
final class CheckinController
{
    /**
     * Pantalla amb el lector de QR (càmera).
     */
    public function scanner(Request $req): void
    {
        $user = Auth::user();
        View::render('admin/checkin/scanner', [
            'user' => $user,
        ], layout: 'admin');
    }

    /**
     * Pàgina de diagnòstic de càmera (per debugar problemes mobile).
     */
    public function cameraTest(Request $req): void
    {
        $user = Auth::user();
        View::render('admin/checkin/camera_test', [
            'user' => $user,
        ], layout: 'admin');
    }

    /**
     * Mostra la fitxa de l'inscrit a partir del token escanejat.
     */
    public function show(Request $req, array $params): void
    {
        $user  = Auth::user();
        $token = (string) ($params['token'] ?? '');

        $inscrito = Inscrito::findByQrToken($token);
        if ($inscrito === null) {
            View::render('admin/checkin/error', [
                'user'    => $user,
                'mensaje' => 'QR no vàlid o no trobat.',
            ], layout: 'admin');
            return;
        }

        $evento = Evento::findById((int) $inscrito['evento_id']);
        $tarifa = Tarifa::findById((int) $inscrito['tarifa_id']);

        // Comprovació de permisos: només l'organitzador o superadmin
        if (!Evento::userCanEdit($user->id, $user->rol, (int) $inscrito['evento_id'])) {
            View::render('admin/checkin/error', [
                'user'    => $user,
                'mensaje' => 'No tens permís per gestionar aquest esdeveniment.',
            ], layout: 'admin');
            return;
        }

        $marcadoPor = null;
        if (!empty($inscrito['check_in_por'])) {
            $marcadoPor = Usuario::findById((int) $inscrito['check_in_por']);
        }

        View::render('admin/checkin/show', [
            'user'       => $user,
            'inscrito'   => $inscrito,
            'evento'     => $evento,
            'tarifa'     => $tarifa,
            'token'      => $token,
            'marcadoPor' => $marcadoPor,
            'flash'      => Session::pullFlash('checkin'),
        ], layout: 'admin');
    }

    /**
     * Marca el check-in. POST només.
     */
    public function mark(Request $req, array $params): void
    {
        $user  = Auth::user();
        $token = (string) ($params['token'] ?? '');

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('checkin', 'Sessió expirada. Recarrega.');
            Response::redirect(base_url('/admin/checkin/' . urlencode($token)));
        }

        $inscrito = Inscrito::findByQrToken($token);
        if ($inscrito === null) {
            Response::redirect(base_url('/admin/checkin'));
        }

        if (!Evento::userCanEdit($user->id, $user->rol, (int) $inscrito['evento_id'])) {
            Response::forbidden();
        }

        $ok = Inscrito::marcarCheckIn((int) $inscrito['id'], $user->id);
        Session::flash('checkin', $ok ? 'success' : 'already');

        Response::redirect(base_url('/admin/checkin/' . urlencode($token)));
    }
}
