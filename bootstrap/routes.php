<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\CheckinController;
use App\Controllers\DescuentosController;
use App\Controllers\EventoController;
use App\Controllers\InscritosAdminController;
use App\Controllers\InscritosImportController;
use App\Controllers\UsuariosAdminController;
use App\Controllers\PagoController;
use App\Controllers\PublicController;
use App\Controllers\InscripcionController;
use App\Controllers\LangController;

/** @var Router $router */
$router = $router ?? throw new RuntimeException('Router no inicializado');

// Selector d'idioma
$router->get ('/lang/{code}',                         [LangController::class, 'switch']);

// ── Pública ─────────────────────────────────────────────
$router->get ('/',                                    [PublicController::class,       'index']);
$router->get ('/eventos/{slug}',                      [PublicController::class,       'show']);
$router->post('/eventos/{slug}/inscriure',            [InscripcionController::class,  'store']);
$router->get ('/eventos/{slug}/gracies',              [InscripcionController::class,  'exito']);

// ── Pagament (Redsys: targeta + Bizum) ─────────────────
$router->get ('/pago/metodo',         [PagoController::class, 'metodo']);
$router->post('/pago/metodo',         [PagoController::class, 'metodoStore']);
$router->get ('/pago/iniciar',        [PagoController::class, 'iniciar']);
$router->post('/pago/notificacion',   [PagoController::class, 'notificacion']); // IPN Redsys (sense CSRF)
$router->get ('/pago/ok',             [PagoController::class, 'ok']);
$router->get ('/pago/ko',             [PagoController::class, 'ko']);

// ── Auth ────────────────────────────────────────────────
$router->get ('/admin/login',  [AuthController::class, 'showLogin']);
$router->post('/admin/login',  [AuthController::class, 'login']);
$router->post('/admin/logout', [AuthController::class, 'logout']);

// ── Panel admin (protegido) ─────────────────────────────
$auth = [[Auth::class, 'requireLogin']];

$router->get ('/admin',                              [AdminController::class, 'dashboard'], $auth);

// CRUD eventos
$router->get ('/admin/eventos',                      [EventoController::class, 'index'],   $auth);
$router->get ('/admin/eventos/nou',                  [EventoController::class, 'create'],  $auth);
$router->post('/admin/eventos',                      [EventoController::class, 'store'],   $auth);
$router->get ('/admin/eventos/{id}/editar',          [EventoController::class, 'edit'],    $auth);
$router->get ('/admin/eventos/{id}/kpis',            [EventoController::class, 'kpis'],    $auth);
$router->get ('/admin/eventos/{id}/descuentos',          [DescuentosController::class, 'index'],         $auth);
$router->get ('/admin/eventos/{id}/descuentos/generar', [DescuentosController::class, 'generar'],       $auth);
$router->post('/admin/eventos/{id}/descuentos/generar', [DescuentosController::class, 'generarStore'],  $auth);
$router->get ('/admin/eventos/{id}/descuentos/export',   [DescuentosController::class, 'export'],        $auth);
$router->post('/admin/descuentos/{id}/toggle',           [DescuentosController::class, 'toggle'],        $auth);
$router->post('/admin/descuentos/{id}/eliminar',         [DescuentosController::class, 'destroy'],       $auth);
$router->post('/admin/eventos/{id}',                 [EventoController::class, 'update'],    $auth);
$router->post('/admin/eventos/{id}/duplicar',        [EventoController::class, 'duplicate'], $auth);
$router->post('/admin/eventos/{id}/eliminar',        [EventoController::class, 'destroy'],   $auth);

// Inscrits (llistat + export CSV + import CSV)
$router->get ('/admin/inscritos',                              [InscritosAdminController::class, 'index'],   $auth);
$router->get ('/admin/inscritos/export',                       [InscritosAdminController::class, 'export'],  $auth);
$router->get ('/admin/eventos/{id}/inscritos/import',          [InscritosImportController::class, 'form'],    $auth);
$router->post('/admin/eventos/{id}/inscritos/import/preview',  [InscritosImportController::class, 'preview'], $auth);
$router->post('/admin/eventos/{id}/inscritos/import/apply',    [InscritosImportController::class, 'apply'],   $auth);

// Usuarios (només superadmin)
$superadmin = [[Auth::class, 'requireSuperadmin']];
$router->get ('/admin/usuarios',                     [UsuariosAdminController::class, 'index'],   $superadmin);
$router->get ('/admin/usuarios/nou',                 [UsuariosAdminController::class, 'create'],  $superadmin);
$router->post('/admin/usuarios',                     [UsuariosAdminController::class, 'store'],   $superadmin);
$router->get ('/admin/usuarios/{id}/editar',         [UsuariosAdminController::class, 'edit'],    $superadmin);
$router->post('/admin/usuarios/{id}',                [UsuariosAdminController::class, 'update'],  $superadmin);
$router->post('/admin/usuarios/{id}/eliminar',       [UsuariosAdminController::class, 'destroy'], $superadmin);
$router->get ('/admin/usuarios/{id}/transferir',     [UsuariosAdminController::class, 'transferForm'], $superadmin);
$router->post('/admin/usuarios/{id}/transferir',     [UsuariosAdminController::class, 'transfer'],     $superadmin);

// Check-in QR
$router->get ('/admin/checkin',                      [CheckinController::class, 'scanner'],    $auth);
$router->get ('/admin/checkin/camera-test',          [CheckinController::class, 'cameraTest'], $auth);
$router->get ('/admin/checkin/{token}',              [CheckinController::class, 'show'],       $auth);
$router->post('/admin/checkin/{token}',              [CheckinController::class, 'mark'],       $auth);
