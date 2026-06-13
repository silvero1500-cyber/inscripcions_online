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
use App\Models\ListaEspera;
use App\Models\Tarifa;
use App\Services\EmailService;

/**
 * Llista d'espera: captura pública quan no queden places + gestió/avís des de l'admin.
 */
final class WaitingListController
{
    // ── Públic: apuntar-se ──────────────────────────────────
    public function store(Request $req, array $params): void
    {
        $slug   = (string) ($params['slug'] ?? '');
        $evento = Evento::findBySlug($slug);
        if ($evento === null || (int) $evento['activo'] !== 1 || !empty($evento['archivado_at'])) {
            Response::notFound();
        }
        $back = base_url('/eventos/' . $slug) . '#llista-espera';

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('espera_error', t('waitlist.err_session'));
            Response::redirect($back);
        }
        // Honeypot anti-bot
        if (trim((string) $req->post('website', '')) !== '') {
            Session::flash('espera_ok', t('waitlist.ok')); // simula èxit, no desa res
            Response::redirect($back);
        }

        $email  = strtolower(trim((string) $req->post('email', '')));
        $nombre = mb_substr(trim((string) $req->post('nombre', '')), 0, 150) ?: null;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('espera_error', t('waitlist.err_email'));
            Response::redirect($back);
        }

        // Tarifa preferida (opcional): ha de pertànyer a l'esdeveniment
        $tarifaId = (int) $req->post('tarifa_id', 0) ?: null;
        if ($tarifaId !== null) {
            $t = Tarifa::findById($tarifaId);
            if ($t === null || (int) $t['evento_id'] !== (int) $evento['id']) {
                $tarifaId = null;
            }
        }

        ListaEspera::add((int) $evento['id'], $tarifaId, $nombre, $email);
        Session::flash('espera_ok', t('waitlist.ok'));
        Response::redirect($back);
    }

    // ── Admin: llistat ──────────────────────────────────────
    public function index(Request $req, array $params): void
    {
        $user     = Auth::user();
        $eventoId = (int) ($params['id'] ?? 0);
        $evento   = Evento::findById($eventoId);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $eventoId)) Response::forbidden();

        View::render('admin/llista_espera/index', [
            'user'    => $user,
            'evento'  => $evento,
            'entries' => ListaEspera::listByEvento($eventoId),
            'flash'   => Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    // ── Admin: avisar els pendents ──────────────────────────
    public function notify(Request $req, array $params): void
    {
        $user     = Auth::user();
        $eventoId = (int) ($params['id'] ?? 0);
        $back     = base_url('/admin/eventos/' . $eventoId . '/llista-espera');

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada. Torna-ho a provar.');
            Response::redirect($back);
        }
        $evento = Evento::findById($eventoId);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $eventoId)) Response::forbidden();

        $pendientes = ListaEspera::pendientes($eventoId);
        $sent = 0; $fail = 0;
        foreach ($pendientes as $e) {
            try {
                EmailService::sendListaEsperaAviso($e, $evento);
                ListaEspera::marcarNotificado((int) $e['id']);
                $sent++;
            } catch (\Throwable $ex) {
                $fail++;
                error_log('[WaitingList] avís fallit id=' . $e['id'] . ': ' . $ex->getMessage());
            }
        }

        if ($sent === 0 && $fail === 0) {
            Session::flash('error', 'No hi havia ningú pendent d\'avisar.');
        } else {
            $msg = "S'han enviat {$sent} avisos.";
            if ($fail > 0) $msg .= " {$fail} han fallat.";
            Session::flash('success', $msg);
        }
        Response::redirect($back);
    }
}
