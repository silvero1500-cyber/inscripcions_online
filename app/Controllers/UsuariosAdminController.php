<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\Usuario;

/**
 * Gestió d'usuaris del panel. Només accessible per superadmin.
 * El middleware Auth::requireSuperadmin ja s'aplica a nivell de ruta.
 */
final class UsuariosAdminController
{
    public function index(Request $req): void
    {
        $user = Auth::user();
        View::render('admin/usuarios/index', [
            'user'     => $user,
            'usuarios' => Usuario::listAll(),
            'flash'    => Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    public function create(Request $req): void
    {
        $user = Auth::user();
        $oldJson    = Session::pullFlash('form_old');
        $errorsJson = Session::pullFlash('form_errors');

        View::render('admin/usuarios/form', [
            'user'        => $user,
            'usuario'     => null,
            'old'         => $oldJson ? (json_decode($oldJson, true) ?: []) : [],
            'errors'      => $errorsJson ? (json_decode($errorsJson, true) ?: []) : [],
            'allCarreres' => \App\Models\Carrera::all(),
            'assignedCarreraIds' => [],
        ], layout: 'admin');
    }

    public function store(Request $req): void
    {
        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada. Torna-ho a provar.');
            Response::redirect(base_url('/admin/usuarios/nou'));
        }

        $data = self::extractData($_POST, true);
        $v = self::validate($data, null);

        if ($v->fails()) {
            self::flashOldAndErrors($_POST, $v->errors());
            Response::redirect(base_url('/admin/usuarios/nou'));
        }

        $newId = Usuario::create([
            'nombre'   => $data['nombre'],
            'email'    => $data['email'],
            'password' => $data['password'],
            'rol'      => $data['rol'],
            'activo'   => $data['activo'],
        ]);

        // Organizador, recollida o export → assignar per CARRERA (totes les seves edicions)
        if (in_array($data['rol'], ['organizador', 'recollida', 'export'], true)) {
            $carreraIds = is_array($_POST['carreres'] ?? null) ? array_map('intval', $_POST['carreres']) : [];
            Usuario::setAssignedCarreras($newId, $carreraIds);
        }

        AuditLog::registrar(AuditLog::USUARI_CREAT, $data['email'] . ' (' . $data['rol'] . ')');
        Session::flash('success', 'Usuari creat correctament.');
        Response::redirect(base_url('/admin/usuarios'));
    }

    public function edit(Request $req, array $params): void
    {
        $user = Auth::user();
        $id = (int) ($params['id'] ?? 0);

        $usuario = Usuario::findById($id);
        if ($usuario === null) Response::notFound();

        $oldJson    = Session::pullFlash('form_old');
        $errorsJson = Session::pullFlash('form_errors');

        View::render('admin/usuarios/form', [
            'user'        => $user,
            'usuario'     => $usuario,
            'old'         => $oldJson ? (json_decode($oldJson, true) ?: []) : [],
            'errors'      => $errorsJson ? (json_decode($errorsJson, true) ?: []) : [],
            'allCarreres' => \App\Models\Carrera::all(),
            'assignedCarreraIds' => Usuario::assignedCarreraIds($id),
        ], layout: 'admin');
    }

    public function update(Request $req, array $params): void
    {
        $user = Auth::user();
        $id = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada.');
            Response::redirect(base_url("/admin/usuarios/{$id}/editar"));
        }

        $usuario = Usuario::findById($id);
        if ($usuario === null) Response::notFound();

        $data = self::extractData($_POST, false);
        $v = self::validate($data, $id, !empty($data['password']));

        // Salvaguarda: no quedar sense superadmin actiu
        if ($usuario['rol'] === 'superadmin' && (int)$usuario['activo'] === 1) {
            $cambiaARol = $data['rol'] !== 'superadmin';
            $cambiaAInactivo = (int)$data['activo'] !== 1;
            if (($cambiaARol || $cambiaAInactivo) && Usuario::countActiveSuperadmins() <= 1) {
                $v->addError('rol', 'No pots quitar el rol/activar d\'aquest superadmin: és l\'únic actiu.');
            }
        }

        if ($v->fails()) {
            self::flashOldAndErrors($_POST, $v->errors());
            Response::redirect(base_url("/admin/usuarios/{$id}/editar"));
        }

        $payload = [
            'nombre' => $data['nombre'],
            'email'  => $data['email'],
            'rol'    => $data['rol'],
            'activo' => $data['activo'],
        ];
        if (!empty($data['password'])) {
            $payload['password'] = $data['password'];
        }
        Usuario::update($id, $payload);

        // Guardar assignacions per CARRERA (organizador/recollida/export); superadmin no en necessita
        if (in_array($data['rol'], ['organizador', 'recollida', 'export'], true)) {
            $carreraIds = is_array($_POST['carreres'] ?? null) ? array_map('intval', $_POST['carreres']) : [];
            Usuario::setAssignedCarreras($id, $carreraIds);
        } else {
            Usuario::setAssignedEvents($id, []);
        }

        Session::flash('success', 'Usuari actualitzat.');
        Response::redirect(base_url('/admin/usuarios'));
    }

    public function transferForm(Request $req, array $params): void
    {
        $user = Auth::user();
        $id = (int) ($params['id'] ?? 0);

        $usuario = Usuario::findById($id);
        if ($usuario === null) Response::notFound();

        $owned = Usuario::ownedEvents($id);
        if (count($owned) === 0) {
            Session::flash('success', 'Aquest usuari ja no té eventos propis.');
            Response::redirect(base_url('/admin/usuarios'));
        }

        View::render('admin/usuarios/transferir', [
            'user'    => $user,
            'usuario' => $usuario,
            'owned'   => $owned,
            'targets' => Usuario::listActiveTargetsForTransfer($id),
            'flash'   => Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    public function transfer(Request $req, array $params): void
    {
        $user = Auth::user();
        $id = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada.');
            Response::redirect(base_url("/admin/usuarios/{$id}/transferir"));
        }

        $usuario = Usuario::findById($id);
        if ($usuario === null) Response::notFound();

        $targetId = (int) ($req->post('target_id') ?? 0);
        if ($targetId <= 0 || $targetId === $id) {
            Session::flash('error', 'Selecciona un usuari destí vàlid.');
            Response::redirect(base_url("/admin/usuarios/{$id}/transferir"));
        }

        $target = Usuario::findById($targetId);
        if ($target === null || (int)$target['activo'] !== 1) {
            Session::flash('error', 'L\'usuari destí no existeix o no és actiu.');
            Response::redirect(base_url("/admin/usuarios/{$id}/transferir"));
        }

        $moved = Usuario::transferOwnedEvents($id, $targetId);
        Session::flash('success', "S'han transferit {$moved} evento(s) a {$target['nombre']}.");
        Response::redirect(base_url('/admin/usuarios'));
    }

    public function destroy(Request $req, array $params): void
    {
        $user = Auth::user();
        $id = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();

        // No es pot esborrar a si mateix
        if ($id === $user->id) {
            Session::flash('error', 'No pots esborrar el teu propi compte.');
            Response::redirect(base_url('/admin/usuarios'));
        }

        $u = Usuario::findById($id);
        if ($u === null) Response::notFound();

        // No esborrar l'únic superadmin actiu
        if ($u['rol'] === 'superadmin' && (int)$u['activo'] === 1 && Usuario::countActiveSuperadmins() <= 1) {
            Session::flash('error', 'No pots esborrar l\'únic superadmin actiu.');
            Response::redirect(base_url('/admin/usuarios'));
        }

        // Comprovar que no té eventos propis
        $cnt = (int) \App\Core\Database::getInstance()->query(
            "SELECT COUNT(*) FROM eventos WHERE propietario_id = ?", [$id]
        )->fetchColumn();
        if ($cnt > 0) {
            Session::flash('error', "No es pot esborrar: aquest usuari té {$cnt} esdeveniments propis. Usa el botó 'Transferir' per reassignar-los a un altre usuari.");
            Response::redirect(base_url('/admin/usuarios'));
        }

        Usuario::delete($id);
        AuditLog::registrar(AuditLog::USUARI_ESBORRAT, ($u['email'] ?? '') . ' #' . $id);
        Session::flash('success', 'Usuari esborrat.');
        Response::redirect(base_url('/admin/usuarios'));
    }

    // ────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private static function extractData(array $post, bool $requirePassword): array
    {
        return [
            'nombre'   => trim((string)($post['nombre'] ?? '')),
            'email'    => strtolower(trim((string)($post['email'] ?? ''))),
            'password' => (string)($post['password'] ?? ''),
            'rol'      => in_array($post['rol'] ?? '', ['superadmin', 'organizador', 'recollida', 'export'], true) ? $post['rol'] : 'organizador',
            'activo'   => isset($post['activo']) ? 1 : 0,
        ];
    }

    private static function validate(array $data, ?int $idExcept, bool $passwordProvided = true): Validator
    {
        $v = new Validator($data);
        $v->required('nombre')->max('nombre', 100);
        $v->required('email')->email('email')->max('email', 255);

        if ($passwordProvided) {
            if (strlen($data['password']) < 8) {
                $v->addError('password', 'La contrasenya ha de tenir mínim 8 caràcters.');
            }
        }

        // Email únic
        if (!empty($data['email']) && Usuario::emailExists($data['email'], $idExcept)) {
            $v->addError('email', 'Aquest email ja està registrat.');
        }

        return $v;
    }

    private static function flashOldAndErrors(array $post, array $errors): void
    {
        unset($post['_csrf'], $post['password']);
        Session::flash('form_old', (string) json_encode($post, JSON_UNESCAPED_UNICODE));
        Session::flash('form_errors', (string) json_encode($errors, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Llista d'eventos disponibles per assignar (tots els actius, ordre alfabètic).
     * Inclou el contador d'inscrits per donar context al superadmin.
     * @return list<array<string,mixed>>
     */
    private static function allEventos(): array
    {
        return Database::getInstance()->query(
            "SELECT e.id, e.titulo, e.fecha_evento,
                    (SELECT COUNT(*) FROM inscritos WHERE evento_id = e.id) AS inscrits
             FROM eventos e
             WHERE e.activo = 1
             ORDER BY e.titulo ASC"
        )->fetchAll();
    }
}
