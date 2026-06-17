<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\CheckinController;
use App\Controllers\RecollidaController;
use App\Controllers\DescuentosController;
use App\Controllers\EventoController;
use App\Controllers\InscritosAdminController;
use App\Controllers\InscritosImportController;
use App\Controllers\UsuariosAdminController;
use App\Controllers\PagoController;
use App\Controllers\PublicController;
use App\Controllers\InscripcionController;
use App\Controllers\TiendaController;
use App\Controllers\WaitingListController;
use App\Controllers\MigrationController;
use App\Controllers\TiendaAdminController;
use App\Controllers\LangController;

/** @var Router $router */
$router = $router ?? throw new RuntimeException('Router no inicializado');

// Selector d'idioma
$router->get ('/lang/{code}',                         [LangController::class, 'switch']);

// ── Pública ─────────────────────────────────────────────
$router->get ('/',                                    [PublicController::class,       'index']);
$router->get ('/eventos/{slug}',                      [PublicController::class,       'show']);
$router->post('/eventos/{slug}/inscriure',            [InscripcionController::class,  'store']);
$router->post('/eventos/{slug}/llista-espera',        [WaitingListController::class,  'store']);
$router->get ('/eventos/{slug}/gracies',              [InscripcionController::class,  'exito']);

// ── Tienda (botiga pública) ────────────────────────────
$router->get ('/tienda',                              [TiendaController::class, 'index']);
$router->get ('/tienda/cistella',                     [TiendaController::class, 'cart']);
$router->post('/tienda/cistella/afegir',              [TiendaController::class, 'cartAdd']);
$router->post('/tienda/cistella/actualitzar',         [TiendaController::class, 'cartUpdate']);
$router->post('/tienda/cistella/treure',              [TiendaController::class, 'cartRemove']);
$router->get ('/tienda/checkout',                     [TiendaController::class, 'checkout']);
$router->post('/tienda/checkout',                     [TiendaController::class, 'checkoutStore']);
$router->get ('/tienda/comanda/{codigo}',             [TiendaController::class, 'comanda']);
$router->get ('/tienda/{slug}',                       [TiendaController::class, 'show']);
$router->get ('/comprovant',                          [InscripcionController::class,  'comprovantForm']);
$router->post('/comprovant',                          [InscripcionController::class,  'comprovantSend']);
$router->get ('/comprovant/{token}',                  [InscripcionController::class,  'comprovant']);

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
// Gestió d'esdeveniments (crear/editar/duplicar/arxivar/esborrar → també tarifes
// i camps) reservada a superadmin. Els organitzadors només operen els seus events.
$superadmin = [[Auth::class, 'requireSuperadmin']];

$router->get ('/admin',                              [AdminController::class, 'dashboard'], $auth);

// CRUD eventos — el llistat el veuen tots els admins; crear/editar/etc. NOMÉS superadmin
$router->get ('/admin/eventos',                      [EventoController::class, 'index'],   $auth);
$router->get ('/admin/eventos/nou',                  [EventoController::class, 'create'],  $superadmin);
$router->post('/admin/eventos',                      [EventoController::class, 'store'],   $superadmin);
$router->get ('/admin/eventos/{id}/editar',          [EventoController::class, 'edit'],    $superadmin);
$router->get ('/admin/eventos/{id}/kpis',            [EventoController::class, 'kpis'],    $auth);
$router->get ('/admin/eventos/{id}/descuentos',          [DescuentosController::class, 'index'],         $auth);
$router->get ('/admin/eventos/{id}/descuentos/generar', [DescuentosController::class, 'generar'],       $auth);
$router->post('/admin/eventos/{id}/descuentos/generar', [DescuentosController::class, 'generarStore'],  $auth);
$router->get ('/admin/eventos/{id}/descuentos/export',   [DescuentosController::class, 'export'],        $auth);
$router->post('/admin/descuentos/{id}/toggle',           [DescuentosController::class, 'toggle'],        $auth);
$router->post('/admin/descuentos/{id}/eliminar',         [DescuentosController::class, 'destroy'],       $auth);
$router->post('/admin/eventos/{id}',                 [EventoController::class, 'update'],    $superadmin);
$router->post('/admin/eventos/{id}/duplicar',        [EventoController::class, 'duplicate'], $superadmin);
$router->post('/admin/eventos/{id}/arxivar',         [EventoController::class, 'archive'],   $superadmin);
$router->post('/admin/eventos/{id}/desarxivar',      [EventoController::class, 'unarchive'], $superadmin);
$router->post('/admin/eventos/{id}/eliminar',        [EventoController::class, 'destroy'],   $superadmin);

// Inscrits (llistat + export CSV + import CSV)
$router->get ('/admin/inscritos',                              [InscritosAdminController::class, 'index'],   $auth);
$router->get ('/admin/inscritos/export',                       [InscritosAdminController::class, 'export'],  $auth);
$router->get ('/admin/inscritos/{id}',                         [InscritosAdminController::class, 'show'],               $auth);
$router->get ('/admin/inscritos/{id}/qr',                      [InscritosAdminController::class, 'qr'],                 $auth);
$router->get ('/admin/inscritos/{id}/comprovant',              [InscritosAdminController::class, 'comprovant'],         $auth);
$router->post('/admin/inscritos/{id}/reenviar',                [InscritosAdminController::class, 'resendConfirmation'], $auth);
$router->get ('/admin/eventos/{id}/inscritos/import',          [InscritosImportController::class, 'form'],    $auth);
$router->post('/admin/eventos/{id}/inscritos/import/preview',  [InscritosImportController::class, 'preview'], $auth);
$router->post('/admin/eventos/{id}/inscritos/import/apply',    [InscritosImportController::class, 'apply'],   $auth);

// Llista d'espera
$router->get ('/admin/eventos/{id}/llista-espera',          [WaitingListController::class, 'index'],  $auth);
$router->post('/admin/eventos/{id}/llista-espera/avisar',   [WaitingListController::class, 'notify'], $auth);

// Usuarios (només superadmin)
// Migracions de base de dades (superadmin) — substitueix el runner PHP amb token
$router->get ('/admin/migracions',                   [MigrationController::class, 'index'],  $superadmin);
$router->post('/admin/migracions/aplicar',           [MigrationController::class, 'apply'],  $superadmin);

// Tienda — gestió de productes (NOMÉS superadmin)
$router->get ('/admin/tienda',                       [TiendaAdminController::class, 'index'],   $superadmin);
$router->get ('/admin/tienda/nou',                   [TiendaAdminController::class, 'create'],  $superadmin);
$router->post('/admin/tienda',                       [TiendaAdminController::class, 'store'],   $superadmin);
$router->get ('/admin/tienda/{id}/editar',           [TiendaAdminController::class, 'edit'],    $superadmin);
$router->post('/admin/tienda/{id}',                  [TiendaAdminController::class, 'update'],  $superadmin);
$router->post('/admin/tienda/{id}/eliminar',         [TiendaAdminController::class, 'destroy'], $superadmin);
$router->post('/admin/tienda/imatge/{imgId}/eliminar', [TiendaAdminController::class, 'deleteImage'], $superadmin);
$router->get ('/admin/tienda/comandes',              [TiendaAdminController::class, 'comandes'],       $superadmin);
$router->post('/admin/tienda/comandes/{id}/llest',   [TiendaAdminController::class, 'comandaLlest'],    $superadmin);
$router->post('/admin/tienda/comandes/{id}/entregat',[TiendaAdminController::class, 'comandaEntregat'], $superadmin);

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

// Recollida de dorsal (entrega de dorsal/material)
$router->get ('/admin/recollida',                    [RecollidaController::class, 'index'],    $auth);
$router->get ('/admin/recollida/escanejar',          [RecollidaController::class, 'scanner'],  $auth);
$router->post('/admin/recollida/{id}/marcar',        [RecollidaController::class, 'marcar'],   $auth);
$router->post('/admin/recollida/{id}/desfer',        [RecollidaController::class, 'desfer'],   $auth);
$router->post('/admin/recollida/{id}/dorsal',        [RecollidaController::class, 'dorsal'],   $auth);
