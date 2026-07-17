<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\BackupService;

/**
 * Accés a les còpies de seguretat CSV (només superadmin): llistat i descàrrega.
 * Els fitxers viuen a storage/backups/<YYYY-MM-DD[_HHh]>/inscrits_<slug>.csv.
 */
final class BackupsAdminController
{
    public function index(Request $req): void
    {
        $dir = BASE_PATH . '/storage/backups';
        $grups = [];

        foreach (glob($dir . '/????-??-??*', GLOB_ONLYDIR) ?: [] as $sub) {
            $files = [];
            foreach (glob($sub . '/*.csv') ?: [] as $f) {
                $files[] = [
                    'name'  => basename($f),
                    'size'  => filesize($f),
                    'mtime' => filemtime($f),
                ];
            }
            if ($files) {
                $grups[basename($sub)] = $files;
            }
        }
        krsort($grups); // més recents primer

        View::render('admin/backups/index', [
            'user'  => Auth::user(),
            'grups' => $grups,
        ], layout: 'admin');
    }

    public function download(Request $req): void
    {
        $carpeta = (string) $req->query('c', '');
        $fitxer  = (string) $req->query('f', '');

        // Només noms simples esperats: cap barra, cap "..", formats coneguts
        if (!preg_match('/^\d{4}-\d{2}-\d{2}(_\d{2}h)?$/', $carpeta)
            || !preg_match('/^[a-z0-9_\-]+\.csv$/i', $fitxer)) {
            Response::notFound();
        }

        $path = BASE_PATH . '/storage/backups/' . $carpeta . '/' . $fitxer;
        $real = realpath($path);
        $base = realpath(BASE_PATH . '/storage/backups');
        if ($real === false || $base === false || !str_starts_with($real, $base)) {
            Response::notFound();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $carpeta . '_' . $fitxer . '"');
        header('Content-Length: ' . (string) filesize($real));
        header('Cache-Control: no-store, no-cache');
        readfile($real);
        exit;
    }

    /** Genera una còpia ara mateix (botó del panell). */
    public function generar(Request $req): void
    {
        if (!\App\Core\Csrf::verify($req->post('_csrf'))) Response::forbidden();

        // Força l'execució encara que no toqui per interval
        $marker = BASE_PATH . '/storage/backups/.last-run';
        @unlink($marker);
        BackupService::runDailyIfDue();

        \App\Core\Session::flash('success', 'Còpia de seguretat generada.');
        Response::redirect(base_url('/admin/backups'));
    }
}
