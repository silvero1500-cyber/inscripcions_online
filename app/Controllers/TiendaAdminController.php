<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Ajuste;
use App\Models\PedidoTienda;
use App\Models\Producto;
use App\Services\ImageUploader;

/**
 * Gestió de la botiga (productes) — NOMÉS superadmin.
 */
final class TiendaAdminController
{
    public function index(Request $req): void
    {
        View::render('admin/tienda/index', [
            'user'      => Auth::user(),
            'productos' => Producto::listAll(),
            'flash'     => Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    public function create(Request $req): void
    {
        $this->renderForm(null);
    }

    public function edit(Request $req, array $params): void
    {
        $producto = Producto::findById((int) ($params['id'] ?? 0));
        if ($producto === null) Response::notFound();
        $this->renderForm($producto);
    }

    public function store(Request $req): void
    {
        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada. Torna-ho a provar.');
            Response::redirect(base_url('/admin/tienda/nou'));
        }
        $data = self::extractData($_POST);
        if ($data['nombre'] === '') {
            Session::flash('error', 'El nom del producte és obligatori.');
            Response::redirect(base_url('/admin/tienda/nou'));
        }
        $id = Producto::create($data);
        Producto::syncAtributos($id, self::extractAtributos($_POST));
        Producto::syncVariaciones($id, self::extractVariaciones($_POST));
        self::handleImagenes($id);

        Session::flash('success', 'Producte creat.');
        Response::redirect(base_url('/admin/tienda/' . $id . '/editar'));
    }

    public function update(Request $req, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $producto = Producto::findById($id);
        if ($producto === null) Response::notFound();
        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada. Torna-ho a provar.');
            Response::redirect(base_url('/admin/tienda/' . $id . '/editar'));
        }
        $data = self::extractData($_POST);
        if ($data['nombre'] === '') {
            Session::flash('error', 'El nom del producte és obligatori.');
            Response::redirect(base_url('/admin/tienda/' . $id . '/editar'));
        }
        Producto::update($id, $data);
        Producto::syncAtributos($id, self::extractAtributos($_POST));
        Producto::syncVariaciones($id, self::extractVariaciones($_POST));
        self::handleImagenes($id);

        Session::flash('success', 'Producte desat.');
        Response::redirect(base_url('/admin/tienda/' . $id . '/editar'));
    }

    public function destroy(Request $req, array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();
        if (Producto::findById($id) !== null) {
            Producto::delete($id);
            Session::flash('success', 'Producte eliminat.');
        }
        Response::redirect(base_url('/admin/tienda'));
    }

    public function deleteImage(Request $req, array $params): void
    {
        $imgId = (int) ($params['imgId'] ?? 0);
        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();
        $img = Producto::imagenById($imgId);
        if ($img === null) Response::notFound();
        $productoId = (int) $img['producto_id'];
        Producto::deleteImagen($imgId);
        Session::flash('success', 'Imatge eliminada.');
        Response::redirect(base_url('/admin/tienda/' . $productoId . '/editar'));
    }

    // ── Configuració de la botiga ───────────────────────────
    public function config(Request $req): void
    {
        View::render('admin/tienda/config', [
            'user'    => Auth::user(),
            'lugar'   => Ajuste::get(Ajuste::TIENDA_LUGAR, ''),
            'horario' => Ajuste::get(Ajuste::TIENDA_HORARIO, ''),
            'flash'   => Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    public function configStore(Request $req): void
    {
        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada. Torna-ho a provar.');
            Response::redirect(base_url('/admin/tienda/config'));
        }
        Ajuste::set(Ajuste::TIENDA_LUGAR, mb_substr(trim((string) $req->post('lugar', '')), 0, 500) ?: null);
        Ajuste::set(Ajuste::TIENDA_HORARIO, mb_substr(trim((string) $req->post('horario', '')), 0, 500) ?: null);
        Session::flash('success', 'Configuració de la botiga desada.');
        Response::redirect(base_url('/admin/tienda/config'));
    }

    // ── Comandes (pedidos) ──────────────────────────────────
    public function comandes(Request $req): void
    {
        PedidoTienda::expirarPendientes();
        $pedidos = PedidoTienda::listAll();
        $lineasPorPedido = [];
        foreach ($pedidos as $p) {
            $lineasPorPedido[(int) $p['id']] = PedidoTienda::lineas((int) $p['id']);
        }
        View::render('admin/tienda/comandes', [
            'user'    => Auth::user(),
            'pedidos' => $pedidos,
            'lineas'  => $lineasPorPedido,
            'flash'   => Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    public function comandaLlest(Request $req, array $params): void
    {
        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();
        $id = (int) ($params['id'] ?? 0);
        $pedido = PedidoTienda::findById($id);
        if ($pedido !== null && $pedido['estado'] === 'pagado') {
            PedidoTienda::marcarListo($id);
            try {
                \App\Services\EmailService::sendComandaTiendaLista($id);
                Session::flash('success', 'Comanda ' . $pedido['codigo'] . ' marcada com a llesta. S\'ha avisat el client per correu.');
            } catch (\Throwable $e) {
                error_log('[Tienda] email llest: ' . $e->getMessage());
                Session::flash('success', 'Comanda ' . $pedido['codigo'] . ' marcada com a llesta (l\'email no s\'ha pogut enviar).');
            }
        }
        Response::redirect(base_url('/admin/tienda/comandes'));
    }

    public function comandaEntregat(Request $req, array $params): void
    {
        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();
        $id = (int) ($params['id'] ?? 0);
        $pedido = PedidoTienda::findById($id);
        if ($pedido !== null && $pedido['estado'] === 'pagado') {
            PedidoTienda::marcarEntregat($id);
            Session::flash('success', 'Comanda ' . $pedido['codigo'] . ' marcada com a recollida.');
        }
        Response::redirect(base_url('/admin/tienda/comandes'));
    }

    // ── Helpers ─────────────────────────────────────────────
    private function renderForm(?array $producto): void
    {
        $id = $producto !== null ? (int) $producto['id'] : 0;
        View::render('admin/tienda/form', [
            'user'        => Auth::user(),
            'producto'    => $producto,
            'imagenes'    => $id ? Producto::imagenes($id) : [],
            'atributos'   => $id ? Producto::atributos($id) : [],
            'variaciones' => $id ? Producto::variaciones($id) : [],
            'flash'       => Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    private static function extractData(array $post): array
    {
        $tipo = ($post['tipo'] ?? 'simple') === 'variable' ? 'variable' : 'simple';
        return [
            'nombre'      => mb_substr(trim((string) ($post['nombre'] ?? '')), 0, 255),
            'descripcion' => trim((string) ($post['descripcion'] ?? '')) ?: null,
            'tipo'        => $tipo,
            'precio'      => round((float) ($post['precio'] ?? 0), 2),
            'stock'       => ($post['stock'] ?? '') === '' ? null : max(0, (int) $post['stock']),
            'activo'      => !empty($post['activo']) ? 1 : 0,
            'destacado'   => !empty($post['destacado']) ? 1 : 0,
            'orden'       => (int) ($post['orden'] ?? 0),
            'slug'        => trim((string) ($post['slug'] ?? '')) ?: (string) ($post['nombre'] ?? ''),
        ];
    }

    /** @return list<array{nombre:string, valores:list<string>}> */
    private static function extractAtributos(array $post): array
    {
        $raw = $post['atributos'] ?? [];
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $a) {
            $nombre = trim((string) ($a['nombre'] ?? ''));
            $valoresRaw = (string) ($a['valores'] ?? '');
            if ($nombre === '' || $valoresRaw === '') continue;
            $valores = array_values(array_filter(
                array_map('trim', preg_split('/\s*\|\s*/', $valoresRaw) ?: []),
                fn($v) => $v !== ''
            ));
            if ($valores) $out[] = ['nombre' => $nombre, 'valores' => $valores];
        }
        return $out;
    }

    /** @return list<array{combinacion:array<string,string>, precio:float, stock:?int, sku:?string}> */
    private static function extractVariaciones(array $post): array
    {
        $raw = $post['variaciones'] ?? [];
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $v) {
            $combo = json_decode((string) ($v['combinacion'] ?? ''), true);
            if (!is_array($combo) || $combo === []) continue;
            $out[] = [
                'combinacion' => array_map('strval', $combo),
                'precio'      => round((float) ($v['precio'] ?? 0), 2),
                'stock'       => ($v['stock'] ?? '') === '' ? null : max(0, (int) $v['stock']),
                'sku'         => trim((string) ($v['sku'] ?? '')) ?: null,
            ];
        }
        return $out;
    }

    /** Puja les imatges noves (input file múltiple `imagenes[]`). */
    private static function handleImagenes(int $productoId): void
    {
        if (empty($_FILES['imagenes']) || !is_array($_FILES['imagenes']['name'])) return;
        $files = $_FILES['imagenes'];
        $count = count($files['name']);
        $orden = count(Producto::imagenes($productoId));
        for ($i = 0; $i < $count; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $one = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i] ?? 0,
            ];
            try {
                $ruta = ImageUploader::handleEventImage($one, 'productos');
                if ($ruta !== null) Producto::addImagen($productoId, $ruta, $orden++);
            } catch (\Throwable $e) {
                Session::flash('error', 'Alguna imatge no s\'ha pogut pujar: ' . $e->getMessage());
            }
        }
    }
}
