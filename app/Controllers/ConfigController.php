<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Ajuste;

/**
 * Configuració general de la plataforma (només superadmin).
 * Primer ús: URL de la política de privacitat (consentiment RGPD).
 */
final class ConfigController
{
    public function index(Request $req): void
    {
        View::render('admin/config/index', [
            'user'          => Auth::user(),
            'privacidadUrl' => Ajuste::get(Ajuste::PRIVACIDAD_URL, ''),
            'flash'         => Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    public function store(Request $req): void
    {
        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada. Torna-ho a provar.');
            Response::redirect(base_url('/admin/configuracio'));
        }

        $url = trim((string) $req->post('privacidad_url', ''));
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            Session::flash('error', "L'enllaç de la política de privacitat no és una URL vàlida.");
            Response::redirect(base_url('/admin/configuracio'));
        }
        Ajuste::set(Ajuste::PRIVACIDAD_URL, $url !== '' ? mb_substr($url, 0, 500) : null);
        Session::flash('success', 'Configuració desada.');
        Response::redirect(base_url('/admin/configuracio'));
    }
}
