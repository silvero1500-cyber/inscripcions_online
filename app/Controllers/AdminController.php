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

        // Stats básicas para el dashboard
        $stats = [
            'eventos'    => (int) $db->query('SELECT COUNT(*) FROM eventos')->fetchColumn(),
            'inscritos'  => (int) $db->query("SELECT COUNT(*) FROM inscritos WHERE estado = 'confirmado'")->fetchColumn(),
            'pendientes' => (int) $db->query("SELECT COUNT(*) FROM inscritos WHERE estado = 'pendiente'")->fetchColumn(),
        ];

        View::render('admin/dashboard', [
            'user'  => $user,
            'stats' => $stats,
        ], layout: 'admin');
    }
}
