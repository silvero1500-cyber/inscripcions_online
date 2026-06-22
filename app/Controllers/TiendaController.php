<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Ajuste;
use App\Models\Pago;
use App\Models\PedidoTienda;
use App\Models\Producto;
use App\Services\Cart;
use App\Services\RedsysService;

/**
 * Botiga pública: catàleg, fitxa de producte i carret. Recollida en botiga.
 */
final class TiendaController
{
    /** Si la botiga està desactivada, les pàgines públiques no existeixen. */
    private static function ensureActiva(): void
    {
        if (!Ajuste::tiendaActiva()) Response::notFound();
    }

    public function index(Request $req): void
    {
        self::ensureActiva();
        View::render('public/tienda/index', [
            'productos'       => Producto::listActivos(),
            'cartCount'       => Cart::count(),
            'pageTitle'       => t('shop.title') . ' · WeRun',
            'metaDescription' => t('shop.subtitle'),
            'ogTitle'         => t('shop.title'),
            'ogUrl'           => base_url('/tienda'),
        ], layout: 'public');
    }

    public function show(Request $req, array $params): void
    {
        self::ensureActiva();
        $slug = (string) ($params['slug'] ?? '');
        $producto = Producto::findBySlug($slug);
        if ($producto === null || (int) $producto['activo'] !== 1) {
            Response::notFound();
        }
        $pid = (int) $producto['id'];

        View::render('public/tienda/producto', [
            'producto'    => $producto,
            'imagenes'    => Producto::imagenes($pid),
            'atributos'   => Producto::atributos($pid),
            'variaciones' => Producto::variaciones($pid, true), // només actives
            'cartCount'   => Cart::count(),
            'flashError'  => Session::pullFlash('shop_error'),
            'pageTitle'   => $producto['nombre'] . ' · WeRun',
            'metaDescription' => mb_substr(trim((string) preg_replace('/\s+/', ' ', strip_tags((string) ($producto['descripcion'] ?? '')))), 0, 180),
            'ogTitle'     => $producto['nombre'],
            'ogImage'     => \App\Services\ImageUploader::publicUrl(Producto::imagenPrincipal($pid)),
            'ogUrl'       => base_url('/tienda/' . $producto['slug']),
        ], layout: 'public');
    }

    public function cartAdd(Request $req): void
    {
        self::ensureActiva();
        $slug = (string) $req->post('slug', '');
        $back = base_url('/tienda/' . $slug);

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('shop_error', t('shop.err_session'));
            Response::redirect($back);
        }

        $producto = Producto::findById((int) $req->post('producto_id', '0'));
        if ($producto === null || (int) $producto['activo'] !== 1) {
            Response::redirect(base_url('/tienda'));
        }

        $cantidad = max(1, (int) $req->post('cantidad', '1'));
        $varId = null;

        if ($producto['tipo'] === Producto::TIPO_VARIABLE) {
            $varId = (int) $req->post('variacion_id', '0');
            $var = $varId > 0 ? Producto::variacionById($varId) : null;
            if ($var === null || (int) $var['producto_id'] !== (int) $producto['id'] || (int) $var['activo'] !== 1) {
                Session::flash('shop_error', t('shop.err_variant'));
                Response::redirect(base_url('/tienda/' . $producto['slug']));
            }
            if ($var['stock'] !== null && (int) $var['stock'] <= 0) {
                Session::flash('shop_error', t('shop.err_soldout'));
                Response::redirect(base_url('/tienda/' . $producto['slug']));
            }
        } else {
            if ($producto['stock'] !== null && (int) $producto['stock'] <= 0) {
                Session::flash('shop_error', t('shop.err_soldout'));
                Response::redirect(base_url('/tienda/' . $producto['slug']));
            }
        }

        Cart::add((int) $producto['id'], $varId, $cantidad);
        Response::redirect(base_url('/tienda/cistella'));
    }

    public function cart(Request $req): void
    {
        self::ensureActiva();
        View::render('public/tienda/cistella', [
            'items'     => Cart::items(),
            'total'     => Cart::total(),
            'cartCount' => Cart::count(),
            'pageTitle' => t('shop.cart') . ' · WeRun',
        ], layout: 'public');
    }

    public function cartUpdate(Request $req): void
    {
        self::ensureActiva();
        if (Csrf::verify($req->post('_csrf'))) {
            $key = (string) $req->post('key', '');
            $cantidad = (int) $req->post('cantidad', '0');
            if ($key !== '') Cart::setQty($key, $cantidad);
        }
        Response::redirect(base_url('/tienda/cistella'));
    }

    public function cartRemove(Request $req): void
    {
        self::ensureActiva();
        if (Csrf::verify($req->post('_csrf'))) {
            $key = (string) $req->post('key', '');
            if ($key !== '') Cart::remove($key);
        }
        Response::redirect(base_url('/tienda/cistella'));
    }

    // ── Checkout ────────────────────────────────────────────
    public function checkout(Request $req): void
    {
        self::ensureActiva();
        PedidoTienda::expirarPendientes(); // allibera stock de comandes abandonades
        $items = Cart::items();
        if ($items === []) {
            Response::redirect(base_url('/tienda/cistella'));
        }
        View::render('public/tienda/checkout', [
            'items'     => $items,
            'total'     => Cart::total(),
            'cartCount' => Cart::count(),
            'old'       => Session::pullFlash('shop_checkout_old'),
            'flashError'=> Session::pullFlash('shop_error'),
            'pageTitle' => t('shop.checkout') . ' · WeRun',
        ], layout: 'public');
    }

    public function checkoutStore(Request $req): void
    {
        self::ensureActiva();
        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('shop_error', t('shop.err_session'));
            Response::redirect(base_url('/tienda/checkout'));
        }
        if (Cart::isEmpty()) {
            Response::redirect(base_url('/tienda/cistella'));
        }

        $nombre   = trim((string) $req->post('nombre', ''));
        $email    = strtolower(trim((string) $req->post('email', '')));
        $telefono = trim((string) $req->post('telefono', '')) ?: null;
        $payMethod = $req->post('pay_method') === RedsysService::PAY_METHOD_BIZUM
            ? RedsysService::PAY_METHOD_BIZUM : RedsysService::PAY_METHOD_CARD;

        if ($nombre === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('shop_error', t('shop.err_contacto'));
            Response::redirect(base_url('/tienda/checkout'));
        }

        // Crear la comanda descomptant stock (transacció amb bloqueig)
        try {
            $res = PedidoTienda::crearDesdeCarrito(Cart::raw(), [
                'nombre' => $nombre, 'email' => $email, 'telefono' => $telefono, 'locale' => current_locale(),
            ]);
        } catch (\DomainException $e) {
            $code = $e->getMessage();
            $msg = str_starts_with($code, 'sin_stock:')
                ? t('shop.err_stock', ['producte' => substr($code, 10)])
                : t('shop.err_soldout');
            Session::flash('shop_error', $msg);
            Response::redirect(base_url('/tienda/cistella'));
        }

        Cart::clear();

        // Configuració Redsys
        try {
            $redsys = new RedsysService();
        } catch (\Throwable $e) {
            error_log('[Tienda] Redsys config: ' . $e->getMessage());
            View::render('public/pago/error_config', [], layout: 'public');
            return;
        }

        $order  = '';
        $pagoId = 0;
        for ($i = 0; $i < 5; $i++) {
            try {
                $order  = RedsysService::generateOrder();
                $pagoId = Pago::crearIniciadoTienda(
                    (int) $res['id'], $order, (float) $res['total'],
                    (string) Env::get('REDSYS_MERCHANT_CODE', ''),
                    (string) Env::get('REDSYS_TERMINAL', '')
                );
                break;
            } catch (\Throwable $e) {
                if ($i === 4) { error_log('[Tienda] ds_order: ' . $e->getMessage()); Response::serverError('No es pot iniciar el pagament.'); }
            }
        }

        $redsysLang = match (current_locale()) { 'es' => '001', 'en' => '002', default => '003' };
        try {
            $form = $redsys->buildPaymentForm([
                'order'            => $order,
                'amount_cents'     => (int) round((float) $res['total'] * 100),
                'description'      => 'Botiga WeRun · ' . $res['codigo'],
                'titular'          => $nombre,
                'url_notification' => base_url('/pago/notificacion'),
                'url_ok'           => base_url('/tienda/comanda/' . $res['codigo']),
                'url_ko'           => base_url('/tienda/comanda/' . $res['codigo']),
                'language'         => $redsysLang,
                'pay_method'       => $payMethod,
            ]);
        } catch (\Throwable $e) {
            error_log('[Tienda] buildPaymentForm: ' . $e->getMessage());
            Response::serverError('No es pot iniciar el pagament.');
        }

        Session::set('tienda_pago', ['pago_id' => $pagoId, 'ds_order' => $order, 'pedido_id' => (int) $res['id']]);

        View::render('public/pago/redirigir', [
            'url'        => $form['url'],
            'fields'     => $form['fields'],
            'evento'     => ['titulo' => 'Botiga WeRun'],
            'importe'    => (float) $res['total'],
            'pay_method' => $payMethod,
        ], layout: 'public');
    }

    public function comanda(Request $req, array $params): void
    {
        $pedido = PedidoTienda::findByCodigo((string) ($params['codigo'] ?? ''));
        if ($pedido === null) Response::notFound();

        View::render('public/tienda/comanda', [
            'pedido'    => $pedido,
            'lineas'    => PedidoTienda::lineas((int) $pedido['id']),
            'cartCount' => Cart::count(),
            'pageTitle' => t('shop.order') . ' ' . $pedido['codigo'] . ' · WeRun',
        ], layout: 'public');
    }
}
