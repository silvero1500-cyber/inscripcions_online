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
use App\Models\AuditLog;
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
        $recollits = 0;
        $totalPages = 1;

        if ($filters['evento_id']) {
            $total = Inscrito::countForAdmin($filtersClean);
            $totalPages = max(1, (int) ceil($total / $perPage));
            if ($page > $totalPages) $page = $totalPages;
            $inscritos = Inscrito::listForAdmin($filtersClean, $page, $perPage);
            $counts = Inscrito::countsByEstadoForAdmin($filtersClean);
            $recollits = Inscrito::countRecollitsForAdmin($filtersClean);
        }

        $from = $total > 0 ? ($page - 1) * $perPage + 1 : 0;
        $to = min($total, $page * $perPage);

        // Franges de l'event seleccionat (per a l'edició inline del grid)
        $franjas = [];
        $ev = null;
        if ($filters['evento_id']) {
            $ev = Evento::findById((int) $filters['evento_id']);
            if ($ev) $franjas = array_map(fn($f) => $f['label'], Evento::franjasConfig($ev));
        }

        View::render('admin/inscritos/index', [
            'user'       => $user,
            'eventos'    => $eventos,
            'inscritos'  => $inscritos,
            'filters'    => $filters,
            'counts'     => $counts,
            'recollits'  => $recollits,
            'franjas'    => $franjas,
            'eventoSel'  => $ev,
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
     * Marca/desmarca la inscripció com a "early bird" (manual). Torna a la fitxa.
     */
    public function toggleEarlyBird(Request $req, array $params): void
    {
        $user = Auth::user();
        $id   = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();

        $inscrito = Inscrito::findById($id);
        if ($inscrito === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, (int) $inscrito['evento_id'])) Response::forbidden();

        $val = ($req->post('early_bird') === '1') ? 1 : 0;
        Database::getInstance()->update('inscritos', ['early_bird' => $val], ['id' => $id]);
        Session::flash('success', $val ? 'Marcat com a Early Bird.' : 'Early Bird desmarcat.');

        Response::redirect(base_url('/admin/inscritos/' . $id));
    }

    /**
     * Edició inline d'un inscrit des del grid. Valida i desa els camps de dades
     * personals + inscripció. Respon JSON (èxit amb valors formatats / errors).
     */
    public function updateInline(Request $req, array $params): void
    {
        $user = Auth::user();
        $id   = (int) ($params['id'] ?? 0);

        if (!in_array($user->rol, ['superadmin', 'organizador'], true)) {
            Response::json(['ok' => false, 'message' => 'Sense permís.'], 403);
        }
        if (!Csrf::verify($req->post('_csrf'))) {
            Response::json(['ok' => false, 'message' => 'Sessió expirada.'], 419);
        }

        $inscrito = Inscrito::findById($id);
        if ($inscrito === null) Response::json(['ok' => false, 'message' => 'Inscrit no trobat.'], 404);
        if (!Evento::userCanEdit($user->id, $user->rol, (int) $inscrito['evento_id'])) {
            Response::json(['ok' => false, 'message' => 'Sense permís.'], 403);
        }

        $eventoId = (int) $inscrito['evento_id'];
        $evento   = Evento::findById($eventoId);
        $errors   = [];

        // ── Sanejar entrada ──
        $nombre    = trim((string) $req->post('nombre'));
        $apellido  = trim((string) $req->post('apellido'));
        $dni       = strtoupper(trim((string) $req->post('dni')));
        $sexo      = strtoupper(trim((string) $req->post('sexo')));
        $fnac      = trim((string) $req->post('fecha_nacimiento'));
        $email     = strtolower(trim((string) $req->post('email')));
        $telefono  = preg_replace('/\s+/', '', trim((string) $req->post('telefono'))) ?? '';
        $club      = trim((string) $req->post('club'));
        $poblacion = mb_substr(trim((string) $req->post('poblacion')), 0, 120);
        $cp        = preg_replace('/\D+/', '', trim((string) $req->post('codigo_postal'))) ?? '';
        $talla     = trim((string) $req->post('talla_camiseta'));
        $franja    = mb_substr(trim((string) $req->post('franja_temps')), 0, 60);
        $dorsalRaw = trim((string) $req->post('numero_dorsal'));

        // ── Validar ──
        if ($nombre === '') $errors['nombre'] = 'El nom és obligatori.';
        if (mb_strlen($nombre) > 100) $errors['nombre'] = 'Nom massa llarg.';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email no vàlid.';
        if ($dni !== '' && !Inscrito::dniValido($dni)) $errors['dni'] = 'DNI o NIE no vàlid.';
        if ($sexo !== '' && !in_array($sexo, Inscrito::SEXOS, true)) $errors['sexo'] = 'Sexe no vàlid.';
        if ($talla !== '' && !in_array($talla, Inscrito::TALLAS, true)) $errors['talla_camiseta'] = 'Talla no vàlida.';
        if ($telefono !== '' && !preg_match('/^\+?\d{9,15}$/', $telefono)) $errors['telefono'] = 'Telèfon no vàlid.';
        if ($cp !== '' && !preg_match('/^\d{4,5}$/', $cp)) $errors['codigo_postal'] = 'CP no vàlid.';
        if ($fnac !== '') {
            $ts = strtotime($fnac);
            if ($ts === false || $ts > time() || $ts < strtotime('-110 years')) $errors['fecha_nacimiento'] = 'Data no vàlida.';
        }
        if ($franja !== '' && $evento !== null) {
            $labels = array_map(fn($f) => $f['label'], Evento::franjasConfig($evento));
            if ($labels && !in_array($franja, $labels, true)) $errors['franja_temps'] = 'Franja no vàlida.';
        }
        $dorsal = null;
        if ($dorsalRaw !== '') {
            if (!ctype_digit($dorsalRaw) || (int) $dorsalRaw <= 0) {
                $errors['numero_dorsal'] = 'Dorsal no vàlid.';
            } else {
                $dorsal = (int) $dorsalRaw;
                if (Inscrito::dorsalEnUs($eventoId, $dorsal, $id)) {
                    $errors['numero_dorsal'] = 'El dorsal ' . $dorsal . ' ja l\'usa un altre inscrit.';
                }
            }
        }

        if ($errors) {
            Response::json(['ok' => false, 'errors' => $errors], 422);
        }

        // ── Desar ──
        $data = [
            'nombre'           => $nombre,
            'apellido'         => $apellido !== '' ? $apellido : null,
            'dni'              => $dni !== '' ? $dni : null,
            'sexo'             => $sexo !== '' ? $sexo : null,
            'fecha_nacimiento' => $fnac !== '' ? $fnac : null,
            'email'            => $email,
            'telefono'         => $telefono !== '' ? $telefono : null,
            'club'             => $club !== '' ? $club : null,
            'poblacion'        => $poblacion !== '' ? $poblacion : null,
            'codigo_postal'    => $cp !== '' ? mb_substr($cp, 0, 10) : null,
            'talla_camiseta'   => $talla !== '' ? $talla : null,
            'franja_temps'     => $franja !== '' ? $franja : null,
            'numero_dorsal'    => $dorsal,
        ];
        try {
            Database::getInstance()->update('inscritos', $data, ['id' => $id]);
        } catch (\Throwable $e) {
            error_log('[InscritosAdmin] updateInline: ' . $e->getMessage());
            Response::json(['ok' => false, 'message' => 'No s\'ha pogut desar.'], 500);
        }

        AuditLog::registrar('inscrit_editat', 'Edició inline inscrit #' . $id . ' (' . $nombre . ')');

        // Valors formatats per repintar les cel·les del grid
        Response::json(['ok' => true, 'inscrito' => [
            'nombre'        => $nombre,
            'apellido'      => (string) ($data['apellido'] ?? ''),
            'dni'           => (string) ($data['dni'] ?? ''),
            'sexo'          => (string) ($data['sexo'] ?? ''),
            'fnac'          => $fnac !== '' ? date('d/m/Y', strtotime($fnac)) : '—',
            'fnac_raw'      => (string) ($data['fecha_nacimiento'] ?? ''),
            'email'         => $email,
            'telefono'      => (string) ($data['telefono'] ?? ''),
            'club'          => (string) ($data['club'] ?? '—'),
            'poblacion'     => (string) ($data['poblacion'] ?? '—'),
            'cp'            => (string) ($data['codigo_postal'] ?? '—'),
            'talla'         => (string) ($data['talla_camiseta'] ?? '—'),
            'franja'        => (string) ($data['franja_temps'] ?? '—'),
            'dorsal'        => $dorsal !== null ? (string) $dorsal : '—',
        ]]);
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
        AuditLog::registrar(AuditLog::INSCRITS_EXPORT, 'Evento «' . ($evento['titulo'] ?? '') . '» #' . (int) $filters['evento_id']);
        $inscritos = Inscrito::listForAdminExport(array_filter($filters));

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
            'Email', 'Telèfon', 'Club', 'Població', 'Codi postal', 'Talla', 'Franja temps',
            'Xip groc', 'Núm xip groc',
            'Tutor nom', 'Tutor cognoms', 'Tutor DNI',
            'Tarifa', 'Preu', 'Estat', 'Dorsal'
        ];
        foreach ($campos as $c) {
            $headers[] = (string) $c['etiqueta'] . (!empty($c['oculto']) ? ' (ocult)' : '');
        }
        fputcsv($out, $headers, ';', '"', '\\'); // escape explícit (deprecat a PHP 8.4+)

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
                // Forçar text perquè Excel no elimini el 0 inicial del codi postal (08201 → 8201)
                ($i['codigo_postal'] ?? '') !== '' ? '="' . $i['codigo_postal'] . '"' : '',
                $i['talla_camiseta'] ?? '',
                $i['franja_temps'] ?? '',
                !empty($i['chip_groc']) ? ($i['chip_groc'] === 'si' ? 'Sí' : 'No') : '',
                $i['chip_groc_num'] ?? '',
                $i['tutor_nombre'] ?? '',
                $i['tutor_apellido'] ?? '',
                $i['tutor_dni'] ?? '',
                $i['tarifa_nombre'],
                number_format((float) $i['tarifa_precio'], 2, ',', '.'),
                $i['estado'],
                $i['numero_dorsal'] ?? '',
            ];
            $vals = $valores[(int) $i['id']] ?? [];
            foreach ($campos as $c) {
                $row[] = $vals[(int) $c['id']] ?? '';
            }
            fputcsv($out, $row, ';', '"', '\\');
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
