<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\View;
use App\Models\AuditLog;

/**
 * Visor del registre d'auditoria de seguretat (només superadmin).
 */
final class AuditController
{
    public function index(Request $req): void
    {
        AuditLog::prune(365); // purga oportunista (RGPD): no acumular més d'1 any

        $accion = $req->query('accion') ?: null;

        View::render('admin/auditoria/index', [
            'user'   => Auth::user(),
            'logs'   => AuditLog::listRecent(300, $accion),
            'accion' => $accion,
        ], layout: 'admin');
    }
}
