<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\Migrator;

/**
 * Pàgina d'administració de migracions de base de dades (només superadmin).
 * Substitueix el patró insegur de pujar scripts PHP amb token a l'arrel web.
 */
final class MigrationController
{
    public function index(Request $req): void
    {
        View::render('admin/migracions/index', [
            'user'       => Auth::user(),
            'migrations' => Migrator::status(),
            'flash'      => Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    public function apply(Request $req): void
    {
        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada. Torna-ho a provar.');
            Response::redirect(base_url('/admin/migracions'));
        }

        $results = Migrator::applyPending();

        $okCount  = count(array_filter($results, fn($r) => $r['ok']));
        $failed   = array_values(array_filter($results, fn($r) => !$r['ok']));

        if (count($results) === 0) {
            Session::flash('success', 'No hi havia cap migració pendent.');
        } elseif (count($failed) === 0) {
            Session::flash('success', "S'han aplicat {$okCount} migracions correctament.");
        } else {
            $f = $failed[0];
            Session::flash('error', "Aplicades {$okCount}. Ha fallat «{$f['filename']}»: " . $f['error']);
        }

        Response::redirect(base_url('/admin/migracions'));
    }
}
