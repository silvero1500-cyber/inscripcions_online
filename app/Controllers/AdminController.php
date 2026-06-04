<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\View;

final class AdminController
{
    public function dashboard(Request $req): void
    {
        $user = Auth::user();
        $db = Database::getInstance();

        if ($user->rol === 'superadmin') {
            // Superadmin: totals globals
            $eventos   = (int) $db->query('SELECT COUNT(*) FROM eventos')->fetchColumn();
            $inscritos = (int) $db->query("SELECT COUNT(*) FROM inscritos WHERE estado = 'confirmado'")->fetchColumn();
        } else {
            // Organitzador: només els seus esdeveniments assignats i els seus inscrits
            $eventos = (int) $db->query(
                "SELECT COUNT(DISTINCT e.id)
                 FROM eventos e
                 LEFT JOIN organizador_evento oe ON oe.evento_id = e.id
                 WHERE e.propietario_id = ? OR oe.usuario_id = ?",
                [$user->id, $user->id]
            )->fetchColumn();

            $inscritos = (int) $db->query(
                "SELECT COUNT(*) FROM inscritos i
                 WHERE i.estado = 'confirmado'
                   AND i.evento_id IN (
                       SELECT e.id FROM eventos e
                       LEFT JOIN organizador_evento oe ON oe.evento_id = e.id
                       WHERE e.propietario_id = ? OR oe.usuario_id = ?
                   )",
                [$user->id, $user->id]
            )->fetchColumn();
        }

        View::render('admin/dashboard', [
            'user'  => $user,
            'stats' => ['eventos' => $eventos, 'inscritos' => $inscritos],
        ], layout: 'admin');
    }
}
