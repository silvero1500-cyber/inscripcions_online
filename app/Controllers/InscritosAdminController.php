<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\CampoPersonalizado;
use App\Models\Evento;
use App\Models\Inscrito;
use App\Models\Tarifa;
use App\Services\EmailService;
use App\Services\QrService;

final class InscritosAdminController
{
    public function index(Request $req): void
    {
        $user = Auth::user();
        $perPage = 25;
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

        // Caduca pendents abandonats d'aquest evento (sense cron): així el llistat
        // sempre reflecteix places reals encara que l'evento no tingui trànsit públic.
        if ($filters['evento_id']) {
            Inscrito::expirarPendientes((int) $filters['evento_id']);
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
            'flash'      => Session::pullAllFlashes(),
            'wide'       => true, // pàgina a tot l'ample (taula amb moltes columnes)
        ], layout: 'admin');
    }

    /**
     * Fitxa d'un inscrit: totes les dades + respostes dels camps personalitzats
     * (inclosos els ocults), pagaments i companys de comanda.
     */
    public function show(Request $req, array $params): void
    {
        $user = Auth::user();
        $id   = (int) ($params['id'] ?? 0);

        $inscrito = Inscrito::findById($id);
        if ($inscrito === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, (int) $inscrito['evento_id'])) Response::forbidden();

        $evento = Evento::findById((int) $inscrito['evento_id']);
        $tarifa = !empty($inscrito['tarifa_id']) ? Tarifa::findById((int) $inscrito['tarifa_id']) : null;

        $campos  = CampoPersonalizado::listByEvento((int) $inscrito['evento_id']);
        $valores = CampoPersonalizado::valoresPorInscrito([$id])[$id] ?? [];

        $pagos = Database::getInstance()->query(
            'SELECT * FROM pagos WHERE inscrito_id = ? ORDER BY id DESC',
            [$id]
        )->fetchAll();

        // Companys de la mateixa comanda (inscripció de grup)
        $companys = [];
        if (!empty($inscrito['pedido_id'])) {
            $companys = Database::getInstance()->query(
                'SELECT id, nombre, apellido FROM inscritos WHERE pedido_id = ? AND id <> ? ORDER BY id ASC',
                [(int) $inscrito['pedido_id'], $id]
            )->fetchAll();
        }

        View::render('admin/inscritos/show', [
            'user'     => $user,
            'inscrito' => $inscrito,
            'evento'   => $evento,
            'tarifa'   => $tarifa,
            'campos'   => $campos,
            'valores'  => $valores,
            'pagos'    => $pagos,
            'companys' => $companys,
            'flash'    => Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    /**
     * Reenvia l'email de confirmació (amb QR) d'un inscrit confirmat.
     */
    public function resendConfirmation(Request $req, array $params): void
    {
        $user = Auth::user();
        $id   = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();

        $inscrito = Inscrito::findById($id);
        if ($inscrito === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, (int) $inscrito['evento_id'])) Response::forbidden();

        $back = base_url('/admin/inscritos?' . http_build_query(['evento_id' => (int) $inscrito['evento_id']]));

        // Correu alternatiu opcional (p. ex. si el de l'inscrit era erroni)
        $emailTo = trim((string) $req->post('email_to', ''));
        if ($emailTo !== '' && !filter_var($emailTo, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'El correu indicat no és vàlid.');
            Response::redirect($back);
        }
        $recipient = $emailTo !== '' ? $emailTo : (string) $inscrito['email'];
        if ($recipient === '') {
            Session::flash('error', 'Aquest inscrit no té email. Indica un correu per reenviar-ho.');
            Response::redirect($back);
        }

        try {
            $evento = Evento::findById((int) $inscrito['evento_id']);
            $tarifa = Tarifa::findById((int) $inscrito['tarifa_id']);
            if ($evento === null || $tarifa === null) {
                Session::flash('error', 'No s\'ha pogut carregar l\'esdeveniment o la tarifa.');
                Response::redirect($back);
            }

            $pago = Database::getInstance()->query(
                'SELECT * FROM pagos WHERE inscrito_id = ? ORDER BY id DESC LIMIT 1',
                [$id]
            )->fetch() ?: [];

            Inscrito::ensureQrToken($id);
            $inscrito = Inscrito::findById($id); // recarregar amb qr_token

            EmailService::sendConfirmacionInscripcion($inscrito, $evento, $tarifa, $pago, $emailTo !== '' ? $emailTo : null);
            Session::flash('success', 'Email de confirmació reenviat a ' . $recipient . '.');
        } catch (\Throwable $e) {
            error_log('[InscritosAdmin] Reenviament fallit: ' . $e->getMessage());
            Session::flash('error', 'No s\'ha pogut enviar l\'email: ' . $e->getMessage());
        }

        Response::redirect($back);
    }

    /**
     * Assegura el qr_token i redirigeix al comprovant imprimible públic.
     */
    public function comprovant(Request $req, array $params): void
    {
        $user = Auth::user();
        $id   = (int) ($params['id'] ?? 0);

        $inscrito = Inscrito::findById($id);
        if ($inscrito === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, (int) $inscrito['evento_id'])) Response::forbidden();

        $token = Inscrito::ensureQrToken($id);
        Response::redirect(base_url('/comprovant/' . $token));
    }

    /**
     * Genera i mostra el PNG del QR de check-in d'un inscrit (inline, descarregable).
     */
    public function qr(Request $req, array $params): void
    {
        $user = Auth::user();
        $id   = (int) ($params['id'] ?? 0);

        $inscrito = Inscrito::findById($id);
        if ($inscrito === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, (int) $inscrito['evento_id'])) Response::forbidden();

        $token = Inscrito::ensureQrToken($id);
        $png   = QrService::pngBytes(base_url('/admin/checkin/' . $token), 360);

        header('Content-Type: image/png');
        header('Content-Disposition: inline; filename="qr_inscrit_' . $id . '.png"');
        header('Cache-Control: private, no-store');
        echo $png;
        exit;
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

        // Camps personalitzats (visibles + ocults) com a columnes extra del CSV.
        // Els ocults serveixen per omplir info a mà i tornar-la a importar.
        $campos = CampoPersonalizado::listByEvento((int) $filters['evento_id']);
        $valores = CampoPersonalizado::valoresPorInscrito(array_map(fn($i) => (int) $i['id'], $inscritos));

        $filename = 'inscrits_' . preg_replace('/[^a-z0-9_-]+/i', '_', (string) $evento['slug'])
                  . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store, no-cache');

        $out = fopen('php://output', 'w');
        // BOM UTF-8 perquè Excel detecti l'encoding
        fwrite($out, "\xEF\xBB\xBF");

        // Capçaleres (fixes + una per cada camp personalitzat)
        $headers = [
            'ID', 'Comanda', 'Data inscripció', 'Nom', 'Cognoms', 'DNI', 'Sexe', 'Data naixement',
            'Email', 'Telèfon', 'Club', 'Població', 'Codi postal', 'Talla',
            'Tarifa', 'Preu', 'Estat', 'Check-in', 'Dorsal'
        ];
        foreach ($campos as $c) {
            $headers[] = (string) $c['etiqueta'] . (!empty($c['oculto']) ? ' (ocult)' : '');
        }
        fputcsv($out, $headers, ';');

        foreach ($inscritos as $i) {
            $row = [
                $i['id'],
                !empty($i['pedido_id']) ? '#' . (int) $i['pedido_id'] : '',
                format_datetime_local($i['created_at'], 'd/m/Y H:i'),
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
                !empty($i['check_in_at']) ? format_datetime_local($i['check_in_at'], 'd/m/Y H:i') : '',
                $i['numero_dorsal'] ?? '',
            ];
            $vals = $valores[(int) $i['id']] ?? [];
            foreach ($campos as $c) {
                $row[] = $vals[(int) $c['id']] ?? '';
            }
            fputcsv($out, $row, ';');
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
