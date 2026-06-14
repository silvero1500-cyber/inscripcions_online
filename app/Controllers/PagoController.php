<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Evento;
use App\Models\Inscrito;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoTienda;
use App\Models\RedsysNotificacion;
use App\Models\Tarifa;
use App\Services\EmailService;
use App\Services\RedsysService;

final class PagoController
{
    /**
     * Pantalla de elección de método: Tarjeta o Bizum.
     */
    public function metodo(Request $req): void
    {
        // ── Grup (pedido) ──
        $ctxP = self::cargarContextoPedido();
        if ($ctxP !== null) {
            if ($ctxP['pedido']['estado'] === 'confirmado'
                || Pago::hayPagoCompletadoPedido((int) $ctxP['pedido']['id'])) {
                Response::redirect(base_url('/eventos/' . $ctxP['evento']['slug'] . '/gracies'));
            }
            $n = count($ctxP['inscritos']);
            View::render('public/pago/metodo', [
                'evento'   => $ctxP['evento'],
                'concepto' => $n . ' ' . mb_strtolower(t('group.participants_title')),
                'importe'  => (float) $ctxP['pedido']['importe_total'],
                'error'    => Session::pullFlash('pago_metodo_error'),
            ], layout: 'public');
            return;
        }

        $ctx = self::cargarContextoSesion();
        if ($ctx === null) {
            Response::redirect(base_url('/'));
        }

        // Si ya está pagado, salir
        if ($ctx['inscrito']['estado'] === 'confirmado'
            || Pago::hayPagoCompletado((int) $ctx['inscrito']['id'])) {
            Response::redirect(base_url('/pago/ok?o=' . urlencode((string) ($ctx['ult']['ds_order'] ?? ''))));
        }

        View::render('public/pago/metodo', [
            'evento'   => $ctx['evento'],
            'concepto' => $ctx['tarifa']['nombre'],
            'tarifa'   => $ctx['tarifa'],
            'inscrito' => $ctx['inscrito'],
            'importe'  => (float) $ctx['tarifa']['precio'],
            'error'    => Session::pullFlash('pago_metodo_error'),
        ], layout: 'public');
    }

    /**
     * Guarda la elección de método de pago y manda a /pago/iniciar.
     */
    public function metodoStore(Request $req): void
    {
        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('pago_metodo_error', 'Sessió expirada. Torna a triar.');
            Response::redirect(base_url('/pago/metodo'));
        }

        $metodo = (string) $req->post('metodo', '');
        if (!in_array($metodo, [RedsysService::PAY_METHOD_CARD, RedsysService::PAY_METHOD_BIZUM], true)) {
            Session::flash('pago_metodo_error', 'Tria un mètode de pagament vàlid.');
            Response::redirect(base_url('/pago/metodo'));
        }

        // Funciona tant per a inscripció individual com per a pedido (grup)
        $key = is_array(Session::get('ultimo_pedido')) ? 'ultimo_pedido' : 'ultima_inscripcion';
        $ult = Session::get($key);
        if (!is_array($ult)) {
            Response::redirect(base_url('/'));
        }
        $ult['pay_method'] = $metodo;
        Session::set($key, $ult);

        Response::redirect(base_url('/pago/iniciar'));
    }

    /**
     * Pantalla intermedia: crea/recupera el Pago y muestra el formulario
     * auto-submit que postea hacia Redsys.
     */
    public function iniciar(Request $req): void
    {
        // ── Grup (pedido): un sol pagament pel total ──
        $ctxP = self::cargarContextoPedido();
        if ($ctxP !== null) {
            $pedido = $ctxP['pedido']; $evento = $ctxP['evento']; $inscritos = $ctxP['inscritos'];

            $payMethod = (string) ($ctxP['ult']['pay_method'] ?? '');
            if (!in_array($payMethod, [RedsysService::PAY_METHOD_CARD, RedsysService::PAY_METHOD_BIZUM], true)) {
                Response::redirect(base_url('/pago/metodo'));
            }
            if ($pedido['estado'] === 'confirmado' || Pago::hayPagoCompletadoPedido((int) $pedido['id'])) {
                Response::redirect(base_url('/eventos/' . $evento['slug'] . '/gracies'));
            }

            try {
                $redsys = new RedsysService();
            } catch (\Throwable $e) {
                error_log('[Redsys] Configuración inválida: ' . $e->getMessage());
                View::render('public/pago/error_config', [], layout: 'public');
                return;
            }

            $importeFinal = (float) $pedido['importe_total'];
            $importeCents = (int) round($importeFinal * 100);
            $repId = (int) $inscritos[0]['id'];

            $order = ''; $pagoId = 0;
            for ($i = 0; $i < 5; $i++) {
                try {
                    $order  = RedsysService::generateOrder();
                    $pagoId = Pago::crearIniciadoPedido(
                        (int) $pedido['id'], $repId, $order, $importeFinal,
                        (string) (\App\Core\Env::get('REDSYS_MERCHANT_CODE', '')),
                        (string) (\App\Core\Env::get('REDSYS_TERMINAL', ''))
                    );
                    break;
                } catch (\Throwable $e) {
                    if ($i === 4) { error_log('[Redsys] No se pudo generar ds_order (pedido): ' . $e->getMessage()); Response::serverError('No se pudo iniciar el pago.'); }
                }
            }

            $redsysLang = match ((string) ($pedido['locale'] ?? 'ca')) { 'es' => '001', 'en' => '002', default => '003' };
            $titular = trim($inscritos[0]['nombre'] . ' ' . (string) ($inscritos[0]['apellido'] ?? ''));

            try {
                $form = $redsys->buildPaymentForm([
                    'order'            => $order,
                    'amount_cents'     => $importeCents,
                    'description'      => $evento['titulo'] . ' · ' . count($inscritos) . ' participants',
                    'titular'          => $titular !== '' ? $titular : (string) $pedido['email'],
                    'url_notification' => base_url('/pago/notificacion'),
                    'url_ok'           => base_url('/pago/ok?o=' . $order),
                    'url_ko'           => base_url('/pago/ko?o=' . $order),
                    'language'         => $redsysLang,
                    'pay_method'       => $payMethod,
                ]);
            } catch (\Throwable $e) {
                error_log('[Redsys] Error preparando formulario (pedido): ' . $e->getMessage());
                Response::serverError('No se pudo iniciar el pago.');
            }

            Session::set('pago_actual', [
                'pago_id'   => $pagoId,
                'ds_order'  => $order,
                'pedido_id' => (int) $pedido['id'],
            ]);

            View::render('public/pago/redirigir', [
                'url'        => $form['url'],
                'fields'     => $form['fields'],
                'evento'     => $evento,
                'importe'    => $importeFinal,
                'pay_method' => $payMethod,
            ], layout: 'public');
            return;
        }

        $ctx = self::cargarContextoSesion();
        if ($ctx === null) {
            Response::redirect(base_url('/'));
        }

        // Si no eligió método, mandar al selector
        $payMethod = (string) ($ctx['ult']['pay_method'] ?? '');
        if (!in_array($payMethod, [RedsysService::PAY_METHOD_CARD, RedsysService::PAY_METHOD_BIZUM], true)) {
            Response::redirect(base_url('/pago/metodo'));
        }

        $inscrito = $ctx['inscrito'];
        $evento   = $ctx['evento'];
        $tarifa   = $ctx['tarifa'];

        // Si ya está pagado, llevar directamente a la pantalla OK
        if ($inscrito['estado'] === 'confirmado' || Pago::hayPagoCompletado((int) $inscrito['id'])) {
            Response::redirect(base_url('/pago/ok'));
        }

        try {
            $redsys = new RedsysService();
        } catch (\Throwable $e) {
            error_log('[Redsys] Configuración inválida: ' . $e->getMessage());
            View::render('public/pago/error_config', [], layout: 'public');
            return;
        }

        // Calcular precio final aplicando descompte si el inscrit el va usar
        $precioOriginal = (float) $tarifa['precio'];
        $precioFinal = $precioOriginal;
        if (!empty($inscrito['descuento_porcentaje'])) {
            $precioFinal = round($precioOriginal * (1 - (float)$inscrito['descuento_porcentaje'] / 100), 2);
        }
        $importeCents = (int) round($precioFinal * 100);

        // Generar order único (con reintentos por si hay colisión UNIQUE)
        $order  = '';
        $pagoId = 0;
        for ($i = 0; $i < 5; $i++) {
            try {
                $order  = RedsysService::generateOrder();
                $pagoId = Pago::crearIniciado(
                    (int) $inscrito['id'],
                    $order,
                    $precioFinal,
                    (string) (\App\Core\Env::get('REDSYS_MERCHANT_CODE', '')),
                    (string) (\App\Core\Env::get('REDSYS_TERMINAL', ''))
                );
                break;
            } catch (\Throwable $e) {
                if ($i === 4) {
                    error_log('[Redsys] No se pudo generar ds_order: ' . $e->getMessage());
                    Response::serverError('No se pudo iniciar el pago.');
                }
            }
        }

        // Mapejar locale de l'inscrit a codi Redsys (001=ES, 002=EN, 003=CA, 002 fallback EN)
        $redsysLang = match ((string) ($inscrito['locale'] ?? 'ca')) {
            'es'    => '001',
            'en'    => '002',
            default => '003',
        };

        try {
            $form = $redsys->buildPaymentForm([
                'order'            => $order,
                'amount_cents'     => $importeCents,
                'description'      => $evento['titulo'] . ' · ' . $tarifa['nombre'],
                'titular'          => trim($inscrito['nombre'] . ' ' . $inscrito['apellido']),
                'url_notification' => base_url('/pago/notificacion'),
                'url_ok'           => base_url('/pago/ok?o=' . $order),
                'url_ko'           => base_url('/pago/ko?o=' . $order),
                'language'         => $redsysLang,
                'pay_method'       => $payMethod,
            ]);
        } catch (\Throwable $e) {
            error_log('[Redsys] Error preparando formulario: ' . $e->getMessage());
            Response::serverError('No se pudo iniciar el pago.');
        }

        // Asociar pago→sesión para validar el retorno
        Session::set('pago_actual', [
            'pago_id'     => $pagoId,
            'ds_order'    => $order,
            'inscrito_id' => (int) $inscrito['id'],
        ]);

        View::render('public/pago/redirigir', [
            'url'        => $form['url'],
            'fields'     => $form['fields'],
            'evento'     => $evento,
            'tarifa'     => $tarifa,
            'inscrito'   => $inscrito,
            'importe'    => $precioFinal,
            'pay_method' => $payMethod,
        ], layout: 'public');
    }

    /**
     * Carga datos y envía email de confirmación con QR.
     * Genera el qr_token si no existe.
     */
    private static function enviarConfirmacion(int $inscritoId, int $pagoId): void
    {
        $inscrito = Inscrito::findById($inscritoId);
        if ($inscrito === null || empty($inscrito['email'])) {
            return;
        }
        $evento = Evento::findById((int) $inscrito['evento_id']);
        $tarifa = Tarifa::findById((int) $inscrito['tarifa_id']);
        $pago   = Pago::findById($pagoId);
        if ($evento === null || $tarifa === null) {
            return;
        }

        Inscrito::ensureQrToken($inscritoId);
        $inscrito = Inscrito::findById($inscritoId); // recargar con qr_token

        EmailService::sendConfirmacionInscripcion($inscrito, $evento, $tarifa, $pago ?? []);
    }

    /**
     * Carga el contexto (evento + tarifa + inscrito + sesión) común
     * a /pago/metodo y /pago/iniciar.
     *
     * @return array{ult:array<string,mixed>, evento:array<string,mixed>, tarifa:array<string,mixed>, inscrito:array<string,mixed>}|null
     */
    private static function cargarContextoSesion(): ?array
    {
        $ult = Session::get('ultima_inscripcion');
        if (!is_array($ult) || empty($ult['id']) || empty($ult['slug'])) {
            return null;
        }

        $inscrito = Inscrito::findById((int) $ult['id']);
        $evento   = Evento::findBySlug((string) $ult['slug']);
        $tarifa   = isset($ult['tarifa_id']) ? Tarifa::findById((int) $ult['tarifa_id']) : null;

        if ($inscrito === null || $evento === null || $tarifa === null) {
            return null;
        }
        return [
            'ult'      => $ult,
            'evento'   => $evento,
            'tarifa'   => $tarifa,
            'inscrito' => $inscrito,
        ];
    }

    /**
     * Context d'un PEDIDO (grup) des de la sessió `ultimo_pedido`.
     *
     * @return array{ult:array<string,mixed>, pedido:array<string,mixed>, evento:array<string,mixed>, inscritos:list<array<string,mixed>>}|null
     */
    private static function cargarContextoPedido(): ?array
    {
        $ult = Session::get('ultimo_pedido');
        if (!is_array($ult) || empty($ult['pedido_id']) || empty($ult['slug'])) {
            return null;
        }
        $pedido = Pedido::findById((int) $ult['pedido_id']);
        $evento = Evento::findBySlug((string) $ult['slug']);
        if ($pedido === null || $evento === null) {
            return null;
        }
        $inscritos = Pedido::inscritos((int) $pedido['id']);
        if (count($inscritos) === 0) {
            return null;
        }
        return [
            'ult'       => $ult,
            'pedido'    => $pedido,
            'evento'    => $evento,
            'inscritos' => $inscritos,
        ];
    }

    /**
     * IPN (Instant Payment Notification): Redsys nos hace POST server-to-server
     * con el resultado. Aquí actualizamos el estado del pago y la inscripción.
     *
     * No tiene CSRF (Redsys no conoce nuestro token); la autenticidad se
     * garantiza con la firma HMAC-SHA256.
     */
    public function notificacion(Request $req): void
    {
        $merchantParameters = (string) ($_POST['Ds_MerchantParameters'] ?? '');
        $signature          = (string) ($_POST['Ds_Signature'] ?? '');
        $rawPayload         = $_POST;

        try {
            $redsys = new RedsysService();
        } catch (\Throwable $e) {
            RedsysNotificacion::log('-', $rawPayload, $req->ip, false, 'Config Redsys: ' . $e->getMessage());
            Response::text('KO', 500);
        }

        $params = $redsys->verifyNotification($merchantParameters, $signature);
        if ($params === null) {
            RedsysNotificacion::log('-', $rawPayload, $req->ip, false, 'Firma inválida');
            // Devolver 200 OK igualmente para que Redsys no reintente eternamente;
            // el log queda para revisar manualmente.
            Response::text('OK', 200);
        }

        $dsOrder = (string) ($params['Ds_Order'] ?? '');
        $pago    = $dsOrder !== '' ? Pago::findByOrder($dsOrder) : null;
        if ($pago === null) {
            RedsysNotificacion::log($dsOrder, $params, $req->ip, false, 'Pago no encontrado');
            Response::text('OK', 200);
        }

        // Si ya estaba procesado, no volver a aplicar (idempotencia)
        if (in_array($pago['estado'], [Pago::ESTADO_COMPLETADO, Pago::ESTADO_FALLIDO], true)) {
            RedsysNotificacion::log($dsOrder, $params, $req->ip, true, 'Duplicado: ya procesado');
            Response::text('OK', 200);
        }

        $resultado = RedsysService::interpretResponse($params);

        Database::getInstance()->transaction(function () use ($pago, $params, $resultado): void {
            if ($resultado['success']) {
                Pago::marcarCompletado((int) $pago['id'], $params);
                if (!empty($pago['tienda_pedido_id'])) {
                    // Comanda de botiga
                    PedidoTienda::marcarPagado((int) $pago['tienda_pedido_id']);
                } elseif (!empty($pago['pedido_id'])) {
                    // Grup: confirmar el pedido i TOTS els seus participants
                    Pedido::marcarConfirmado((int) $pago['pedido_id']);
                    foreach (Pedido::inscritos((int) $pago['pedido_id']) as $ins) {
                        Inscrito::marcarConfirmado((int) $ins['id']);
                    }
                } else {
                    Inscrito::marcarConfirmado((int) $pago['inscrito_id']);
                }
            } else {
                Pago::marcarFallido((int) $pago['id'], $params);
                // No cancelamos la inscripción automáticamente:
                // el usuario podría reintentar el pago.
            }
        });

        RedsysNotificacion::log($dsOrder, $params, $req->ip, true, null);

        // Email de confirmación + QR (solo si el pago fue OK). Si falla el envío,
        // logueamos pero devolvemos OK a Redsys igualmente para no reintentar.
        if ($resultado['success']) {
            try {
                if (!empty($pago['tienda_pedido_id'])) {
                    EmailService::sendComandaTienda((int) $pago['tienda_pedido_id']);
                } elseif (!empty($pago['pedido_id'])) {
                    InscripcionController::enviarConfirmacionPedido((int) $pago['pedido_id']);
                } else {
                    self::enviarConfirmacion((int) $pago['inscrito_id'], (int) $pago['id']);
                }
            } catch (\Throwable $e) {
                error_log('[PagoController] Email confirmación falló: ' . $e->getMessage());
            }
        }

        Response::text('OK', 200);
    }

    /**
     * Retorno del navegador tras pago OK.
     * No es la fuente de verdad — el estado real lo fija la IPN.
     */
    public function ok(Request $req): void
    {
        $order = (string) $req->query('o', '');
        $pago  = $order !== '' ? Pago::findByOrder($order) : null;

        if ($pago === null) {
            // Sin contexto, mandamos al inicio
            Response::redirect(base_url('/'));
        }

        // ── Grup (pedido) ──
        if (!empty($pago['pedido_id'])) {
            $pedido = Pedido::findById((int) $pago['pedido_id']);
            $evento = $pedido ? Evento::findById((int) $pedido['evento_id']) : null;
            if ($pedido === null || $evento === null) {
                Response::redirect(base_url('/'));
            }
            Session::forget('pago_actual');
            if ($pedido['estado'] === 'confirmado') {
                Session::set('ultimo_pedido', ['pedido_id' => (int) $pedido['id'], 'slug' => $evento['slug'], 'pay_method' => null]);
                Response::redirect(base_url('/eventos/' . $evento['slug'] . '/gracies'));
            }
            // Encara esperant la confirmació (IPN)
            View::render('public/pago/ok', [
                'pago'      => $pago,
                'evento'    => $evento,
                'esperando' => true,
                'qrToken'   => null,
            ], layout: 'public');
            return;
        }

        $inscrito = Inscrito::findById((int) $pago['inscrito_id']);
        $evento   = $inscrito ? Evento::findById((int) $inscrito['evento_id']) : null;
        $tarifa   = $inscrito ? Tarifa::findById((int) $inscrito['tarifa_id']) : null;

        if ($inscrito === null || $evento === null || $tarifa === null) {
            Response::redirect(base_url('/'));
        }

        // Limpiar sesión de pago en curso
        Session::forget('pago_actual');

        // Si el pagament ja està completat, assegura el qr_token per al comprovant
        $qrToken = null;
        if ($pago['estado'] === Pago::ESTADO_COMPLETADO) {
            $qrToken = Inscrito::ensureQrToken((int) $inscrito['id']);
        }

        View::render('public/pago/ok', [
            'pago'     => $pago,
            'inscrito' => $inscrito,
            'evento'   => $evento,
            'tarifa'   => $tarifa,
            'esperando' => $pago['estado'] !== Pago::ESTADO_COMPLETADO,
            'qrToken'  => $qrToken,
        ], layout: 'public');
    }

    /**
     * Retorno tras pago KO (denegado, cancelado, etc.)
     */
    public function ko(Request $req): void
    {
        $order = (string) $req->query('o', '');
        $pago  = $order !== '' ? Pago::findByOrder($order) : null;

        // ── Grup (pedido) ──
        if ($pago && !empty($pago['pedido_id'])) {
            $pedido = Pedido::findById((int) $pago['pedido_id']);
            $evento = $pedido ? Evento::findById((int) $pedido['evento_id']) : null;
            View::render('public/pago/ko', [
                'pago'     => $pago,
                'evento'   => $evento,
                'inscrito' => null,
                'tarifa'   => null,
            ], layout: 'public');
            return;
        }

        $inscrito = $pago ? Inscrito::findById((int) $pago['inscrito_id']) : null;
        $evento   = $inscrito ? Evento::findById((int) $inscrito['evento_id']) : null;
        $tarifa   = $inscrito ? Tarifa::findById((int) $inscrito['tarifa_id']) : null;

        View::render('public/pago/ko', [
            'pago'     => $pago,
            'inscrito' => $inscrito,
            'evento'   => $evento,
            'tarifa'   => $tarifa,
        ], layout: 'public');
    }
}
