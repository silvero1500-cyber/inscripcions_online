<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Producto;
use App\Services\Cart;

/**
 * Botiga pública: catàleg, fitxa de producte i carret. Recollida en tenda.
 */
final class TiendaController
{
    public function index(Request $req): void
    {
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
        View::render('public/tienda/cistella', [
            'items'     => Cart::items(),
            'total'     => Cart::total(),
            'cartCount' => Cart::count(),
            'pageTitle' => t('shop.cart') . ' · WeRun',
        ], layout: 'public');
    }

    public function cartUpdate(Request $req): void
    {
        if (Csrf::verify($req->post('_csrf'))) {
            $key = (string) $req->post('key', '');
            $cantidad = (int) $req->post('cantidad', '0');
            if ($key !== '') Cart::setQty($key, $cantidad);
        }
        Response::redirect(base_url('/tienda/cistella'));
    }

    public function cartRemove(Request $req): void
    {
        if (Csrf::verify($req->post('_csrf'))) {
            $key = (string) $req->post('key', '');
            if ($key !== '') Cart::remove($key);
        }
        Response::redirect(base_url('/tienda/cistella'));
    }
}
