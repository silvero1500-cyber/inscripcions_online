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
 * Check-in retirat: la "validació" no s'usa. Tota lectura de QR es redirigeix a
 * la RECOLLIDA de dorsals (escanejar el QR sempre porta a recollir dorsal). Es
 * mantenen les rutes perquè els QR ja impresos/enviats (que apunten a
 * /admin/checkin/{token}) segueixin funcionant → redirigeixen a /admin/recollida.
 */
final class CheckinController
{
    public function scanner(Request $req): void
    {
        Response::redirect(base_url('/admin/recollida'));
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
     * Lectura del QR del corredor → redirigeix a la RECOLLIDA amb el token.
     */
    public function show(Request $req, array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        Response::redirect(base_url('/admin/recollida?token=' . urlencode($token)));
    }

    /** Compatibilitat: el POST de check-in ja no s'usa → cap a recollida. */
    public function mark(Request $req, array $params): void
    {
        $token = (string) ($params['token'] ?? '');
        Response::redirect(base_url('/admin/recollida?token=' . urlencode($token)));
    }
}
