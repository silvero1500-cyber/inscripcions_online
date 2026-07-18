<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Evento;
use App\Models\Usuario;

/**
 * Zona del rol restringit 'export': només llista els eventos assignats a
 * l'usuari i permet descarregar-ne el CSV d'inscrits. Cap altra dada ni acció.
 * Els superadmin també hi poden entrar (veuen tots els eventos).
 */
final class ExportController
{
    public function index(Request $req): void
    {
        $user = Auth::user();

        $eventos = $this->eventosPermesos($user);

        View::render('admin/export/index', [
            'user'    => $user,
            'eventos' => $eventos,
            'flash'   => \App\Core\Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    public function download(Request $req, array $params): void
    {
        $user = Auth::user();
        $eventoId = (int) ($params['id'] ?? 0);

        // Comprovació d'accés: ha de ser un dels eventos permesos per aquest usuari
        $permesos = array_map(fn($e) => (int) $e['id'], $this->eventosPermesos($user));
        if (!in_array($eventoId, $permesos, true)) {
            Response::forbidden();
        }

        AuditLog::registrar(AuditLog::INSCRITS_EXPORT, 'Export CSV (rol export) · evento #' . $eventoId);
        InscritosAdminController::streamCsv($eventoId);
    }

    /**
     * Eventos que aquest usuari pot exportar: superadmin = tots; export/altres =
     * els assignats via organizador_evento o dels quals és propietari.
     * @return list<array<string,mixed>>
     */
    private function eventosPermesos($user): array
    {
        $db = Database::getInstance();
        if ($user->rol === 'superadmin') {
            return $db->query(
                "SELECT id, titulo, slug, fecha_evento FROM eventos
                 WHERE archivado_at IS NULL ORDER BY fecha_evento DESC, id DESC"
            )->fetchAll();
        }
        return $db->query(
            "SELECT DISTINCT e.id, e.titulo, e.slug, e.fecha_evento
             FROM eventos e
             LEFT JOIN organizador_evento oe ON oe.evento_id = e.id
             WHERE e.archivado_at IS NULL
               AND (e.propietario_id = ? OR oe.usuario_id = ?)
             ORDER BY e.fecha_evento DESC, e.id DESC",
            [$user->id, $user->id]
        )->fetchAll();
    }
}
