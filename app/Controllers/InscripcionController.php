<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\CampoPersonalizado;
use App\Models\CamposFijos;
use App\Models\DescuentoEvento;
use App\Models\Evento;
use App\Models\GrupoAforo;
use App\Models\Inscrito;
use App\Models\Pedido;
use App\Models\RateLimit;
use App\Models\Tarifa;
use App\Services\EmailService;
use App\Services\QrService;

final class InscripcionController
{
    /**
     * Valida un codi de descompte sense inscriure (AJAX, botó "Aplicar").
     * Retorna JSON {valid, porcentaje, message}.
     */
    public function validarCupo(Request $req, array $params): void
    {
        $slug   = (string) ($params['slug'] ?? '');
        $evento = Evento::findBySlug($slug);
        if ($evento === null) {
            Response::json(['valid' => false, 'message' => 'Esdeveniment no trobat.'], 404);
        }
        if (!Csrf::verify($req->post('_csrf'))) {
            Response::json(['valid' => false, 'message' => 'Sessió expirada. Recarrega la pàgina.'], 419);
        }
        $codigo = strtoupper(trim((string) $req->post('codigo', '')));
        if ($codigo === '') {
            Response::json(['valid' => false, 'message' => 'Introdueix un codi.']);
        }
        $desc = DescuentoEvento::findByCodigo((int) $evento['id'], $codigo);
        if ($desc === null) {
            Response::json(['valid' => false, 'message' => 'El codi no és vàlid per a aquest esdeveniment.']);
        }
        $check = DescuentoEvento::checkUsable($desc);
        if (!$check['valid']) {
            Response::json(['valid' => false, 'message' => $check['error'] ?? 'El codi no es pot fer servir.']);
        }
        Response::json([
            'valid'      => true,
            'porcentaje' => (float) $desc['porcentaje'],
            'message'    => 'Descompte del ' . rtrim(rtrim(number_format((float) $desc['porcentaje'], 2, ',', '.'), '0'), ',') . '% aplicat.',
        ]);
    }

    public function store(Request $req, array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        $evento = Evento::findBySlug($slug);

        if ($evento === null || (int) $evento['activo'] !== 1 || !empty($evento['archivado_at'])) {
            Response::notFound();
        }

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'La sessió ha expirat. Torna a omplir el formulari.');
            Response::redirect(base_url('/eventos/' . $slug));
        }

        // Honeypot anti-bot: si el camp ocult ve omplert, és spam → descartar en silenci
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            error_log('[Inscripcio] Honeypot activat (spam) IP=' . $req->ip);
            Response::redirect(base_url('/'));
        }

        // Caduca pendents abandonats: allibera places abans de comprovar l'aforament.
        // (Cobreix també la inscripció de grup, que es despatxa més avall.)
        Inscrito::expirarPendientes((int) $evento['id']);

        if (!PublicController::inscripcionesAbiertas($evento)) {
            Session::flash('error', 'Les inscripcions per a aquest esdeveniment estan tancades.');
            Response::redirect(base_url('/eventos/' . $slug));
        }

        // Inscripció de grup (diversos participants en una sola compra)
        if ($req->post('grupo') === '1' && (int) ($evento['max_participantes'] ?? 1) >= 2) {
            $this->storeGrupo($req, $evento, $slug);
            return; // storeGrupo redirigeix sempre
        }

        $eventoId = (int) $evento['id'];

        // ── Pre-check tarifa (sin bloqueo, respuesta rápida) ──
        $tarifaId  = (int) ($_POST['tarifa_id'] ?? 0);
        $tarifaRow = $tarifaId > 0 ? Tarifa::findDisponibleForEvento($tarifaId, $eventoId) : null;
        if ($tarifaRow === null) {
            Session::flash('error', 'Tria una tarifa vàlida.');
            Response::redirect(base_url('/eventos/' . $slug) . '#formulari');
        }

        // ── Validación campos estándar (segons config de l'esdeveniment) ──
        $camposFijos = CamposFijos::resolve($evento['campos_fijos'] ?? null);
        $data = self::extractCorredorData($_POST, $camposFijos, $tarifaRow);
        $v    = self::validateCorredor($data, $camposFijos, $tarifaRow, $evento);

        // El correu electrònic s'ha de repetir igual (confirmació)
        $emailConfirm = strtolower(trim((string) ($_POST['email_confirm'] ?? '')));
        if ($v->first('email') === null && $emailConfirm !== (string) ($data['email'] ?? '')) {
            $v->addError('email_confirm', t('form.email_mismatch'));
        }

        // Restricció d'any de naixement de la tarifa triada (infantil, veterans, …)
        self::validateAnioNacimiento($v, $tarifaRow, (string) ($data['fecha_nacimiento'] ?? ''));
        // Talla disponible per al sexe
        self::validateTallaSexo($v, $evento['tallas_sexo'] ?? null, (string) ($data['sexo'] ?? ''), (string) ($data['talla_camiseta'] ?? ''));

        // ── Validación campos personalizados ──────────────────
        $valoresCampos = [];
        foreach (CampoPersonalizado::getActivosPorEvento($eventoId) as $c) {
            // Camp condicional: si està limitat a unes tarifes i no és la triada, s'ignora
            $ct = CampoPersonalizado::tarifasDeCampo($c);
            if (!empty($ct) && !in_array($tarifaId, $ct, true)) continue;
            $key = 'campo_' . (int) $c['id'];
            $raw = $_POST[$key] ?? null;

            if ((int) $c['requerido'] === 1) {
                $vacio = ($raw === null) || (is_string($raw) && trim($raw) === '') || (is_array($raw) && count($raw) === 0);
                if ($vacio) {
                    $v->addError($key, 'El camp "' . $c['etiqueta'] . '" és obligatori.');
                    continue;
                }
            }

            if ($raw === null || $raw === '') continue;

            if (is_array($raw)) {
                $valoresCampos[(int) $c['id']] = implode(', ', array_map('strval', $raw));
            } else {
                $valoresCampos[(int) $c['id']] = mb_substr((string) $raw, 0, 1000);
            }
        }

        // Consentiment RGPD obligatori
        if (empty($_POST['acepta_privacidad'])) {
            $v->addError('acepta_privacidad', t('form.privacy.required'));
        }

        if ($v->fails()) {
            $post = $_POST;
            unset($post['_csrf']);
            Session::flash('insc_old', (string) json_encode($post, JSON_UNESCAPED_UNICODE));
            Session::flash('insc_errors', (string) json_encode($v->errors(), JSON_UNESCAPED_UNICODE));
            Response::redirect(base_url('/eventos/' . $slug) . '#formulari');
        }

        // ── Codi de descompte (opcional) ─────────────────────
        $descuentoCodigo = strtoupper(trim((string) $req->post('descuento_codigo', '')));

        // ── Sección crítica: bloqueo de fila + comprobaciones + inserción ──
        $datosCorredor = array_merge($data, [
            'evento_id'   => $eventoId,
            'tarifa_id'   => $tarifaId,
            'estado'      => 'pendiente',
            'ip_registro' => $req->ip,
            'locale'      => current_locale(),
        ]);

        $inscritoId = 0;
        try {
            $inscritoId = (int) Database::getInstance()->transaction(
                function () use ($eventoId, $tarifaId, $datosCorredor, $valoresCampos, $descuentoCodigo): int {
                    // SELECT FOR UPDATE: bloquea la fila hasta el commit,
                    // impidiendo que otra petición concurrente supere el aforo.
                    $tarifa = Tarifa::findAndLockForInscripcion($tarifaId, $eventoId);
                    if ($tarifa === null) {
                        throw new \DomainException('tarifa_invalida');
                    }
                    // Si la tarifa pertany a un grup d'aforament, compta contra el cupo
                    // del grup (bloqueja la fila del grup); si no, contra el seu aforo propi.
                    $capacidadOk = !empty($tarifa['grupo_aforo_id'])
                        ? GrupoAforo::tieneCapacidad((int) $tarifa['grupo_aforo_id'])
                        : Tarifa::tieneCapacidad($tarifa);
                    if (!$capacidadOk) {
                        throw new \DomainException('tarifa_esgotada');
                    }

                    // Codi descompte (si s'ha indicat)
                    $datos = $datosCorredor;
                    if ($descuentoCodigo !== '') {
                        $desc = DescuentoEvento::findAndLockForUse((int) $datosCorredor['evento_id'], $descuentoCodigo);
                        if ($desc === null) {
                            throw new \DomainException('descuento_invalid');
                        }
                        $check = DescuentoEvento::checkUsable($desc);
                        if (!$check['valid']) {
                            throw new \DomainException('descuento_' . str_replace([' ', '\''], ['_', ''], $check['error']));
                        }
                        $datos['descuento_id']         = (int) $desc['id'];
                        $datos['descuento_codigo']    = $desc['codigo'];
                        $datos['descuento_porcentaje'] = $desc['porcentaje'];
                        DescuentoEvento::incrementarUsos((int) $desc['id']);
                    }
                    // Bloqueja el preu vigent (trams de data) en aquest moment
                    $datos['precio_aplicado'] = Tarifa::precioVigente($tarifa);
                    return Inscrito::createWithCustomFields($datos, $valoresCampos);
                }
            );
        } catch (\DomainException $e) {
            $code = $e->getMessage();
            $msg = match (true) {
                $code === 'tarifa_esgotada'                     => 'La tarifa triada està esgotada.',
                $code === 'descuento_invalid'                   => 'El codi de descompte no és vàlid per a aquest esdeveniment.',
                str_starts_with($code, 'descuento_')            => 'Codi de descompte: ' . str_replace(['descuento_', '_'], ['', ' '], $code),
                default                                          => 'Tria una tarifa vàlida.',
            };
            Session::flash('error', $msg);
            Response::redirect(base_url('/eventos/' . $slug) . '#formulari');
        }

        Session::forget('ultimo_pedido');
        Session::set('ultima_inscripcion', [
            'id'         => $inscritoId,
            'evento_id'  => $eventoId,
            'tarifa_id'  => $tarifaId,
            'slug'       => $slug,
            'pay_method' => null,
        ]);

        // Si el preu final (amb descompte) és 0, no hi ha pagament: confirmar directe
        $inscritoCreat = Inscrito::findById($inscritoId);
        $tarifaInfo = Tarifa::findById($tarifaId);
        $precio = $inscritoCreat['precio_aplicado'] !== null
            ? (float) $inscritoCreat['precio_aplicado']
            : (float) ($tarifaInfo['precio'] ?? 0);
        if (!empty($inscritoCreat['descuento_porcentaje'])) {
            $precio = round($precio * (1 - (float)$inscritoCreat['descuento_porcentaje'] / 100), 2);
        }
        if ($precio <= 0.01) {
            Inscrito::marcarConfirmado($inscritoId);
            self::enviarConfirmacionGratuita($inscritoId);
            Response::redirect(base_url('/eventos/' . $slug . '/gracies'));
        }

        Response::redirect(base_url('/pago/metodo'));
    }

    /**
     * Envia l'email de confirmació amb el QR de check-in per a inscripcions
     * gratuïtes (preu 0), que es confirmen sense passar per la passarel·la de pagament.
     * Si l'enviament falla, no bloqueja la confirmació (ja feta).
     */
    private static function enviarConfirmacionGratuita(int $inscritoId): void
    {
        try {
            $inscrito = Inscrito::findById($inscritoId);
            if ($inscrito === null || empty($inscrito['email'])) return;

            $evento = Evento::findById((int) $inscrito['evento_id']);
            $tarifa = Tarifa::findById((int) $inscrito['tarifa_id']);
            if ($evento === null || $tarifa === null) return;

            Inscrito::ensureQrToken($inscritoId);
            $inscrito = Inscrito::findById($inscritoId); // recarregar amb qr_token

            EmailService::sendConfirmacionInscripcion($inscrito, $evento, $tarifa, []);
        } catch (\Throwable $e) {
            error_log('[InscripcionController] Email confirmació gratuïta fallit: ' . $e->getMessage());
        }
    }

    /**
     * Alta d'una inscripció de GRUP: un contacte + N participants, un sol pagament.
     * Crea un `pedido` i N `inscritos` (tot o res respecte de l'aforament).
     */
    private function storeGrupo(Request $req, array $evento, string $slug): void
    {
        $eventoId = (int) $evento['id'];
        $maxPart  = max(1, (int) ($evento['max_participantes'] ?? 1));

        // Honeypot ja comprovat a store(). Contacte + participants del POST.
        $contacto = is_array($_POST['contacto'] ?? null) ? $_POST['contacto'] : [];
        $participantsRaw = is_array($_POST['participants'] ?? null)
            ? array_values(array_filter($_POST['participants'], 'is_array'))
            : [];

        $errors = [];

        $contactEmail        = strtolower(trim((string) ($contacto['email'] ?? '')));
        $contactEmailConfirm = strtolower(trim((string) ($contacto['email_confirm'] ?? '')));
        $contactTel          = preg_replace('/\s+/', '', trim((string) ($contacto['telefono'] ?? ''))) ?? '';

        if ($contactEmail === '' || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['contacto.email'] = ['Correu electrònic no vàlid.'];
        } elseif ($contactEmailConfirm !== $contactEmail) {
            $errors['contacto.email_confirm'] = [t('form.email_mismatch')];
        }
        if (count($participantsRaw) < 1) {
            $errors['contacto.email'] = [t('group.min_one')];
        } elseif (count($participantsRaw) > $maxPart) {
            $errors['contacto.email'] = [t('group.too_many')];
        }

        $camposFijos = CamposFijos::resolve($evento['campos_fijos'] ?? null);

        // Telèfon: és del contacte (no de cada participant)
        if (CamposFijos::visible($camposFijos, 'telefono')) {
            if (CamposFijos::requerido($camposFijos, 'telefono') && $contactTel === '') {
                $errors['contacto.telefono'] = ['El telèfon és obligatori.'];
            } elseif ($contactTel !== '' && !preg_match('/^\+?\d{9,15}$/', $contactTel)) {
                $errors['contacto.telefono'] = ['Número de telèfon no vàlid.'];
            }
        }

        $camposPers  = CampoPersonalizado::getActivosPorEvento($eventoId);

        // Validar cada participant i preparar les dades
        $prepared = [];
        foreach ($participantsRaw as $i => $p) {
            $tarifaId  = (int) ($p['tarifa_id'] ?? 0);
            $tarifaRow = $tarifaId > 0 ? Tarifa::findDisponibleForEvento($tarifaId, $eventoId) : null;
            if ($tarifaRow === null) {
                $errors["participants.$i.tarifa_id"] = ['Tria una tarifa vàlida.'];
            }

            // Dades del corredor (l'email/telèfon són del contacte, no del participant)
            $pPost = $p;
            $pPost['email']    = $contactEmail;
            $pPost['telefono'] = $contactTel;
            $data = self::extractCorredorData($pPost, $camposFijos, $tarifaRow);
            $v    = self::validateCorredor($data, $camposFijos, $tarifaRow, $evento);
            if ($tarifaRow !== null) {
                self::validateAnioNacimiento($v, $tarifaRow, (string) ($data['fecha_nacimiento'] ?? ''));
            }
            self::validateTallaSexo($v, $evento['tallas_sexo'] ?? null, (string) ($data['sexo'] ?? ''), (string) ($data['talla_camiseta'] ?? ''));
            foreach ($v->errors() as $field => $msgs) {
                // email/email_confirm/telefono són del contacte, no de cada participant
                if (in_array($field, ['email', 'email_confirm', 'telefono'], true)) continue;
                $errors["participants.$i.$field"] = $msgs;
            }

            // Camps personalitzats d'aquest participant
            $valores = [];
            foreach ($camposPers as $c) {
                $ct = CampoPersonalizado::tarifasDeCampo($c);
                if (!empty($ct) && !in_array($tarifaId, $ct, true)) continue;
                $key = 'campo_' . (int) $c['id'];
                $raw = $p[$key] ?? null;
                if ((int) $c['requerido'] === 1) {
                    $vacio = ($raw === null) || (is_string($raw) && trim($raw) === '') || (is_array($raw) && count($raw) === 0);
                    if ($vacio) { $errors["participants.$i.$key"] = ['El camp "' . $c['etiqueta'] . '" és obligatori.']; continue; }
                }
                if ($raw === null || $raw === '') continue;
                $valores[(int) $c['id']] = is_array($raw)
                    ? implode(', ', array_map('strval', $raw))
                    : mb_substr((string) $raw, 0, 1000);
            }

            $prepared[] = [
                'data'      => $data,
                'tarifaId'  => $tarifaId,
                'valores'   => $valores,
                'descuento' => strtoupper(trim((string) ($p['descuento_codigo'] ?? ''))),
            ];
        }

        // Consentiment RGPD obligatori
        if (empty($_POST['acepta_privacidad'])) {
            $errors['acepta_privacidad'] = [t('form.privacy.required')];
        }

        if (!empty($errors)) {
            $post = $_POST; unset($post['_csrf']);
            Session::flash('insc_old', (string) json_encode($post, JSON_UNESCAPED_UNICODE));
            Session::flash('insc_errors', (string) json_encode($errors, JSON_UNESCAPED_UNICODE));
            Response::redirect(base_url('/eventos/' . $slug) . '#formulari');
        }

        // ── Secció crítica: pedido + N inscritos en una transacció (tot o res) ──
        try {
            $result = Database::getInstance()->transaction(
                function () use ($eventoId, $contactEmail, $contactTel, $prepared, $req): array {
                    $pedidoId = Pedido::crear([
                        'token'         => Pedido::generarToken(),
                        'evento_id'     => $eventoId,
                        'email'         => $contactEmail,
                        'telefono'      => $contactTel !== '' ? $contactTel : null,
                        'importe_total' => 0,
                        'estado'        => 'pendiente',
                        'locale'        => current_locale(),
                        'ip_registro'   => $req->ip,
                    ]);

                    $total = 0.0;
                    $inscritoIds = [];
                    foreach ($prepared as $p) {
                        // Bloqueja la tarifa i comprova capacitat (les insercions prèvies
                        // d'aquesta mateixa transacció ja compten → "tot o res").
                        $tarifa = Tarifa::findAndLockForInscripcion($p['tarifaId'], $eventoId);
                        if ($tarifa === null) throw new \DomainException('tarifa_invalida');
                        $capacidadOk = !empty($tarifa['grupo_aforo_id'])
                            ? GrupoAforo::tieneCapacidad((int) $tarifa['grupo_aforo_id'])
                            : Tarifa::tieneCapacidad($tarifa);
                        if (!$capacidadOk) throw new \DomainException('tarifa_esgotada');

                        $precio = Tarifa::precioVigente($tarifa); // preu vigent (trams de data)
                        $datos = array_merge($p['data'], [
                            'evento_id'      => $eventoId,
                            'tarifa_id'      => $p['tarifaId'],
                            'pedido_id'      => $pedidoId,
                            'estado'         => 'pendiente',
                            'ip_registro'    => $req->ip,
                            'locale'         => current_locale(),
                            'email'          => $contactEmail,
                            'telefono'       => $contactTel !== '' ? $contactTel : null,
                            'precio_aplicado' => $precio, // bloquejat (abans de descompte)
                        ]);

                        // Cupó per participant (un codi descompta NOMÉS aquest participant)
                        if ($p['descuento'] !== '') {
                            $desc = DescuentoEvento::findAndLockForUse($eventoId, $p['descuento']);
                            if ($desc === null) throw new \DomainException('descuento_invalid');
                            $check = DescuentoEvento::checkUsable($desc);
                            if (!$check['valid']) throw new \DomainException('descuento_' . str_replace([' ', '\''], ['_', ''], $check['error']));
                            $datos['descuento_id']         = (int) $desc['id'];
                            $datos['descuento_codigo']     = $desc['codigo'];
                            $datos['descuento_porcentaje'] = $desc['porcentaje'];
                            DescuentoEvento::incrementarUsos((int) $desc['id']);
                            $precio = round($precio * (1 - (float) $desc['porcentaje'] / 100), 2);
                        }

                        $inscritoIds[] = Inscrito::createWithCustomFields($datos, $p['valores']);
                        $total += $precio;
                    }

                    Pedido::actualizarTotal($pedidoId, $total);
                    return ['pedido_id' => $pedidoId, 'inscrito_ids' => $inscritoIds, 'total' => $total];
                }
            );
        } catch (\DomainException $e) {
            $code = $e->getMessage();
            $msg = match (true) {
                $code === 'tarifa_esgotada'          => 'Alguna tarifa triada està esgotada per al nombre de participants.',
                $code === 'descuento_invalid'        => 'Un codi de descompte no és vàlid per a aquest esdeveniment.',
                str_starts_with($code, 'descuento_') => 'Codi de descompte: ' . str_replace(['descuento_', '_'], ['', ' '], $code),
                default                              => 'Tria una tarifa vàlida.',
            };
            Session::flash('error', $msg);
            $post = $_POST; unset($post['_csrf']);
            Session::flash('insc_old', (string) json_encode($post, JSON_UNESCAPED_UNICODE));
            Response::redirect(base_url('/eventos/' . $slug) . '#formulari');
        }

        $pedidoId = (int) $result['pedido_id'];
        $total    = (float) $result['total'];

        Session::forget('ultima_inscripcion');
        Session::set('ultimo_pedido', ['pedido_id' => $pedidoId, 'slug' => $slug, 'pay_method' => null]);

        // Gratuït (total 0): confirmar tot el pedido + email amb tots els QR
        if ($total <= 0.01) {
            Pedido::marcarConfirmado($pedidoId);
            foreach ($result['inscrito_ids'] as $iid) Inscrito::marcarConfirmado((int) $iid);
            self::enviarConfirmacionPedido($pedidoId);
            Response::redirect(base_url('/eventos/' . $slug . '/gracies'));
        }

        // De pagament: passa a la passarel·la (gestió de grup → Fase pagament)
        Response::redirect(base_url('/pago/metodo'));
    }

    /**
     * Envia un sol email al contacte del pedido amb el QR de cada participant.
     * No bloqueja si falla (la confirmació ja està feta).
     */
    public static function enviarConfirmacionPedido(int $pedidoId): void
    {
        try {
            $pedido = Pedido::findById($pedidoId);
            if ($pedido === null || empty($pedido['email'])) return;

            $evento    = Evento::findById((int) $pedido['evento_id']);
            $inscritos = Pedido::inscritos($pedidoId);
            if ($evento === null || count($inscritos) === 0) return;

            // Assegurar qr_token + carregar tarifa de cada participant
            $items = [];
            foreach ($inscritos as $ins) {
                Inscrito::ensureQrToken((int) $ins['id']);
                $ins = Inscrito::findById((int) $ins['id']);
                $items[] = [
                    'inscrito' => $ins,
                    'tarifa'   => Tarifa::findById((int) $ins['tarifa_id']),
                ];
            }

            EmailService::sendConfirmacionPedido($pedido, $evento, $items);
        } catch (\Throwable $e) {
            error_log('[InscripcionController] Email confirmació pedido fallit: ' . $e->getMessage());
        }
    }

    public function exito(Request $req, array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        $evento = Evento::findBySlug($slug);
        if ($evento === null) Response::notFound();

        // Grup: si l'última acció va ser un pedido d'aquest esdeveniment
        $ped = Session::get('ultimo_pedido');
        if (is_array($ped) && (string) ($ped['slug'] ?? '') === $slug) {
            $pedido = Pedido::findById((int) ($ped['pedido_id'] ?? 0));
            if ($pedido !== null && (int) $pedido['evento_id'] === (int) $evento['id']) {
                $items = [];
                foreach (Pedido::inscritos((int) $pedido['id']) as $ins) {
                    $qrDataUri = null;
                    if (($ins['estado'] ?? '') === 'confirmado') {
                        try {
                            $tk = Inscrito::ensureQrToken((int) $ins['id']);
                            $ins['qr_token'] = $tk;
                            $png = QrService::pngBytes(base_url('/admin/checkin/' . $tk), 280);
                            $qrDataUri = 'data:image/png;base64,' . base64_encode($png);
                        } catch (\Throwable $e) {
                            error_log('[InscripcionController] QR gràcies pedido fallit: ' . $e->getMessage());
                        }
                    }
                    $items[] = [
                        'inscrito'  => $ins,
                        'tarifa'    => Tarifa::findById((int) $ins['tarifa_id']),
                        'qrDataUri' => $qrDataUri,
                    ];
                }
                View::render('public/inscripcion/exito_pedido', [
                    'evento' => $evento,
                    'pedido' => $pedido,
                    'items'  => $items,
                ], layout: 'public');
                return;
            }
        }

        $ult = Session::get('ultima_inscripcion');
        if (!is_array($ult) || (string) ($ult['slug'] ?? '') !== $slug) {
            Response::redirect(base_url('/eventos/' . $slug));
        }

        $inscrito = Inscrito::findById((int) $ult['id']);
        $tarifa   = Tarifa::findById((int) $ult['tarifa_id']);

        // QR de check-in: només si la inscripció ja està confirmada (gratuïtes / ja pagades)
        $qrDataUri = null;
        $qrToken   = null;
        if ($inscrito !== null && ($inscrito['estado'] ?? '') === 'confirmado') {
            try {
                $qrToken = Inscrito::ensureQrToken((int) $inscrito['id']);
                $png     = QrService::pngBytes(base_url('/admin/checkin/' . $qrToken), 320);
                $qrDataUri = 'data:image/png;base64,' . base64_encode($png);
            } catch (\Throwable $e) {
                error_log('[InscripcionController] QR gràcies fallit: ' . $e->getMessage());
            }
        }

        View::render('public/inscripcion/exito', [
            'evento'    => $evento,
            'inscrito'  => $inscrito,
            'tarifa'    => $tarifa,
            'qrDataUri' => $qrDataUri,
            'qrToken'   => $qrToken,
        ], layout: 'public');
    }

    /**
     * Comprovant d'inscripció imprimible (públic, accés pel qr_token secret).
     * Pàgina autònoma optimitzada per imprimir / desar com a PDF des del navegador.
     */
    public function comprovant(Request $req, array $params): void
    {
        $token    = (string) ($params['token'] ?? '');
        $inscrito = Inscrito::findByQrToken($token);
        if ($inscrito === null) Response::notFound();

        $evento = Evento::findById((int) $inscrito['evento_id']);
        $tarifa = Tarifa::findById((int) $inscrito['tarifa_id']);
        if ($evento === null) Response::notFound();

        $qrDataUri = null;
        try {
            $png = QrService::pngBytes(base_url('/admin/checkin/' . $inscrito['qr_token']), 360);
            $qrDataUri = 'data:image/png;base64,' . base64_encode($png);
        } catch (\Throwable $e) {
            error_log('[InscripcionController] QR comprovant fallit: ' . $e->getMessage());
        }

        View::render('public/inscripcion/comprovant', [
            'evento'    => $evento,
            'inscrito'  => $inscrito,
            'tarifa'    => $tarifa,
            'qrDataUri' => $qrDataUri,
        ]);
    }

    /**
     * Pàgina pública per recuperar el comprovant: l'inscrit posa el seu DNI o
     * correu i li reenviem el comprovant per email.
     */
    public function comprovantForm(Request $req): void
    {
        // L'esdeveniment es pren automàticament del link (?e=slug) si ve d'una fitxa de carrera
        $slug   = trim((string) $req->query('e', ''));
        $evento = $slug !== '' ? Evento::findBySlug($slug) : null;

        View::render('public/inscripcion/recuperar', [
            'evento' => $evento,
            'old'    => (string) Session::pullFlash('recover_old'),
            'flash'  => Session::pullAllFlashes(),
        ], layout: 'public');
    }

    public function comprovantSend(Request $req): void
    {
        // Esdeveniment d'origen (si el formulari ve d'una carrera concreta)
        $slug    = trim((string) $req->post('evento_slug', ''));
        $evento  = $slug !== '' ? Evento::findBySlug($slug) : null;
        $backUrl = base_url('/comprovant' . ($slug !== '' ? '?e=' . urlencode($slug) : ''));

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', t('recover.expired'));
            Response::redirect($backUrl);
        }

        // Honeypot anti-bot: si ve omplert, descartar en silenci
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            Response::redirect($backUrl);
        }

        $needle = trim((string) $req->post('dni_email', ''));
        Session::flash('recover_old', $needle);

        if ($needle === '') {
            Session::flash('error', t('recover.empty'));
            Response::redirect($backUrl);
        }

        // Límit de freqüència per IP: evita enumeració massiva de DNIs/correus i
        // l'enviament massiu de correus als inscrits. Màx 8 intents / 15 min.
        $rlKey = 'recover:' . $req->ip;
        if (RateLimit::tooMany($rlKey, 8, 900)) {
            Session::flash('error', t('recover.rate_limited'));
            Response::redirect($backUrl);
        }
        RateLimit::hit($rlKey);

        // Resposta NEUTRA sempre (no revela si el DNI/correu està inscrit ni quin
        // correu és): si hi ha coincidències, s'envia el comprovant en silenci.
        $eventoId  = $evento !== null ? (int) $evento['id'] : null;
        $inscritos = Inscrito::findConfirmadosByDniOrEmail($needle, $eventoId);
        foreach ($inscritos as $ins) {
            self::enviarComprovantEmail($ins);
        }

        Session::flash('success', t('recover.sent_generic'));
        Response::redirect($backUrl);
    }

    /** Carrega context i envia l'email de confirmació amb QR per a un inscrit. */
    private static function enviarComprovantEmail(array $inscrito): bool
    {
        try {
            if (empty($inscrito['email'])) return false;
            $evento = Evento::findById((int) $inscrito['evento_id']);
            $tarifa = Tarifa::findById((int) $inscrito['tarifa_id']);
            if ($evento === null || $tarifa === null) return false;

            $pago = Database::getInstance()->query(
                'SELECT * FROM pagos WHERE inscrito_id = ? ORDER BY id DESC LIMIT 1',
                [(int) $inscrito['id']]
            )->fetch() ?: [];

            Inscrito::ensureQrToken((int) $inscrito['id']);
            $inscrito = Inscrito::findById((int) $inscrito['id']); // recarregar amb qr_token

            EmailService::sendConfirmacionInscripcion($inscrito, $evento, $tarifa, $pago);
            return true;
        } catch (\Throwable $e) {
            error_log('[Comprovant recover] ' . $e->getMessage());
            return false;
        }
    }

    /** Emmascara un email: "sergio@hotmail.com" → "ser***@hotmail.com". */
    private static function maskEmail(string $email): string
    {
        $email = trim($email);
        $at = strpos($email, '@');
        if ($at === false) return '***';
        $local  = substr($email, 0, $at);
        $domain = substr($email, $at);
        $keep = mb_strlen($local) <= 3 ? 1 : min(3, (int) floor(mb_strlen($local) / 2));
        return mb_substr($local, 0, $keep) . '***' . $domain;
    }

    // ────────────────────────────────────────────────────────
    // Helpers privados
    // ────────────────────────────────────────────────────────

    /**
     * @param array<string,string> $config  [camp => 'obligatori'|'opcional'|'ocult']
     * @return array<string, mixed>
     */
    private static function extractCorredorData(array $post, array $config, ?array $tarifa = null): array
    {
        $cp = preg_replace('/\D+/', '', trim((string) ($post['codigo_postal'] ?? ''))) ?? '';
        $data = [
            'nombre'           => trim((string) ($post['nombre'] ?? '')),
            'apellido'         => trim((string) ($post['apellido'] ?? '')),
            'sexo'             => strtoupper(trim((string) ($post['sexo'] ?? ''))),
            'fecha_nacimiento' => trim((string) ($post['fecha_nacimiento'] ?? '')),
            'dni'              => strtoupper(trim((string) ($post['dni'] ?? ''))),
            'email'            => strtolower(trim((string) ($post['email'] ?? ''))),
            'telefono'         => preg_replace('/\s+/', '', trim((string) ($post['telefono'] ?? ''))) ?? '',
            'club'             => trim((string) ($post['club'] ?? '')) ?: null,
            'talla_camiseta'   => trim((string) ($post['talla_camiseta'] ?? '')) ?: null,
            'poblacion'        => mb_substr(trim((string) ($post['poblacion'] ?? '')), 0, 120) ?: null,
            'codigo_postal'    => $cp !== '' ? mb_substr($cp, 0, 10) : null,
            'franja_temps'     => mb_substr(trim((string) ($post['franja_temps'] ?? '')), 0, 60) ?: null,
        ];

        // Dades del tutor: només per a modalitats infantils. Si la modalitat NO és
        // infantil, s'ignoren encara que vinguin al POST (defensa servidor).
        if (\App\Models\Tarifa::esInfantil($tarifa)) {
            $data['tutor_nombre']   = mb_substr(trim((string) ($post['tutor_nombre'] ?? '')), 0, 100) ?: null;
            $data['tutor_apellido'] = mb_substr(trim((string) ($post['tutor_apellido'] ?? '')), 0, 150) ?: null;
            $data['tutor_dni']      = strtoupper(trim((string) ($post['tutor_dni'] ?? ''))) ?: null;
        } else {
            $data['tutor_nombre'] = null;
            $data['tutor_apellido'] = null;
            $data['tutor_dni'] = null;
        }

        // Camps ocults: no confiar en el POST, forçar buit/null
        foreach (CamposFijos::CAMPS as $key => $_meta) {
            if (($config[$key] ?? '') === 'ocult') {
                $data[$key] = null;
            }
        }

        // Columnes que ara són nullables: '' → null (només nom i email queden NOT NULL)
        foreach (['apellido', 'sexo', 'fecha_nacimiento', 'dni', 'telefono'] as $key) {
            if ($data[$key] === '') $data[$key] = null;
        }

        return $data;
    }

    /**
     * @param array<string,string> $config  [camp => 'obligatori'|'opcional'|'ocult']
     */
    private static function validateCorredor(array $data, array $config, ?array $tarifa = null, ?array $evento = null): Validator
    {
        $v = new Validator($data);

        $req = static fn(string $k): bool => ($config[$k] ?? 'obligatori') === 'obligatori';
        $vis = static fn(string $k): bool => ($config[$k] ?? 'obligatori') !== 'ocult';

        // Sempre obligatoris (no configurables)
        $v->required('nombre')->max('nombre', 100);
        $v->required('email')->email('email')->max('email', 255);

        // Cognoms
        if ($vis('apellido')) {
            if ($req('apellido')) $v->required('apellido');
            $v->max('apellido', 150);
        }

        // Sexe
        if ($vis('sexo')) {
            if ($req('sexo')) $v->required('sexo');
            $v->in('sexo', Inscrito::SEXOS);
        }

        // Data de naixement
        if ($vis('fecha_nacimiento')) {
            if ($req('fecha_nacimiento')) $v->required('fecha_nacimiento');
            $v->date('fecha_nacimiento');
            if (!empty($data['fecha_nacimiento']) && $v->first('fecha_nacimiento') === null) {
                $fn = strtotime((string) $data['fecha_nacimiento']);
                if ($fn === false || $fn > time() || $fn < strtotime('-110 years')) {
                    $v->addError('fecha_nacimiento', 'Data de naixement no plausible.');
                }
            }
        }

        // DNI / NIE
        if ($vis('dni')) {
            if ($req('dni')) $v->required('dni');
            if (!empty($data['dni']) && !Inscrito::dniValido((string) $data['dni'])) {
                $v->addError('dni', 'DNI o NIE no vàlid.');
            }
        }

        // Telèfon
        if ($vis('telefono')) {
            if ($req('telefono')) $v->required('telefono');
            if (!empty($data['telefono']) && !preg_match('/^\+?\d{9,15}$/', (string) $data['telefono'])) {
                $v->addError('telefono', 'Número de telèfon no vàlid.');
            }
        }

        // Talla
        if ($vis('talla_camiseta')) {
            if ($req('talla_camiseta')) $v->required('talla_camiseta');
            if (!empty($data['talla_camiseta'])) $v->in('talla_camiseta', Inscrito::TALLAS);
        }

        // Club
        if ($vis('club') && $req('club')) $v->required('club');

        // Població
        if ($vis('poblacion') && $req('poblacion')) $v->required('poblacion');

        // Codi postal
        if ($vis('codigo_postal')) {
            if ($req('codigo_postal')) $v->required('codigo_postal');
            if (!empty($data['codigo_postal']) && !preg_match('/^\d{4,5}$/', (string) $data['codigo_postal'])) {
                $v->addError('codigo_postal', 'Codi postal no vàlid.');
            }
        }

        // Franja de temps (camp fix, select amb opcions per event)
        if ($vis('franja_temps')) {
            if ($req('franja_temps')) $v->required('franja_temps');
            if (!empty($data['franja_temps']) && $evento !== null) {
                $labels = array_map(fn($f) => $f['label'], \App\Models\Evento::franjasConfig($evento));
                if ($labels && !in_array((string) $data['franja_temps'], $labels, true)) {
                    $v->addError('franja_temps', 'Tria una franja de temps vàlida.');
                }
            }
        }

        // Dades del tutor: obligatòries si la modalitat és infantil (menors)
        if (\App\Models\Tarifa::esInfantil($tarifa)) {
            if (empty($data['tutor_nombre']))   $v->addError('tutor_nombre', t('form.tutor.required'));
            if (empty($data['tutor_apellido'])) $v->addError('tutor_apellido', t('form.tutor.required'));
            if (empty($data['tutor_dni'])) {
                $v->addError('tutor_dni', t('form.tutor.required'));
            } elseif (!Inscrito::dniValido((string) $data['tutor_dni'])) {
                $v->addError('tutor_dni', t('form.tutor.dni_invalid'));
            }
        }

        return $v;
    }

    /**
     * Valida que l'any de naixement del corredor entri dins del rang permès per
     * la tarifa (p.ex. infantil/veterans). Si la tarifa no té restricció, no fa res.
     * Els errors s'adjunten al camp `tarifa_id` (es mostren al costat del selector).
     */
    private static function validateAnioNacimiento(Validator $v, array $tarifa, string $fechaNac): void
    {
        $min = isset($tarifa['anio_nac_min']) && $tarifa['anio_nac_min'] !== null ? (int) $tarifa['anio_nac_min'] : null;
        $max = isset($tarifa['anio_nac_max']) && $tarifa['anio_nac_max'] !== null ? (int) $tarifa['anio_nac_max'] : null;
        if ($min === null && $max === null) return;

        $fechaNac = trim($fechaNac);
        if ($fechaNac === '') {
            $v->addError('tarifa_id', t('form.tarifa.nac_msg_need'));
            return;
        }
        $ts = strtotime($fechaNac);
        if ($ts === false) return; // data invàlida: ja ho controla la validació de fecha_nacimiento

        $year = (int) date('Y', $ts);
        if (($min !== null && $year < $min) || ($max !== null && $year > $max)) {
            if ($min !== null && $max !== null) {
                $v->addError('tarifa_id', t('form.tarifa.nac_msg_between', ['min' => $min, 'max' => $max]));
            } elseif ($min !== null) {
                $v->addError('tarifa_id', t('form.tarifa.nac_msg_from', ['min' => $min]));
            } else {
                $v->addError('tarifa_id', t('form.tarifa.nac_msg_until', ['max' => $max]));
            }
        }
    }

    /**
     * Valida que la talla triada estigui disponible per al sexe (config
     * `eventos.tallas_sexo`). Sense restricció / sexe 'NB' / buit → no fa res.
     */
    private static function validateTallaSexo(Validator $v, ?string $tallasSexoJson, ?string $sexo, ?string $talla): void
    {
        if (empty($talla) || empty($sexo)) return;
        if (!in_array($talla, Inscrito::tallasParaSexo($tallasSexoJson, $sexo), true)) {
            $v->addError('talla_camiseta', t('form.talla_sexo_invalid'));
        }
    }
}
