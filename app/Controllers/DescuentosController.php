<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\DescuentoEvento;
use App\Models\Evento;

/**
 * Gestió de cupons de descompte per evento.
 * L'organitzador propietari (o superadmin) pot generar lots, activar/desactivar i esborrar.
 */
final class DescuentosController
{
    public function index(Request $req, array $params): void
    {
        $user = Auth::user();
        $eventoId = (int) ($params['id'] ?? 0);

        $evento = Evento::findById($eventoId);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $eventoId)) Response::forbidden();

        // Si ve un ?lote=xxx, mostrem només els d'aquell lote (post-generació)
        $loteFilter = $req->query('lote');
        $descuentos = $loteFilter
            ? DescuentoEvento::listByLote($eventoId, $loteFilter)
            : DescuentoEvento::listByEvento($eventoId);

        View::render('admin/descuentos/index', [
            'user'       => $user,
            'evento'     => $evento,
            'descuentos' => $descuentos,
            'loteFilter' => $loteFilter,
            'flash'      => Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    public function generar(Request $req, array $params): void
    {
        $user = Auth::user();
        $eventoId = (int) ($params['id'] ?? 0);

        $evento = Evento::findById($eventoId);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $eventoId)) Response::forbidden();

        $oldJson    = Session::pullFlash('form_old');
        $errorsJson = Session::pullFlash('form_errors');

        View::render('admin/descuentos/generar', [
            'user'    => $user,
            'evento'  => $evento,
            'old'     => $oldJson ? (json_decode($oldJson, true) ?: []) : [],
            'errors'  => $errorsJson ? (json_decode($errorsJson, true) ?: []) : [],
        ], layout: 'admin');
    }

    public function generarStore(Request $req, array $params): void
    {
        $user = Auth::user();
        $eventoId = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/descuentos/generar"));
        }

        $evento = Evento::findById($eventoId);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $eventoId)) Response::forbidden();

        $cantidad   = (int) ($req->post('cantidad') ?? 0);
        $porcentaje = (float) str_replace(',', '.', (string) $req->post('porcentaje'));
        $prefijo    = trim((string) $req->post('prefijo', ''));
        $usosMaxRaw = trim((string) $req->post('usos_max', ''));
        $usosMax    = $usosMaxRaw === '' ? null : max(1, (int) $usosMaxRaw);
        $vDesde     = self::normDateTime((string) $req->post('valido_desde', ''));
        $vHasta     = self::normDateTime((string) $req->post('valido_hasta', ''));
        $nota       = trim((string) $req->post('nota', '')) ?: null;

        $errors = [];
        if ($cantidad < 1 || $cantidad > 500) {
            $errors['cantidad'][] = 'La quantitat ha de ser entre 1 i 500.';
        }
        if ($porcentaje <= 0 || $porcentaje > 100) {
            $errors['porcentaje'][] = 'El percentatge ha de ser entre 0.01 i 100.';
        }
        if ($prefijo !== '' && !preg_match('/^[A-Z0-9]{1,15}$/i', $prefijo)) {
            $errors['prefijo'][] = 'El prefix només pot contenir lletres i números (màx 15).';
        }
        if ($errors !== []) {
            Session::flash('form_old', (string) json_encode($_POST, JSON_UNESCAPED_UNICODE));
            Session::flash('form_errors', (string) json_encode($errors, JSON_UNESCAPED_UNICODE));
            Response::redirect(base_url("/admin/eventos/{$eventoId}/descuentos/generar"));
        }

        try {
            $codis = DescuentoEvento::generarLote([
                'evento_id'    => $eventoId,
                'cantidad'     => $cantidad,
                'porcentaje'   => $porcentaje,
                'prefijo'      => $prefijo,
                'usos_max'     => $usosMax,
                'valido_desde' => $vDesde,
                'valido_hasta' => $vHasta,
                'nota'         => $nota,
            ]);
        } catch (\Throwable $e) {
            Session::flash('error', 'Error generant codis: ' . $e->getMessage());
            Response::redirect(base_url("/admin/eventos/{$eventoId}/descuentos/generar"));
        }

        // Trobar el lote: usem el primer codi generat per buscar-lo
        $lote = null;
        if (count($codis) > 0) {
            $primer = DescuentoEvento::findByCodigo($eventoId, $codis[0]);
            $lote = $primer['lote'] ?? null;
        }

        Session::flash('success', "S'han generat " . count($codis) . " codis de descompte.");
        $url = base_url("/admin/eventos/{$eventoId}/descuentos");
        if ($lote) $url .= '?lote=' . urlencode($lote);
        Response::redirect($url);
    }

    public function toggle(Request $req, array $params): void
    {
        $user = Auth::user();
        $id = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();

        $d = DescuentoEvento::findById($id);
        if ($d === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, (int) $d['evento_id'])) Response::forbidden();

        $nuevoActivo = (int) $d['activo'] === 1 ? 0 : 1;
        DescuentoEvento::toggleActivo($id, $nuevoActivo);

        Session::flash('success', $nuevoActivo === 1 ? 'Codi activat.' : 'Codi desactivat.');
        Response::redirect(base_url('/admin/eventos/' . (int) $d['evento_id'] . '/descuentos'));
    }

    public function destroy(Request $req, array $params): void
    {
        $user = Auth::user();
        $id = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();

        $d = DescuentoEvento::findById($id);
        if ($d === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, (int) $d['evento_id'])) Response::forbidden();

        if ((int) $d['usos_actuales'] > 0) {
            Session::flash('error', 'No es pot esborrar un codi que ja s\'ha usat. Desactiva\'l si vols.');
            Response::redirect(base_url('/admin/eventos/' . (int) $d['evento_id'] . '/descuentos'));
        }

        DescuentoEvento::delete($id);
        Session::flash('success', 'Codi esborrat.');
        Response::redirect(base_url('/admin/eventos/' . (int) $d['evento_id'] . '/descuentos'));
    }

    /**
     * Exporta CSV dels codis d'un lote (o de tot el evento si no s'especifica).
     */
    public function export(Request $req, array $params): void
    {
        $user = Auth::user();
        $eventoId = (int) ($params['id'] ?? 0);

        $evento = Evento::findById($eventoId);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $eventoId)) Response::forbidden();

        $lote = $req->query('lote');
        $descuentos = $lote
            ? DescuentoEvento::listByLote($eventoId, $lote)
            : DescuentoEvento::listByEvento($eventoId);

        $filename = 'codis_descompte_' . preg_replace('/[^a-z0-9_-]+/i', '_', (string) $evento['slug'])
                  . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Codi', '% Descompte', 'Usos actuals', 'Usos màx', 'Vàlid des de', 'Vàlid fins', 'Actiu', 'Lote', 'Nota'], ';');
        foreach ($descuentos as $d) {
            fputcsv($out, [
                $d['codigo'],
                number_format((float)$d['porcentaje'], 2, ',', '.'),
                $d['usos_actuales'],
                $d['usos_max'] ?? '∞',
                $d['valido_desde'] ?? '',
                $d['valido_hasta'] ?? '',
                (int)$d['activo'] === 1 ? 'sí' : 'no',
                $d['lote'] ?? '',
                $d['nota'] ?? '',
            ], ';');
        }
        fclose($out);
        exit;
    }

    private static function normDateTime(string $value): ?string
    {
        if ($value === '') return null;
        $value = str_replace('T', ' ', $value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) return $value . ':00';
        return $value;
    }
}
