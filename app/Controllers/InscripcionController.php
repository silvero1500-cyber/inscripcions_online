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
use App\Models\Inscrito;
use App\Models\Tarifa;
use App\Services\EmailService;
use App\Services\QrService;

final class InscripcionController
{
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

        if (!PublicController::inscripcionesAbiertas($evento)) {
            Session::flash('error', 'Les inscripcions per a aquest esdeveniment estan tancades.');
            Response::redirect(base_url('/eventos/' . $slug));
        }

        $eventoId = (int) $evento['id'];

        // ── Pre-check tarifa (sin bloqueo, respuesta rápida) ──
        $tarifaId = (int) ($_POST['tarifa_id'] ?? 0);
        if ($tarifaId <= 0 || Tarifa::findDisponibleForEvento($tarifaId, $eventoId) === null) {
            Session::flash('error', 'Tria una tarifa vàlida.');
            Response::redirect(base_url('/eventos/' . $slug) . '#formulari');
        }

        // ── Validación campos estándar (segons config de l'esdeveniment) ──
        $camposFijos = CamposFijos::resolve($evento['campos_fijos'] ?? null);
        $data = self::extractCorredorData($_POST, $camposFijos);
        $v    = self::validateCorredor($data, $camposFijos);

        // ── Validación campos personalizados ──────────────────
        $valoresCampos = [];
        foreach (CampoPersonalizado::getActivosPorEvento($eventoId) as $c) {
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
                    if (!Tarifa::tieneCapacidad($tarifa)) {
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

        Session::set('ultima_inscripcion', [
            'id'         => $inscritoId,
            'evento_id'  => $eventoId,
            'tarifa_id'  => $tarifaId,
            'slug'       => $slug,
            'pay_method' => null,
        ]);

        // Si el preu final (amb descompte) és 0, no hi ha pagament: confirmar directe
        $tarifaInfo = Tarifa::findById($tarifaId);
        $precio = (float) ($tarifaInfo['precio'] ?? 0);
        $inscritoCreat = Inscrito::findById($inscritoId);
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

    public function exito(Request $req, array $params): void
    {
        $slug = (string) ($params['slug'] ?? '');
        $evento = Evento::findBySlug($slug);
        if ($evento === null) Response::notFound();

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

    // ────────────────────────────────────────────────────────
    // Helpers privados
    // ────────────────────────────────────────────────────────

    /**
     * @param array<string,string> $config  [camp => 'obligatori'|'opcional'|'ocult']
     * @return array<string, mixed>
     */
    private static function extractCorredorData(array $post, array $config): array
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
        ];

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
    private static function validateCorredor(array $data, array $config): Validator
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

        return $v;
    }
}
