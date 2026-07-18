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

        // Esdeveniments que aquest usuari pot gestionar (superadmin = tots)
        if ($user->rol === 'superadmin') {
            $eventoIds = $db->query('SELECT id FROM eventos')->fetchAll(\PDO::FETCH_COLUMN);
        } else {
            $eventoIds = $db->query(
                "SELECT DISTINCT e.id FROM eventos e
                 LEFT JOIN organizador_evento oe ON oe.evento_id = e.id
                 WHERE e.propietario_id = ? OR oe.usuario_id = ?",
                [$user->id, $user->id]
            )->fetchAll(\PDO::FETCH_COLUMN);
        }
        $eventoIds = array_map('intval', $eventoIds);

        $stats     = ['eventos' => 0, 'inscritos' => 0, 'recollits' => 0, 'darrers7' => 0];
        $proxima   = null;   // carrera més pròxima destacada
        $proxims   = [];     // llista de pròxims esdeveniments
        $chart      = [];    // inscripcions per dia (14 dies)
        $chartMax   = 0;

        if ($eventoIds) {
            $in = implode(',', $eventoIds);

            $stats['eventos'] = (int) $db->query(
                "SELECT COUNT(*) FROM eventos WHERE id IN ($in) AND archivado_at IS NULL AND activo = 1"
            )->fetchColumn();
            $stats['inscritos'] = (int) $db->query(
                "SELECT COUNT(*) FROM inscritos WHERE estado='confirmado' AND evento_id IN ($in)"
            )->fetchColumn();
            $stats['recollits'] = (int) $db->query(
                "SELECT COUNT(*) FROM inscritos WHERE estado='confirmado' AND dorsal_recollit_at IS NOT NULL AND evento_id IN ($in)"
            )->fetchColumn();
            $stats['darrers7'] = (int) $db->query(
                "SELECT COUNT(*) FROM inscritos WHERE estado='confirmado' AND evento_id IN ($in) AND created_at >= (NOW() - INTERVAL 7 DAY)"
            )->fetchColumn();

            // Pròxims esdeveniments (no arxivats, data >= avui), amb inscrits confirmats
            $proxims = $db->query(
                "SELECT e.id, e.titulo, e.slug, e.fecha_evento, e.activo, e.inscripciones_abiertas, e.aforo_maximo,
                        (SELECT COUNT(*) FROM inscritos i WHERE i.evento_id = e.id AND i.estado='confirmado') AS inscritos
                 FROM eventos e
                 WHERE e.id IN ($in) AND e.archivado_at IS NULL AND e.fecha_evento >= CURDATE()
                 ORDER BY e.fecha_evento ASC
                 LIMIT 6"
            )->fetchAll();
            if ($proxims) {
                $proxima = $proxims[0];
                $proxims = array_slice($proxims, 1); // la resta a la llista
            }

            // Mini-gràfic: inscripcions confirmades per dia (darrers 14 dies)
            $rows = $db->query(
                "SELECT DATE(created_at) AS d, COUNT(*) AS n
                 FROM inscritos
                 WHERE estado='confirmado' AND evento_id IN ($in) AND created_at >= (NOW() - INTERVAL 13 DAY)
                 GROUP BY DATE(created_at)"
            )->fetchAll(\PDO::FETCH_KEY_PAIR);
            for ($i = 13; $i >= 0; $i--) {
                $day = date('Y-m-d', strtotime("-$i day"));
                $n = (int) ($rows[$day] ?? 0);
                $chart[] = ['day' => $day, 'n' => $n];
                if ($n > $chartMax) $chartMax = $n;
            }
        }

        // Comptador d'incidències sense resoldre (només rellevant per a superadmin)
        $incidenciesNoves = 0;
        if ($user->rol === 'superadmin') {
            try { $incidenciesNoves = \App\Models\Incidencia::countNoves(); } catch (\Throwable $e) { $incidenciesNoves = 0; }
        }

        View::render('admin/dashboard', [
            'user'     => $user,
            'stats'    => $stats,
            'proxima'  => $proxima,
            'proxims'  => $proxims,
            'chart'    => $chart,
            'chartMax' => $chartMax,
            'incidenciesNoves' => $incidenciesNoves,
        ], layout: 'admin');
    }
}
