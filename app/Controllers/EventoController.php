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
use App\Models\Evento;
use App\Models\CampoPersonalizado;
use App\Models\CamposFijos;
use App\Models\GrupoAforo;
use App\Models\Inscrito;
use App\Models\Tarifa;
use App\Services\ImageUploader;
use App\Services\Slugger;

final class EventoController
{
    public function index(Request $req): void
    {
        $user = Auth::user();
        $showArchived = $req->query('arxivats') === '1';
        $eventos = Evento::listForUser($user->id, $user->rol, $showArchived);

        // Añadir contador de inscritos por evento (mejor en una sola query, pero esto es claro)
        foreach ($eventos as &$e) {
            $e['_inscritos'] = Evento::countInscritos((int)$e['id']);
        }
        unset($e);

        View::render('admin/eventos/index', [
            'user'         => $user,
            'eventos'      => $eventos,
            'showArchived' => $showArchived,
            'flash'        => Session::pullAllFlashes(),
            'wide'         => true, // a tot l'ample perquè les accions càpiguen en una línia
        ], layout: 'admin');
    }

    public function create(Request $req): void
    {
        $user = Auth::user();
        $oldJson    = Session::pullFlash('form_old');
        $errorsJson = Session::pullFlash('form_errors');

        View::render('admin/eventos/form', [
            'user'    => $user,
            'evento'  => null,
            'campos'  => [],
            'tarifas' => [],
            'grupos'  => [],
            'old'     => $oldJson    ? (json_decode($oldJson, true) ?: [])    : [],
            'errors'  => $errorsJson ? (json_decode($errorsJson, true) ?: []) : [],
        ], layout: 'admin');
    }

    public function store(Request $req): void
    {
        $user = Auth::user();

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada. Torna-ho a provar.');
            Response::redirect(base_url('/admin/eventos/nou'));
        }

        $data = self::extractEventoData($_POST);
        $validator = self::validateEvento($data);

        if ($validator->fails()) {
            self::flashOldAndErrors($_POST, $validator->errors());
            Response::redirect(base_url('/admin/eventos/nou'));
        }

        try {
            $imagePath = ImageUploader::handleEventImage($_FILES['imagen'] ?? []);
        } catch (\Throwable $e) {
            self::flashOldAndErrors($_POST, ['imagen' => [$e->getMessage()]]);
            Response::redirect(base_url('/admin/eventos/nou'));
        }

        $slug    = Slugger::uniqueForEvento((string)$data['titulo']);
        $tarifas = self::extractTarifasFromPost($_POST);
        $campos  = self::extractCamposFromPost($_POST);
        $grupos  = self::extractGruposFromPost($_POST);

        Database::getInstance()->transaction(
            function () use ($user, $data, $imagePath, $slug, $tarifas, $campos, $grupos): void {
                $eventoId = Evento::create([
                    'propietario_id'           => $user->id,
                    'titulo'                   => $data['titulo'],
                    'slug'                     => $slug,
                    'localizacion'             => $data['localizacion'],
                    'reglamento_url'           => $data['reglamento_url'],
                    'web_oficial_url'          => $data['web_oficial_url'],
                    'fecha_evento'             => $data['fecha_evento'],
                    'fecha_limite_inscripcion' => $data['fecha_limite_inscripcion'],
                    'aforo_maximo'             => $data['aforo_maximo'],
                    'max_participantes'        => $data['max_participantes'],
                    'tallas_sexo'              => $data['tallas_sexo'],
                    'imagen_portada'           => $imagePath,
                    'activo'                   => $data['activo'],
                    'inscripciones_abiertas'   => $data['inscripciones_abiertas'],
                    'campos_fijos'             => $data['campos_fijos'],
                    'campos_orden'             => $data['campos_orden'],
                ]);
                $map = GrupoAforo::syncForEvento($eventoId, $grupos);
                Tarifa::syncForEvento($eventoId, self::assignTarifaGroups($tarifas, $map));
                CampoPersonalizado::syncForEvento($eventoId, $campos);
            }
        );

        Session::flash('success', 'Esdeveniment creat correctament.');
        Response::redirect(base_url('/admin/eventos'));
    }

    public function edit(Request $req, array $params): void
    {
        $user = Auth::user();
        $id   = (int) ($params['id'] ?? 0);

        $evento = Evento::findById($id);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $id)) Response::forbidden();

        $campos  = CampoPersonalizado::listByEvento($id);
        $tarifas = Tarifa::listByEvento($id);
        $grupos  = GrupoAforo::listByEvento($id);
        $oldJson    = Session::pullFlash('form_old');
        $errorsJson = Session::pullFlash('form_errors');

        View::render('admin/eventos/form', [
            'user'    => $user,
            'evento'  => $evento,
            'campos'  => $campos,
            'tarifas' => $tarifas,
            'grupos'  => $grupos,
            'old'     => $oldJson    ? (json_decode($oldJson, true) ?: [])    : [],
            'errors'  => $errorsJson ? (json_decode($errorsJson, true) ?: []) : [],
        ], layout: 'admin');
    }

    public function update(Request $req, array $params): void
    {
        $user = Auth::user();
        $id   = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada. Torna-ho a provar.');
            Response::redirect(base_url("/admin/eventos/{$id}/editar"));
        }

        $evento = Evento::findById($id);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $id)) Response::forbidden();

        $data = self::extractEventoData($_POST);
        $validator = self::validateEvento($data);

        if ($validator->fails()) {
            self::flashOldAndErrors($_POST, $validator->errors());
            Response::redirect(base_url("/admin/eventos/{$id}/editar"));
        }

        $update = [
            'titulo'                   => $data['titulo'],
            'localizacion'             => $data['localizacion'],
            'reglamento_url'           => $data['reglamento_url'],
            'web_oficial_url'          => $data['web_oficial_url'],
            'fecha_evento'             => $data['fecha_evento'],
            'fecha_limite_inscripcion' => $data['fecha_limite_inscripcion'],
            'aforo_maximo'             => $data['aforo_maximo'],
            'max_participantes'        => $data['max_participantes'],
            'tallas_sexo'              => $data['tallas_sexo'],
            'activo'                   => $data['activo'],
            'inscripciones_abiertas'   => $data['inscripciones_abiertas'],
            'campos_fijos'             => $data['campos_fijos'],
            'campos_orden'             => $data['campos_orden'],
        ];

        // Si el título cambió, regenerar slug único
        if ($data['titulo'] !== $evento['titulo']) {
            $update['slug'] = Slugger::uniqueForEvento((string)$data['titulo'], $id);
        }

        // Imagen: si suben una nueva, reemplazar; si tildaron "eliminar", borrar
        try {
            $newImage = ImageUploader::handleEventImage($_FILES['imagen'] ?? []);
            if ($newImage !== null) {
                ImageUploader::deleteEventImage($evento['imagen_portada'] ?? null);
                $update['imagen_portada'] = $newImage;
            } elseif (($_POST['eliminar_imagen'] ?? '') === '1') {
                ImageUploader::deleteEventImage($evento['imagen_portada'] ?? null);
                $update['imagen_portada'] = null;
            }
        } catch (\Throwable $e) {
            self::flashOldAndErrors($_POST, ['imagen' => [$e->getMessage()]]);
            Response::redirect(base_url("/admin/eventos/{$id}/editar"));
        }

        $tarifas = self::extractTarifasFromPost($_POST);
        $campos  = self::extractCamposFromPost($_POST);
        $grupos  = self::extractGruposFromPost($_POST);

        Database::getInstance()->transaction(
            function () use ($id, $update, $tarifas, $campos, $grupos): void {
                Evento::update($id, $update);
                $map = GrupoAforo::syncForEvento($id, $grupos);
                Tarifa::syncForEvento($id, self::assignTarifaGroups($tarifas, $map));
                CampoPersonalizado::syncForEvento($id, $campos);
            }
        );

        Session::flash('success', 'Esdeveniment actualitzat.');
        Response::redirect(base_url('/admin/eventos'));
    }

    /**
     * Pantalla de KPIs del organitzador per a un esdeveniment.
     */
    public function kpis(Request $req, array $params): void
    {
        $user = Auth::user();
        $id   = (int) ($params['id'] ?? 0);

        $evento = Evento::findById($id);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $id)) Response::forbidden();

        $db = Database::getInstance();

        // ── Resumen general ────────────────────────────────────
        $estados = $db->query(
            "SELECT estado, COUNT(*) AS n FROM inscritos WHERE evento_id = ? GROUP BY estado",
            [$id]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        $totalActivas = (int) ($estados['pendiente'] ?? 0) + (int) ($estados['confirmado'] ?? 0);

        // ── Ingresos (solo confirmados) ────────────────────────
        $ingresosConfirmados = (float) $db->query(
            "SELECT COALESCE(SUM(t.precio), 0)
             FROM inscritos i JOIN tarifas_evento t ON t.id = i.tarifa_id
             WHERE i.evento_id = ? AND i.estado = 'confirmado'",
            [$id]
        )->fetchColumn();
        $ingresosPotenciales = (float) $db->query(
            "SELECT COALESCE(SUM(t.precio), 0)
             FROM inscritos i JOIN tarifas_evento t ON t.id = i.tarifa_id
             WHERE i.evento_id = ? AND i.estado IN ('pendiente', 'confirmado')",
            [$id]
        )->fetchColumn();

        // ── Por sexo ───────────────────────────────────────────
        $porSexo = $db->query(
            "SELECT sexo, COUNT(*) AS n FROM inscritos
             WHERE evento_id = ? AND estado IN ('pendiente','confirmado')
             GROUP BY sexo",
            [$id]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        // ── Por talla ──────────────────────────────────────────
        $porTalla = $db->query(
            "SELECT COALESCE(talla_camiseta, 'Sense talla') AS talla, COUNT(*) AS n
             FROM inscritos
             WHERE evento_id = ? AND estado IN ('pendiente','confirmado')
             GROUP BY talla",
            [$id]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        // ── Por tarifa ─────────────────────────────────────────
        $porTarifa = $db->query(
            "SELECT t.nombre, t.precio, COUNT(i.id) AS n
             FROM tarifas_evento t
             LEFT JOIN inscritos i ON i.tarifa_id = t.id AND i.estado IN ('pendiente','confirmado')
             WHERE t.evento_id = ?
             GROUP BY t.id, t.nombre, t.precio
             ORDER BY n DESC, t.orden ASC",
            [$id]
        )->fetchAll();

        // ── Por edad (rangos) ──────────────────────────────────
        $rangos = $db->query(
            "SELECT
                SUM(CASE WHEN edad < 18 THEN 1 ELSE 0 END)                  AS r1,
                SUM(CASE WHEN edad BETWEEN 18 AND 29 THEN 1 ELSE 0 END)     AS r2,
                SUM(CASE WHEN edad BETWEEN 30 AND 39 THEN 1 ELSE 0 END)     AS r3,
                SUM(CASE WHEN edad BETWEEN 40 AND 49 THEN 1 ELSE 0 END)     AS r4,
                SUM(CASE WHEN edad BETWEEN 50 AND 59 THEN 1 ELSE 0 END)     AS r5,
                SUM(CASE WHEN edad >= 60 THEN 1 ELSE 0 END)                 AS r6
             FROM (
                SELECT TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) AS edad
                FROM inscritos
                WHERE evento_id = ? AND estado IN ('pendiente','confirmado')
             ) x",
            [$id]
        )->fetch();

        // ── Top clubs ──────────────────────────────────────────
        $topClubs = $db->query(
            "SELECT club, COUNT(*) AS n FROM inscritos
             WHERE evento_id = ? AND estado IN ('pendiente','confirmado')
               AND club IS NOT NULL AND club <> ''
             GROUP BY club ORDER BY n DESC LIMIT 10",
            [$id]
        )->fetchAll();

        // ── Top poblacions ─────────────────────────────────────
        $topPoblaciones = $db->query(
            "SELECT poblacion, COUNT(*) AS n FROM inscritos
             WHERE evento_id = ? AND estado IN ('pendiente','confirmado')
               AND poblacion IS NOT NULL AND poblacion <> ''
             GROUP BY poblacion ORDER BY n DESC LIMIT 10",
            [$id]
        )->fetchAll();

        // ── Evolución últimos 30 días ──────────────────────────
        $evolucion = $db->query(
            "SELECT DATE(created_at) AS dia, COUNT(*) AS n FROM inscritos
             WHERE evento_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY dia ORDER BY dia ASC",
            [$id]
        )->fetchAll();

        View::render('admin/eventos/kpis', [
            'user'                => $user,
            'evento'              => $evento,
            'estados'             => $estados,
            'totalActivas'        => $totalActivas,
            'ingresosConfirmados' => $ingresosConfirmados,
            'ingresosPotenciales' => $ingresosPotenciales,
            'porSexo'             => $porSexo,
            'porTalla'            => $porTalla,
            'porTarifa'           => $porTarifa,
            'rangos'              => $rangos,
            'topClubs'            => $topClubs,
            'topPoblaciones'      => $topPoblaciones,
            'evolucion'           => $evolucion,
            'aforoMax'            => $evento['aforo_maximo'] !== null ? (int) $evento['aforo_maximo'] : null,
        ], layout: 'admin');
    }

    public function destroy(Request $req, array $params): void
    {
        $user = Auth::user();
        $id   = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();

        $evento = Evento::findById($id);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $id)) Response::forbidden();

        // Si hay inscritos, no permitir borrar
        if (Evento::countInscritos($id) > 0) {
            Session::flash('error', 'No es pot esborrar: hi ha inscrits confirmats.');
            Response::redirect(base_url('/admin/eventos'));
        }

        ImageUploader::deleteEventImage($evento['imagen_portada'] ?? null);
        Evento::delete($id);

        Session::flash('success', 'Esdeveniment esborrat.');
        Response::redirect(base_url('/admin/eventos'));
    }

    /**
     * Arxiva un esdeveniment (no l'esborra: surt del llistat actiu i del públic,
     * però es conserven les dades i els inscrits).
     */
    public function archive(Request $req, array $params): void
    {
        $user = Auth::user();
        $id   = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();

        $evento = Evento::findById($id);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $id)) Response::forbidden();

        Evento::archive($id);
        Session::flash('success', 'Esdeveniment arxivat. El trobaràs a «Arxivats».');
        Response::redirect(base_url('/admin/eventos'));
    }

    /**
     * Desarxiva un esdeveniment (torna al llistat actiu).
     */
    public function unarchive(Request $req, array $params): void
    {
        $user = Auth::user();
        $id   = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();

        $evento = Evento::findById($id);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $id)) Response::forbidden();

        Evento::unarchive($id);
        Session::flash('success', 'Esdeveniment desarxivat.');
        Response::redirect(base_url('/admin/eventos'));
    }

    /**
     * Duplica un esdeveniment (amb les seves tarifes i camps personalitzats).
     * La còpia es crea inactiva perquè l'organitzador la revisi abans de publicar-la.
     */
    public function duplicate(Request $req, array $params): void
    {
        $user = Auth::user();
        $id   = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) Response::forbidden();

        $evento = Evento::findById($id);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $id)) Response::forbidden();

        $tarifas = Tarifa::listByEvento($id);
        $campos  = CampoPersonalizado::listByEvento($id);
        $grupos  = GrupoAforo::listByEvento($id);

        $nuevoTitulo = mb_substr((string)$evento['titulo'] . ' (còpia)', 0, 255);
        $slug        = Slugger::uniqueForEvento($nuevoTitulo);

        $newId = 0;
        Database::getInstance()->transaction(
            function () use ($evento, $tarifas, $campos, $grupos, $nuevoTitulo, $slug, &$newId): void {
                $newId = Evento::create([
                    'propietario_id'           => (int) $evento['propietario_id'],
                    'titulo'                   => $nuevoTitulo,
                    'slug'                     => $slug,
                    'descripcion'              => $evento['descripcion'],
                    'localizacion'             => $evento['localizacion'] ?? null,
                    'reglamento_url'           => $evento['reglamento_url'] ?? null,
                    'web_oficial_url'          => $evento['web_oficial_url'] ?? null,
                    'fecha_evento'             => $evento['fecha_evento'],
                    'fecha_limite_inscripcion' => $evento['fecha_limite_inscripcion'],
                    'aforo_maximo'             => $evento['aforo_maximo'],
                    'max_participantes'        => $evento['max_participantes'] ?? null,
                    'tallas_sexo'              => $evento['tallas_sexo'] ?? null,
                    'imagen_portada'           => ImageUploader::copyEventImage($evento['imagen_portada'] ?? null),
                    'activo'                   => 0, // la còpia comença inactiva
                    'inscripciones_abiertas'   => (int) $evento['inscripciones_abiertas'],
                    'campos_fijos'             => $evento['campos_fijos'] ?? null,
                    'campos_orden'             => $evento['campos_orden'] ?? null,
                ]);

                // Grups d'aforament: crear-los de nou i obtenir mapa old_id -> new_id
                $gruposNuevos = array_map(static fn(array $g): array => [
                    'id'           => null,
                    'cid'          => 'old_' . (int)$g['id'],
                    'nombre'       => $g['nombre'],
                    'aforo_maximo' => (int)$g['aforo_maximo'],
                ], $grupos);
                $cidMap = GrupoAforo::syncForEvento($newId, $gruposNuevos);

                // Tarifes: sense id → s'insereixen com a noves; es remapeja el grup
                $tarifasNuevas = array_map(function (array $t) use ($cidMap): array {
                    $grupoId = null;
                    if (!empty($t['grupo_aforo_id'])) {
                        $grupoId = $cidMap['old_' . (int)$t['grupo_aforo_id']] ?? null;
                    }
                    return [
                        'id'            => null,
                        'nombre'        => $t['nombre'],
                        'descripcion'   => $t['descripcion'],
                        'precio'        => $t['precio'],
                        'aforo_maximo'  => $t['aforo_maximo'],
                        'grupo_aforo_id' => $grupoId,
                        'anio_nac_min'  => $t['anio_nac_min'] ?? null,
                        'anio_nac_max'  => $t['anio_nac_max'] ?? null,
                        'fecha_inicio'  => $t['fecha_inicio'],
                        'fecha_fin'     => $t['fecha_fin'],
                        'activo'        => (int) $t['activo'],
                    ];
                }, $tarifas);
                Tarifa::syncForEvento($newId, $tarifasNuevas);

                // Camps personalitzats
                $camposNuevos = array_map(static fn(array $c): array => [
                    'nombre_campo' => $c['nombre_campo'],
                    'etiqueta'     => $c['etiqueta'],
                    'tipo'         => $c['tipo'],
                    'opciones'     => $c['opciones'],
                    'requerido'    => (int) $c['requerido'],
                    'placeholder'  => $c['placeholder'] ?? null,
                    'ayuda'        => $c['ayuda'] ?? null,
                ], $campos);
                CampoPersonalizado::syncForEvento($newId, $camposNuevos);
            }
        );

        Session::flash('success', 'Esdeveniment duplicat. Revisa la còpia i activa-la quan estigui a punt.');
        Response::redirect(base_url("/admin/eventos/{$newId}/editar"));
    }

    // ────────────────────────────────────────────────────────
    // Helpers privados
    // ────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private static function extractEventoData(array $post): array
    {
        $aforo  = trim((string)($post['aforo_maximo'] ?? ''));
        $maxPart = trim((string)($post['max_participantes'] ?? ''));
        $fechaLimite = self::normalizeDateTime(trim((string)($post['fecha_limite_inscripcion'] ?? '')));

        return [
            'titulo'                   => trim((string)($post['titulo'] ?? '')),
            'localizacion'             => trim((string)($post['localizacion'] ?? '')) ?: null,
            'reglamento_url'           => self::normalizeUrl((string)($post['reglamento_url'] ?? '')),
            'web_oficial_url'          => self::normalizeUrl((string)($post['web_oficial_url'] ?? '')),
            'fecha_evento'             => trim((string)($post['fecha_evento'] ?? '')),
            'fecha_limite_inscripcion' => $fechaLimite,
            'aforo_maximo'             => $aforo === '' ? null : (int)$aforo,
            'max_participantes'        => $maxPart === '' ? null : max(1, (int)$maxPart),
            'tallas_sexo'              => self::extractTallasSexo($post),
            'activo'                   => isset($post['activo']) ? 1 : 0,
            'inscripciones_abiertas'   => isset($post['inscripciones_abiertas']) ? 1 : 0,
            'campos_fijos'             => CamposFijos::fromPost($post),
            'campos_orden'             => CamposFijos::ordenFromPost($post),
        ];
    }

    /**
     * Normaliza un input datetime-local ("2026-06-12T18:30") a formato MySQL ("2026-06-12 18:30:00").
     * Devuelve null si el valor está vacío.
     */
    private static function normalizeDateTime(string $value): ?string
    {
        if ($value === '') return null;
        $value = str_replace('T', ' ', $value);
        // Si solo viene HH:MM, añadir :00
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            return $value . ':00';
        }
        return $value;
    }

    /**
     * Construeix el JSON de talles per sexe a partir del POST
     * (tallas_sexo[H][] / tallas_sexo[M][]). Només es desa la restricció real:
     * si un sexe té totes (o cap) marcades → sense restricció (s'omet).
     */
    private static function extractTallasSexo(array $post): ?string
    {
        $raw = $post['tallas_sexo'] ?? null;
        if (!is_array($raw)) return null;
        $out = [];
        foreach (['H', 'M'] as $s) {
            $list = (isset($raw[$s]) && is_array($raw[$s]))
                ? array_values(array_intersect(Inscrito::TALLAS, $raw[$s]))
                : [];
            // Només és una restricció si en treu alguna (ni totes ni cap)
            if (count($list) > 0 && count($list) < count(Inscrito::TALLAS)) {
                $out[$s] = $list;
            }
        }
        return $out ? (string) json_encode($out, JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * Normaliza un any de naixement: vacío o fuera de rango plausible → null.
     */
    private static function normalizeAnioNac(mixed $value): ?int
    {
        $s = trim((string) $value);
        if ($s === '' || !ctype_digit($s)) return null;
        $y = (int) $s;
        $max = (int) date('Y');
        return ($y >= 1900 && $y <= $max) ? $y : null;
    }

    /**
     * Normaliza una URL: vacía → null; si no trae esquema, le antepone https://.
     */
    private static function normalizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') return null;
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        return mb_substr($url, 0, 500);
    }

    private static function validateEvento(array $data): Validator
    {
        $v = new Validator($data);
        $v->required('titulo')->max('titulo', 255);
        if (!empty($data['localizacion'])) {
            $v->max('localizacion', 255);
        }
        foreach (['reglamento_url', 'web_oficial_url'] as $f) {
            if (!empty($data[$f]) && !filter_var($data[$f], FILTER_VALIDATE_URL)) {
                $v->addError($f, 'Enllaç no vàlid (ha de ser una URL).');
            }
        }
        $v->required('fecha_evento')->date('fecha_evento');
        if (!empty($data['fecha_limite_inscripcion'])) {
            $v->dateTime('fecha_limite_inscripcion');
        }
        if (isset($data['aforo_maximo']) && $data['aforo_maximo'] !== null) {
            $v->integer('aforo_maximo', 1, 100000);
        }
        if (isset($data['max_participantes']) && $data['max_participantes'] !== null) {
            $v->integer('max_participantes', 1, 50);
        }
        return $v;
    }

    /**
     * Extrae las tarifas del POST.
     * Estructura esperada:
     *   tarifas[0][id]            = 5  (vacío si es nueva)
     *   tarifas[0][nombre]        = Adult
     *   tarifas[0][descripcion]   = ...
     *   tarifas[0][precio]        = 15.00
     *   tarifas[0][aforo_maximo]  = 100 (vacío = sin límite)
     *   tarifas[0][fecha_inicio]  = 2026-01-01T00:00 (opcional)
     *   tarifas[0][fecha_fin]     = 2026-06-30T23:59 (opcional)
     *   tarifas[0][activo]        = 1 (checkbox)
     *
     * @return list<array<string,mixed>>
     */
    private static function extractTarifasFromPost(array $post): array
    {
        $raw = $post['tarifas'] ?? [];
        if (!is_array($raw)) return [];

        $out = [];
        foreach ($raw as $t) {
            $nombre = trim((string)($t['nombre'] ?? ''));
            $precioRaw = trim((string)($t['precio'] ?? ''));
            if ($nombre === '' || $precioRaw === '') continue;

            $precio = (float) str_replace(',', '.', $precioRaw);
            if ($precio < 0) $precio = 0;

            $aforo = trim((string)($t['aforo_maximo'] ?? ''));
            $fIni  = self::normalizeDateTime(trim((string)($t['fecha_inicio'] ?? '')));
            $fFin  = self::normalizeDateTime(trim((string)($t['fecha_fin']    ?? '')));

            // Restricció d'any de naixement (opcional). Sanititzem a un rang plausible.
            $nacMin = self::normalizeAnioNac($t['anio_nac_min'] ?? '');
            $nacMax = self::normalizeAnioNac($t['anio_nac_max'] ?? '');
            if ($nacMin !== null && $nacMax !== null && $nacMin > $nacMax) {
                [$nacMin, $nacMax] = [$nacMax, $nacMin]; // si venen al revés, els ordenem
            }

            $out[] = [
                'id'           => !empty($t['id']) ? (int)$t['id'] : null,
                'nombre'       => mb_substr($nombre, 0, 100),
                'descripcion'  => mb_substr(trim((string)($t['descripcion'] ?? '')), 0, 500) ?: null,
                'precio'       => number_format($precio, 2, '.', ''),
                'aforo_maximo' => $aforo === '' ? null : max(1, (int)$aforo),
                'grupo_cid'    => trim((string)($t['grupo_cid'] ?? '')),
                'anio_nac_min' => $nacMin,
                'anio_nac_max' => $nacMax,
                'fecha_inicio' => $fIni,
                'fecha_fin'    => $fFin,
                'activo'       => isset($t['activo']) ? 1 : 0,
            ];
        }
        return $out;
    }

    /**
     * Extreu els grups d'aforament compartit del POST.
     * Estructura: grupos[idx][id|cid|nombre|aforo_maximo]
     * @return list<array<string,mixed>>
     */
    private static function extractGruposFromPost(array $post): array
    {
        $raw = $post['grupos'] ?? [];
        if (!is_array($raw)) return [];

        $out = [];
        foreach ($raw as $g) {
            $nombre = trim((string)($g['nombre'] ?? ''));
            $aforo  = (int)($g['aforo_maximo'] ?? 0);
            if ($nombre === '' || $aforo < 1) continue; // ignorar files incompletes

            $out[] = [
                'id'           => !empty($g['id']) ? (int)$g['id'] : null,
                'cid'          => trim((string)($g['cid'] ?? '')),
                'nombre'       => mb_substr($nombre, 0, 100),
                'aforo_maximo' => $aforo,
            ];
        }
        return $out;
    }

    /**
     * Resol el grup de cada tarifa (grupo_cid → grupo_aforo_id) amb el mapa de grups.
     *
     * @param list<array<string,mixed>> $tarifas
     * @param array<string,int>         $map      cid => grupoId
     * @return list<array<string,mixed>>
     */
    private static function assignTarifaGroups(array $tarifas, array $map): array
    {
        foreach ($tarifas as &$t) {
            $cid = (string)($t['grupo_cid'] ?? '');
            $t['grupo_aforo_id'] = ($cid !== '' && isset($map[$cid])) ? $map[$cid] : null;
        }
        unset($t);
        return $tarifas;
    }

    /**
     * Extrae los campos personalizados del POST.
     * Estructura esperada:
     *   campos[0][nombre_campo]=acepta_reglamento
     *   campos[0][etiqueta]=Accepto el reglament
     *   campos[0][tipo]=checkbox
     *   campos[0][opciones]=... (solo para select/radio/checkbox múltiple, separadas por |)
     *   campos[0][requerido]=1
     *
     * @return list<array<string,mixed>>
     */
    private static function extractCamposFromPost(array $post): array
    {
        $raw = $post['campos'] ?? [];
        if (!is_array($raw)) return [];

        $out = [];
        foreach ($raw as $c) {
            $etiqueta = trim((string)($c['etiqueta'] ?? ''));
            if ($etiqueta === '') continue; // ignorar filas vacías

            $tipo = (string)($c['tipo'] ?? 'text');
            if (!in_array($tipo, CampoPersonalizado::TIPOS_VALIDOS, true)) {
                $tipo = 'text';
            }

            $nombre = trim((string)($c['nombre_campo'] ?? ''));
            if ($nombre === '') {
                $nombre = Slugger::make($etiqueta);
            }
            $nombre = preg_replace('/[^a-z0-9_]/i', '_', strtolower($nombre)) ?? 'campo';

            $opcionesRaw = (string)($c['opciones'] ?? '');
            $opcionesJson = null;
            if (in_array($tipo, ['select', 'radio', 'checkbox'], true) && $opcionesRaw !== '') {
                $opcionesArr = preg_split('/\s*\|\s*/', $opcionesRaw) ?: [];
                $opcionesJson = CampoPersonalizado::opcionesToJson($opcionesArr);
            }

            $tarifaIds = [];
            if (!empty($c['tarifa_ids']) && is_array($c['tarifa_ids'])) {
                foreach ($c['tarifa_ids'] as $tid) {
                    $tid = (int) $tid;
                    if ($tid > 0) $tarifaIds[] = $tid;
                }
            }

            // Opcions específiques per tarifa: campos[i][opciones_tarifa][tarifaId] = "A | B | C"
            $opcTarifa = [];
            if (!empty($c['opciones_tarifa']) && is_array($c['opciones_tarifa'])) {
                foreach ($c['opciones_tarifa'] as $tid => $str) {
                    $tid = (int) $tid;
                    $str = trim((string) $str);
                    if ($tid > 0 && $str !== '') {
                        $list = preg_split('/\s*\|\s*/', $str) ?: [];
                        $list = array_values(array_filter(array_map('trim', $list), fn($o) => $o !== ''));
                        if ($list) $opcTarifa[$tid] = $list;
                    }
                }
            }

            $out[] = [
                'nombre_campo'    => substr($nombre, 0, 100),
                'etiqueta'        => substr($etiqueta, 0, 255),
                'tipo'            => $tipo,
                'opciones'        => $opcionesJson,
                'opciones_tarifa' => $opcTarifa,
                'requerido'       => !empty($c['requerido']) ? 1 : 0,
                'tarifa_ids'      => $tarifaIds,
                'placeholder'     => substr(trim((string)($c['placeholder'] ?? '')), 0, 255) ?: null,
                'ayuda'           => substr(trim((string)($c['ayuda'] ?? '')), 0, 500) ?: null,
            ];
        }

        return $out;
    }

    private static function flashOldAndErrors(array $post, array $errors): void
    {
        // No incluimos _csrf en old
        unset($post['_csrf']);
        Session::flash('form_old', (string) json_encode($post, JSON_UNESCAPED_UNICODE));
        Session::flash('form_errors', (string) json_encode($errors, JSON_UNESCAPED_UNICODE));
    }
}
