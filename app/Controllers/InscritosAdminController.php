<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\Evento;
use App\Models\Inscrito;

final class InscritosAdminController
{
    public function index(Request $req): void
    {
        $user = Auth::user();
        $perPage = 100;
        $page = max(1, (int) ($req->query('page') ?? 1));

        $filters = [
            'evento_id' => $req->query('evento_id') ? (int) $req->query('evento_id') : null,
            'estado'    => $req->query('estado') ?: null,
            'search'    => $req->query('search') ?: null,
            'club'      => $req->query('club') ?: null,
        ];

        $eventos = self::listEventosForUser($user);

        if (empty($filters['evento_id']) && $user->rol !== 'superadmin' && count($eventos) > 0) {
            $filters['evento_id'] = (int) $eventos[0]['id'];
        }
        if ($filters['evento_id'] && !Evento::userCanEdit($user->id, $user->rol, (int) $filters['evento_id'])) {
            Response::forbidden();
        }

        $filtersClean = array_filter($filters, fn($v) => $v !== null && $v !== '');

        $total = 0;
        $inscritos = [];
        $counts = ['pendiente' => 0, 'confirmado' => 0, 'cancelado' => 0, 'reembolsado' => 0];
        $totalPages = 1;

        if ($filters['evento_id']) {
            $total = Inscrito::countForAdmin($filtersClean);
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($page > $totalPages) $page = $totalPages;
            $inscritos = Inscrito::listForAdmin($filtersClean, $page, $perPage);
            $counts = Inscrito::countsByEstadoForAdmin($filtersClean);
        }

        $from = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
        $to = min($total, $page * $perPage);

        View::render('admin/inscritos/index', [
            'user'       => $user,
            'eventos'    => $eventos,
            'inscritos'  => $inscritos,
            'filters'    => $filters,
            'counts'     => $counts,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => $totalPages,
            'from'       => $from,
            'to'         => $to,
        ], layout: 'admin');
    }

    /**
     * Exporta el listat (amb filtres aplicats) a CSV.
     * Compatible amb Excel: UTF-8 BOM + separador ;
     */
    public function export(Request $req): void
    {
        $user = Auth::user();

        $filters = [
            'evento_id' => $req->query('evento_id') ? (int) $req->query('evento_id') : null,
            'estado'    => $req->query('estado') ?: null,
            'search'    => $req->query('search') ?: null,
            'club'      => $req->query('club') ?: null,
        ];

        if (empty($filters['evento_id'])) {
            Response::redirect(base_url('/admin/inscritos'));
        }
        if (!Evento::userCanEdit($user->id, $user->rol, (int) $filters['evento_id'])) {
            Response::forbidden();
        }

        $evento = Evento::findById((int) $filters['evento_id']);
        $inscritos = Inscrito::listForAdminExport(array_filter($filters), 5000);

        $filename = 'inscrits_' . preg_replace('/[^a-z0-9_-]+/i', '_', (string) $evento['slug'])
                  . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache');

        $out = fopen('php://output', 'w');
        // BOM UTF-8 perquè Excel detecti l'encoding
        fwrite($out, "\xEF\xBB\xBF");

        // Capçaleres
        fputcsv($out, [
            'ID', 'Data inscripció', 'Nom', 'Cognoms', 'DNI', 'Sexe', 'Data naixement',
            'Email', 'Telèfon', 'Club', 'Població', 'Codi postal', 'Talla',
            'Tarifa', 'Preu', 'Estat', 'Check-in', 'Dorsal'
        ], ';');

        foreach ($inscritos as $i) {
            fputcsv($out, [
                $i['id'],
                $i['created_at'],
                $i['nombre'],
                $i['apellido'],
                $i['dni'],
                $i['sexo'],
                $i['fecha_nacimiento'],
                $i['email'],
                $i['telefono'],
                $i['club'] ?? '',
                $i['poblacion'] ?? '',
                $i['codigo_postal'] ?? '',
                $i['talla_camiseta'] ?? '',
                $i['tarifa_nombre'],
                number_format((float) $i['tarifa_precio'], 2, ',', '.'),
                $i['estado'],
                !empty($i['check_in_at']) ? $i['check_in_at'] : '',
                $i['numero_dorsal'] ?? '',
            ], ';');
        }

        fclose($out);
        exit;
    }

    /**
     * Llistat d'eventos que el user pot gestionar (per el select del filtre).
     * @return list<array<string,mixed>>
     */
    private static function listEventosForUser($user): array
    {
        if ($user->rol === 'superadmin') {
            return Database::getInstance()->query(
                "SELECT id, titulo, slug, fecha_evento
                 FROM eventos ORDER BY fecha_evento DESC, id DESC"
            )->fetchAll();
        }
        return Database::getInstance()->query(
            "SELECT e.id, e.titulo, e.slug, e.fecha_evento
             FROM eventos e
             WHERE e.propietario_id = ?
                OR EXISTS (SELECT 1 FROM organizador_evento oe WHERE oe.evento_id = e.id AND oe.usuario_id = ?)
             ORDER BY e.fecha_evento DESC, e.id DESC",
            [$user->id, $user->id]
        )->fetchAll();
    }
}
