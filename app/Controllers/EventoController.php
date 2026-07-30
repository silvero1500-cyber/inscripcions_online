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
            'user'     => $user,
            'evento'   => null,
            'campos'   => [],
            'tarifas'  => [],
            'grupos'   => [],
            'carreres' => \App\Models\Carrera::allActivas(),
            'old'      => $oldJson    ? (json_decode($oldJson, true) ?: [])    : [],
            'errors'   => $errorsJson ? (json_decode($errorsJson, true) ?: []) : [],
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
            $imagePath  = ImageUploader::handleEventImage($_FILES['imagen'] ?? []);
            $bannerPath = ImageUploader::handleEventImage($_FILES['banner'] ?? [], 'banners');
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
                    'carrera_id'               => $data['carrera_id'],
                    'anio_edicion'             => $data['anio_edicion'],
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
                    'franjas_config'           => $data['franjas_config'],
                    'imagen_portada'           => $imagePath,
                    'banner_superior'          => $bannerPath,
                    'activo'                   => $data['activo'],
                    'inscripciones_abiertas'   => $data['inscripciones_abiertas'],
                    'descuentos_activos'       => $data['descuentos_activos'],
                    'incidencias_activo'       => $data['incidencias_activo'],
                    'form_password'            => $data['form_password'],
                    'campos_fijos'             => $data['campos_fijos'],
                    'campos_orden'             => null,
                ]);
                $map = GrupoAforo::syncForEvento($eventoId, $grupos);
                Tarifa::syncForEvento($eventoId, self::assignTarifaGroups($tarifas, $map));
                $createdIds = CampoPersonalizado::syncForEvento($eventoId, $campos);
                // L'ordre final inclou els camps personalitzats (mapeja __CUSTOM__ → campo_<id>)
                Database::getInstance()->update('eventos',
                    ['campos_orden' => CamposFijos::buildCamposOrden($_POST, $createdIds)],
                    ['id' => $eventoId]
                );
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
            'user'     => $user,
            'evento'   => $evento,
            'campos'   => $campos,
            'tarifas'  => $tarifas,
            'grupos'   => $grupos,
            'carreres' => \App\Models\Carrera::allActivas(),
            'old'      => $oldJson    ? (json_decode($oldJson, true) ?: [])    : [],
            'errors'   => $errorsJson ? (json_decode($errorsJson, true) ?: []) : [],
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
            'carrera_id'               => $data['carrera_id'],
            'anio_edicion'             => $data['anio_edicion'],
            'localizacion'             => $data['localizacion'],
            'reglamento_url'           => $data['reglamento_url'],
            'web_oficial_url'          => $data['web_oficial_url'],
            'fecha_evento'             => $data['fecha_evento'],
            'fecha_limite_inscripcion' => $data['fecha_limite_inscripcion'],
            'aforo_maximo'             => $data['aforo_maximo'],
            'max_participantes'        => $data['max_participantes'],
            'tallas_sexo'              => $data['tallas_sexo'],
            'franjas_config'           => $data['franjas_config'],
            'activo'                   => $data['activo'],
            'inscripciones_abiertas'   => $data['inscripciones_abiertas'],
            'descuentos_activos'       => $data['descuentos_activos'],
            'incidencias_activo'       => $data['incidencias_activo'],
            'form_password'            => $data['form_password'],
            'campos_fijos'             => $data['campos_fijos'],
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
            // Banner superior: mateix patró
            $newBanner = ImageUploader::handleEventImage($_FILES['banner'] ?? [], 'banners');
            if ($newBanner !== null) {
                ImageUploader::deleteEventImage($evento['banner_superior'] ?? null);
                $update['banner_superior'] = $newBanner;
            } elseif (($_POST['eliminar_banner'] ?? '') === '1') {
                ImageUploader::deleteEventImage($evento['banner_superior'] ?? null);
                $update['banner_superior'] = null;
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
                $createdIds = CampoPersonalizado::syncForEvento($id, $campos);
                Database::getInstance()->update('eventos',
                    ['campos_orden' => CamposFijos::buildCamposOrden($_POST, $createdIds)],
                    ['id' => $id]
                );
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

        // Caduca pendents abandonats abans de calcular els KPIs (sense cron)
        \App\Models\Inscrito::expirarPendientes($id);

        $db = Database::getInstance();

        // ── Resumen general ────────────────────────────────────
        $estados = $db->query(
            "SELECT estado, COUNT(*) AS n FROM inscritos WHERE evento_id = ? GROUP BY estado",
            [$id]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        $totalActivas = (int) ($estados['pendiente'] ?? 0) + (int) ($estados['confirmado'] ?? 0);

        // ── Ingresos (solo confirmados) ────────────────────────
        // Es fa servir el preu REALMENT aplicat (precio_aplicado) quan hi és;
        // si no (registres antics sense aquest valor), el preu base de la tarifa.
        // Així els events amb dades importades (preu pagat per persona) i tarifa 0
        // mostren l'ingrés real.
        $ingresosConfirmados = (float) $db->query(
            "SELECT COALESCE(SUM(COALESCE(i.precio_aplicado, t.precio)), 0)
             FROM inscritos i JOIN tarifas_evento t ON t.id = i.tarifa_id
             WHERE i.evento_id = ? AND i.estado = 'confirmado'",
            [$id]
        )->fetchColumn();
        $ingresosPotenciales = (float) $db->query(
            "SELECT COALESCE(SUM(COALESCE(i.precio_aplicado, t.precio)), 0)
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

        // ── Xip groc (propi o de cessió) ────────────────────────
        $porChip = $db->query(
            "SELECT COALESCE(chip_groc, 'sense_resposta') AS chip, COUNT(*) AS n
             FROM inscritos
             WHERE evento_id = ? AND estado IN ('pendiente','confirmado')
             GROUP BY chip",
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

        // ── Per mètode de pagament (TPV vs Manual/Import) ──────
        // TPV = té un pagament Redsys completat; si no, es considera manual/importat.
        $porPagament = $db->query(
            "SELECT CASE WHEN EXISTS (
                        SELECT 1 FROM pagos p WHERE p.inscrito_id = i.id AND p.estado = 'completado'
                    ) THEN 'TPV' ELSE 'Manual' END AS metode,
                    COUNT(*) AS n
             FROM inscritos i
             WHERE i.evento_id = ? AND i.estado IN ('pendiente','confirmado')
             GROUP BY metode",
            [$id]
        )->fetchAll(\PDO::FETCH_KEY_PAIR);

        // ── Per franja d'edat × categoria (tarifa) — per al drill-down ──
        $edatCategoriaRows = $db->query(
            "SELECT t.nombre AS categoria,
                    CASE
                        WHEN edad < 18 THEN '<18'
                        WHEN edad BETWEEN 18 AND 29 THEN '18-29'
                        WHEN edad BETWEEN 30 AND 39 THEN '30-39'
                        WHEN edad BETWEEN 40 AND 49 THEN '40-49'
                        WHEN edad BETWEEN 50 AND 59 THEN '50-59'
                        WHEN edad >= 60 THEN '60+'
                        ELSE 'Sense data'
                    END AS franja,
                    COUNT(*) AS n
             FROM (
                SELECT i.tarifa_id, TIMESTAMPDIFF(YEAR, i.fecha_nacimiento, CURDATE()) AS edad
                FROM inscritos i
                WHERE i.evento_id = ? AND i.estado IN ('pendiente','confirmado')
             ) x
             JOIN tarifas_evento t ON t.id = x.tarifa_id
             GROUP BY t.nombre, franja",
            [$id]
        )->fetchAll();

        // ── Venda per trams (preus early-bird) ─────────────────
        // Inscrits agrupats pel preu que se'ls va aplicar (precio_aplicado);
        // si la tarifa no té trams definits, no apareix com a tram.
        $vendaTrams = $db->query(
            "SELECT t.nombre AS tarifa, i.precio_aplicado AS preu, COUNT(*) AS n
             FROM inscritos i
             JOIN tarifas_evento t ON t.id = i.tarifa_id
             WHERE i.evento_id = ? AND i.estado IN ('pendiente','confirmado')
               AND i.precio_aplicado IS NOT NULL
               AND EXISTS (SELECT 1 FROM tarifa_precios tp WHERE tp.tarifa_id = t.id)
             GROUP BY t.nombre, i.precio_aplicado
             ORDER BY t.nombre, i.precio_aplicado",
            [$id]
        )->fetchAll();

        // ── Evolución últimos 90 días (total) ──────────────────
        $evolucion = $db->query(
            "SELECT DATE(created_at) AS dia, COUNT(*) AS n FROM inscritos
             WHERE evento_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
             GROUP BY dia ORDER BY dia ASC",
            [$id]
        )->fetchAll();

        // ── Evolución 90 días per categoria (tarifa) ───────────
        $evolucionCat = $db->query(
            "SELECT DATE(i.created_at) AS dia, t.nombre AS categoria, COUNT(*) AS n
             FROM inscritos i JOIN tarifas_evento t ON t.id = i.tarifa_id
             WHERE i.evento_id = ? AND i.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
             GROUP BY dia, t.nombre ORDER BY dia ASC",
            [$id]
        )->fetchAll();

        // ── Creixement diari mitjà (% sobre el total acumulat, darrers 30 dies amb activitat) ──
        // Mitjana de (inscrits del dia / acumulat fins el dia anterior) sobre els dies amb inscrits.
        $creixementMitja = null;
        if (count($evolucion) > 1) {
            $acum = 0; $ratios = [];
            foreach ($evolucion as $row) {
                $n = (int) $row['n'];
                if ($acum > 0 && $n > 0) $ratios[] = $n / $acum;
                $acum += $n;
            }
            if ($ratios) $creixementMitja = round(array_sum($ratios) / count($ratios) * 100, 1);
        }

        // ── Per talla × sexe (apilat unisex/dona) ──────────────
        // Unisex = Home + No binari; Dona = M.
        $tallaSexoRows = $db->query(
            "SELECT COALESCE(talla_camiseta, 'Sense talla') AS talla,
                    CASE WHEN sexo = 'M' THEN 'Dona' ELSE 'Unisex' END AS grup,
                    COUNT(*) AS n
             FROM inscritos
             WHERE evento_id = ? AND estado IN ('pendiente','confirmado')
             GROUP BY talla, grup",
            [$id]
        )->fetchAll();

        // ── Top clubs (amb % sobre total) ──────────────────────
        $topClubs = $db->query(
            "SELECT club, COUNT(*) AS n FROM inscritos
             WHERE evento_id = ? AND estado IN ('pendiente','confirmado')
               AND club IS NOT NULL AND club <> ''
             GROUP BY club ORDER BY n DESC LIMIT 10",
            [$id]
        )->fetchAll();

        // ── Top poblacions (amb % sobre total) ─────────────────
        $topPoblaciones = $db->query(
            "SELECT poblacion, COUNT(*) AS n FROM inscritos
             WHERE evento_id = ? AND estado IN ('pendiente','confirmado')
               AND poblacion IS NOT NULL AND poblacion <> ''
             GROUP BY poblacion ORDER BY n DESC LIMIT 10",
            [$id]
        )->fetchAll();

        // ── Inscrits últims 7 dies ─────────────────────────────
        $darrers7 = (int) $db->query(
            "SELECT COUNT(*) FROM inscritos
             WHERE evento_id = ? AND estado IN ('pendiente','confirmado')
               AND created_at >= (NOW() - INTERVAL 7 DAY)",
            [$id]
        )->fetchColumn();

        // ── Comparativa amb l'edició anterior (mateix punt del compte enrere) ──
        $comparativa = $this->edicioAnterior($evento, $totalActivas, $darrers7);

        // ── Evolució comparada per dies des de l'inici d'inscripcions (edició actual vs anterior) ──
        $evolucioEdicions = $this->evolucioPerEdicions($evento);

        // ── Ingressos per edició (comparativa entre anys de la mateixa carrera) ──
        $ingressosEdicions = $this->ingressosPerEdicions($evento);

        // ── Fidelització: repetidors per edició (match per email/DNI amb anys anteriors) ──
        $fidelitzacio = $this->fidelitzacioPerEdicions($evento);

        View::render('admin/eventos/kpis', [
            'user'                => $user,
            'evento'              => $evento,
            'estados'             => $estados,
            'totalActivas'        => $totalActivas,
            'ingresosConfirmados' => $ingresosConfirmados,
            'ingresosPotenciales' => $ingresosPotenciales,
            'porSexo'             => $porSexo,
            'porChip'             => $porChip,
            'porTarifa'           => $porTarifa,
            'rangos'              => $rangos,
            'porPagament'         => $porPagament,
            'edatCategoria'       => $edatCategoriaRows,
            'vendaTrams'          => $vendaTrams,
            'evolucion'           => $evolucion,
            'evolucionCat'        => $evolucionCat,
            'creixementMitja'     => $creixementMitja,
            'tallaSexo'           => $tallaSexoRows,
            'topClubs'            => $topClubs,
            'topPoblaciones'      => $topPoblaciones,
            'darrers7'            => $darrers7,
            'comparativa'         => $comparativa,
            'evolucioEdicions'    => $evolucioEdicions,
            'ingressosEdicions'   => $ingressosEdicions,
            'fidelitzacio'        => $fidelitzacio,
            'kpisHidden'          => (function () use ($user) {
                $raw = \App\Models\UserPref::get($user->id, 'kpis_hidden');
                $arr = $raw ? (json_decode($raw, true) ?: []) : [];
                return is_array($arr) ? array_values(array_filter(array_map('strval', $arr))) : [];
            })(),
            'aforoMax'            => $evento['aforo_maximo'] !== null ? (int) $evento['aforo_maximo'] : null,
            'connexions'          => $this->connexionsPerHores($evento),
            'inscDia'             => $this->inscripcionsPerDia($evento),
            'origen'              => $this->origenVisites($evento),
        ], layout: 'admin');
    }

    /**
     * Origen (font de trànsit) de les visites al formulari, últims 30 dies.
     * Cru [{d,f,n}]; la vista agrega per font segons el període triat.
     * @return list<array{d:string,f:string,n:int}>
     */
    private function origenVisites(array $evento): array
    {
        try {
            $rows = Database::getInstance()->query(
                "SELECT fecha, font, n FROM visitas_origen
                 WHERE evento_id = ? AND fecha >= (CURDATE() - INTERVAL 30 DAY)
                 ORDER BY fecha ASC",
                [(int) $evento['id']]
            )->fetchAll();
        } catch (\Throwable $e) {
            return []; // taula pot no existir encara (migració 049)
        }

        return array_map(fn($r) => [
            'd' => (string) $r['fecha'],
            'f' => (string) $r['font'],
            'n' => (int) $r['n'],
        ], $rows);
    }

    /**
     * Inscripcions creades per dia (últims 30 dies), per calcular el % de
     * conversió (inscripcions / visites) a la vista de KPIs.
     * @return list<array{d:string,n:int}>
     */
    private function inscripcionsPerDia(array $evento): array
    {
        $rows = Database::getInstance()->query(
            "SELECT DATE(created_at) AS d, COUNT(*) AS n
             FROM inscritos
             WHERE evento_id = ? AND estado IN ('pendiente','confirmado')
               AND created_at >= (CURDATE() - INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY d ASC",
            [(int) $evento['id']]
        )->fetchAll();

        return array_map(fn($r) => [
            'd' => (string) $r['d'],
            'n' => (int) $r['n'],
        ], $rows);
    }

    /**
     * Connexions (visites) al formulari públic per dia i hora, dels últims 30
     * dies. Es retorna en cru [{d,h,n}] i la vista construeix el mapa de calor
     * dia-de-la-setmana × hora amb el selector de període (7/15/30 dies).
     * @return list<array{d:string,h:int,n:int}>
     */
    private function connexionsPerHores(array $evento): array
    {
        try {
            $rows = Database::getInstance()->query(
                "SELECT fecha, hora, n FROM visitas_horas
                 WHERE evento_id = ? AND fecha >= (CURDATE() - INTERVAL 30 DAY)
                 ORDER BY fecha ASC, hora ASC",
                [(int) $evento['id']]
            )->fetchAll();
        } catch (\Throwable $e) {
            // La taula pot no existir encara (migració 048 no aplicada) → sense dades
            return [];
        }

        return array_map(fn($r) => [
            'd' => (string) $r['fecha'],
            'h' => (int) $r['hora'],
            'n' => (int) $r['n'],
        ], $rows);
    }

    /**
     * Desa la preferència de KPIs amagats per l'usuari (personalització del panell).
     * Rep {hidden: ["id1","id2",...]}. Resposta JSON.
     */
    public function saveKpisPrefs(Request $req): void
    {
        $user = Auth::user();
        if (!Csrf::verify($req->post('_csrf'))) Response::json(['ok' => false], 419);

        $hidden = $req->post('hidden');
        if (is_string($hidden)) $hidden = json_decode($hidden, true);
        if (!is_array($hidden)) $hidden = [];
        // sanititzar: només ids curts alfanumèrics/guions
        $clean = [];
        foreach ($hidden as $h) {
            $h = preg_replace('/[^a-z0-9_-]/i', '', (string) $h);
            if ($h !== '') $clean[] = $h;
        }
        $clean = array_values(array_unique($clean));

        \App\Models\UserPref::set($user->id, 'kpis_hidden', (string) json_encode($clean));
        Response::json(['ok' => true]);
    }

    /**
     * Ingressos confirmats de CADA edició de la mateixa carrera (per comparar anys).
     * Fa servir el preu realment aplicat quan hi és; si no, el de la tarifa.
     * @return list<array{any:int, total:float}>
     */
    private function ingressosPerEdicions(array $evento): array
    {
        $carreraId = $evento['carrera_id'] ?? null;
        if (empty($carreraId)) return [];

        return array_map(
            fn($r) => ['any' => (int) $r['anio_edicion'], 'total' => (float) $r['total']],
            Database::getInstance()->query(
                "SELECT e.anio_edicion,
                        COALESCE(SUM(COALESCE(i.precio_aplicado, t.precio)), 0) AS total
                 FROM eventos e
                 LEFT JOIN inscritos i ON i.evento_id = e.id AND i.estado = 'confirmado'
                 LEFT JOIN tarifas_evento t ON t.id = i.tarifa_id
                 WHERE e.carrera_id = ? AND e.anio_edicion IS NOT NULL
                 GROUP BY e.id, e.anio_edicion
                 ORDER BY e.anio_edicion ASC",
                [(int) $carreraId]
            )->fetchAll()
        );
    }

    /**
     * Fidelització: per cada edició de la carrera, quants inscrits ja havien
     * participat en ALGUNA edició anterior (match per email o DNI). Es calcula
     * en PHP carregant una sola vegada (any, email, dni) de tota la carrera.
     *
     * @return array{
     *   edicions: list<array{any:int, total:int, repetidors:int, pct:float}>,
     *   actual: array{any:int, total:int, repetidors:int, novells:int, pct:float}|null
     * }|null
     */
    private function fidelitzacioPerEdicions(array $evento): ?array
    {
        $carreraId = $evento['carrera_id'] ?? null;
        if (empty($carreraId)) return null;

        $rows = Database::getInstance()->query(
            "SELECT e.anio_edicion AS any, LOWER(TRIM(i.email)) AS email, UPPER(TRIM(COALESCE(i.dni,''))) AS dni
             FROM inscritos i JOIN eventos e ON e.id = i.evento_id
             WHERE e.carrera_id = ? AND e.anio_edicion IS NOT NULL
               AND i.estado IN ('pendiente','confirmado')",
            [(int) $carreraId]
        )->fetchAll();
        if (count($rows) === 0) return null;

        // Primer any en què apareix cada email / dni
        $emailMin = [];
        $dniMin = [];
        foreach ($rows as $r) {
            $any = (int) $r['any'];
            if ($r['email'] !== '') {
                $emailMin[$r['email']] = isset($emailMin[$r['email']]) ? min($emailMin[$r['email']], $any) : $any;
            }
            if ($r['dni'] !== '') {
                $dniMin[$r['dni']] = isset($dniMin[$r['dni']]) ? min($dniMin[$r['dni']], $any) : $any;
            }
        }

        // Per edició: total i repetidors (email o dni vistos en un any anterior)
        $agg = []; // any => [total, rep]
        foreach ($rows as $r) {
            $any = (int) $r['any'];
            if (!isset($agg[$any])) $agg[$any] = ['total' => 0, 'rep' => 0];
            $agg[$any]['total']++;
            $repeteix = false;
            if ($r['email'] !== '' && isset($emailMin[$r['email']]) && $emailMin[$r['email']] < $any) $repeteix = true;
            if (!$repeteix && $r['dni'] !== '' && isset($dniMin[$r['dni']]) && $dniMin[$r['dni']] < $any) $repeteix = true;
            if ($repeteix) $agg[$any]['rep']++;
        }
        ksort($agg);

        $edicions = [];
        foreach ($agg as $any => $a) {
            $pct = $a['total'] > 0 ? round($a['rep'] * 100 / $a['total'], 1) : 0.0;
            $edicions[] = ['any' => (int) $any, 'total' => (int) $a['total'], 'repetidors' => (int) $a['rep'], 'pct' => $pct];
        }

        // Resum de l'edició que s'està veient
        $anyActual = (int) ($evento['anio_edicion'] ?? 0);
        $actual = null;
        foreach ($edicions as $ed) {
            if ($ed['any'] === $anyActual) {
                $actual = [
                    'any'        => $ed['any'],
                    'total'      => $ed['total'],
                    'repetidors' => $ed['repetidors'],
                    'novells'    => $ed['total'] - $ed['repetidors'],
                    'pct'        => $ed['pct'],
                ];
                break;
            }
        }

        return ['edicions' => $edicions, 'actual' => $actual];
    }

    /**
     * Comparativa amb l'edició anterior de la mateixa carrera (anio_edicion − 1),
     * mesurada al MATEIX punt del compte enrere (mateixos dies abans de la cursa).
     * Retorna null si l'edició no pertany a cap carrera o no hi ha edició anterior.
     *
     * @return array{
     *   anyAnterior:int, inscritsMateixPunt:int, totalFinal:int, darrers7Ant:int,
     *   deltaTotal:int, delta7:int, previsio:?int
     * }|null
     */
    private function edicioAnterior(array $evento, int $totalActivas, int $darrers7): ?array
    {
        $carreraId = $evento['carrera_id'] ?? null;
        $anio      = $evento['anio_edicion'] ?? null;
        if (empty($carreraId) || empty($anio)) return null;

        $db = Database::getInstance();
        $ant = $db->query(
            "SELECT id, fecha_evento, anio_edicion FROM eventos
             WHERE carrera_id = ? AND anio_edicion = ? LIMIT 1",
            [(int) $carreraId, (int) $anio - 1]
        )->fetch();
        if (!$ant) return null;

        $antId = (int) $ant['id'];

        // Dies que falten ara per a la cursa actual (mínim 0)
        $diesFalten = (int) $db->query(
            "SELECT GREATEST(0, DATEDIFF(?, CURDATE()))",
            [(string) $evento['fecha_evento']]
        )->fetchColumn();

        // Data tall equivalent dins l'edició anterior (la seva data − dies que falten)
        $tall = $db->query(
            "SELECT DATE_SUB(?, INTERVAL ? DAY)",
            [(string) $ant['fecha_evento'], $diesFalten]
        )->fetchColumn();

        $inscritsMateixPunt = (int) $db->query(
            "SELECT COUNT(*) FROM inscritos
             WHERE evento_id = ? AND estado IN ('pendiente','confirmado')
               AND created_at <= ?",
            [$antId, $tall . ' 23:59:59']
        )->fetchColumn();

        $totalFinal = (int) $db->query(
            "SELECT COUNT(*) FROM inscritos
             WHERE evento_id = ? AND estado IN ('pendiente','confirmado')",
            [$antId]
        )->fetchColumn();

        // Mateixos 7 dies abans de la cursa anterior (al voltant del punt equivalent)
        $darrers7Ant = (int) $db->query(
            "SELECT COUNT(*) FROM inscritos
             WHERE evento_id = ? AND estado IN ('pendiente','confirmado')
               AND created_at > DATE_SUB(?, INTERVAL 7 DAY)
               AND created_at <= ?",
            [$antId, $tall . ' 23:59:59', $tall . ' 23:59:59']
        )->fetchColumn();

        // Previsió d'inscrits aplicant el creixement de l'edició anterior
        $previsio = null;
        if ($inscritsMateixPunt > 0) {
            $factor = $totalFinal / $inscritsMateixPunt;
            $previsio = (int) round($totalActivas * $factor);
        }

        return [
            'anyAnterior'        => (int) $ant['anio_edicion'],
            'inscritsMateixPunt' => $inscritsMateixPunt,
            'totalFinal'         => $totalFinal,
            'darrers7Ant'        => $darrers7Ant,
            'deltaTotal'         => $totalActivas - $inscritsMateixPunt,
            'delta7'             => $darrers7 - $darrers7Ant,
            'previsio'           => $previsio,
        ];
    }

    /**
     * Evolució d'inscripcions els últims 90 dies ABANS de la cursa (compte enrere),
     * per a l'edició actual i l'edició anterior de la mateixa carrera (si n'hi ha).
     * El "dia 90" és 90 dies abans de la cursa, el "dia 0" és el dia de la cursa.
     *
     * @return array{
     *   anyActual:int, anyAnterior:?int, diesFalten:?int,
     *   actual: list<array{dia:int, n:int}>,
     *   anterior: list<array{dia:int, n:int}>
     * }|null
     */
    private function evolucioPerEdicions(array $evento): ?array
    {
        $db = Database::getInstance();
        $id = (int) $evento['id'];

        $actual = $this->evolucioPreviaCursa($db, $id, (string) $evento['fecha_evento']);
        if ($actual === null) return null;

        // Dies que falten avui per a la cursa (marca "avui" al gràfic); null si ja ha passat
        $diesFalten = (int) $db->query(
            "SELECT DATEDIFF(?, CURDATE())", [(string) $evento['fecha_evento']]
        )->fetchColumn();
        if ($diesFalten < 0 || $diesFalten > 90) $diesFalten = null;

        $carreraId = $evento['carrera_id'] ?? null;
        $anio      = $evento['anio_edicion'] ?? null;

        // Totes les edicions de la mateixa carrera (amb inscrits), no només l'anterior.
        $edicions = [];
        if (!empty($carreraId)) {
            $rows = $db->query(
                "SELECT id, anio_edicion, fecha_evento FROM eventos
                 WHERE carrera_id = ? AND anio_edicion IS NOT NULL
                 ORDER BY anio_edicion ASC",
                [(int) $carreraId]
            )->fetchAll();
            foreach ($rows as $r) {
                $punts = ((int) $r['id'] === $id)
                    ? $actual
                    : $this->evolucioPreviaCursa($db, (int) $r['id'], (string) $r['fecha_evento']);
                if ($punts === null) continue; // edició sense inscrits
                $edicions[] = ['any' => (int) $r['anio_edicion'], 'punts' => $punts];
            }
        } else {
            $edicions[] = ['any' => (int) ($anio ?? 0), 'punts' => $actual];
        }

        return [
            'anyActual'  => (int) ($anio ?? 0),
            'diesFalten' => $diesFalten,
            'edicions'   => $edicions,
        ];
    }

    /**
     * Recompte d'inscripcions agrupat per "dies abans de la cursa" (0 = dia de la cursa,
     * 90 = 90 dies abans), limitat a la finestra [0, 90]. Retorna null si l'esdeveniment
     * no té cap inscripció.
     *
     * @return list<array{dia:int, n:int}>|null
     */
    private function evolucioPreviaCursa(Database $db, int $eventoId, string $fechaEvento): ?array
    {
        $hi = (int) $db->query(
            "SELECT COUNT(*) FROM inscritos WHERE evento_id = ?", [$eventoId]
        )->fetchColumn();
        if ($hi === 0) return null;

        $rows = $db->query(
            "SELECT DATEDIFF(DATE(?), DATE(created_at)) AS diaN, COUNT(*) AS n
             FROM inscritos
             WHERE evento_id = ?
             GROUP BY diaN
             HAVING diaN BETWEEN 0 AND 90
             ORDER BY diaN DESC",
            [$fechaEvento, $eventoId]
        )->fetchAll();

        return array_map(fn($r) => ['dia' => (int) $r['diaN'], 'n' => (int) $r['n']], $rows);
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
        ImageUploader::deleteEventImage($evento['banner_superior'] ?? null);
        Evento::delete($id);
        AuditLog::registrar(AuditLog::EVENTO_ESBORRAT, ($evento['titulo'] ?? '') . ' #' . $id);

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
        AuditLog::registrar(AuditLog::EVENTO_ARXIVAT, ($evento['titulo'] ?? '') . ' #' . $id);
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
                    'carrera_id'               => $evento['carrera_id'] ?? null,
                    'anio_edicion'             => !empty($evento['anio_edicion']) ? (int) $evento['anio_edicion'] + 1 : null,
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
                    'franjas_config'           => $evento['franjas_config'] ?? null,
                    'imagen_portada'           => ImageUploader::copyEventImage($evento['imagen_portada'] ?? null),
                    'banner_superior'          => ImageUploader::copyEventImage($evento['banner_superior'] ?? null),
                    'activo'                   => 0, // la còpia comença inactiva
                    'inscripciones_abiertas'   => (int) $evento['inscripciones_abiertas'],
                    'descuentos_activos'       => (int) ($evento['descuentos_activos'] ?? 1),
                    'incidencias_activo'       => (int) ($evento['incidencias_activo'] ?? 0),
                    'form_password'            => $evento['form_password'] ?? null,
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
                    // Copiar els trams de preu de la tarifa original
                    $tramos = array_map(fn($tr) => [
                        'fecha_hasta' => $tr['fecha_hasta'] ?? null,
                        'precio'      => (float) $tr['precio'],
                    ], Tarifa::tramos((int) $t['id']));

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
                        'tramos'        => $tramos,
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
        $fechaEvento = trim((string)($post['fecha_evento'] ?? ''));

        $carreraId = trim((string)($post['carrera_id'] ?? ''));
        $anioEd    = trim((string)($post['anio_edicion'] ?? ''));
        // Si no s'indica l'any de l'edició, s'agafa el de la data de l'esdeveniment
        if ($anioEd === '' && preg_match('/^(\d{4})-/', $fechaEvento, $m)) {
            $anioEd = $m[1];
        }

        return [
            'titulo'                   => trim((string)($post['titulo'] ?? '')),
            'carrera_id'               => $carreraId === '' ? null : (int)$carreraId,
            'anio_edicion'             => $anioEd === '' ? null : (int)$anioEd,
            'localizacion'             => trim((string)($post['localizacion'] ?? '')) ?: null,
            'reglamento_url'           => self::normalizeUrl((string)($post['reglamento_url'] ?? '')),
            'web_oficial_url'          => self::normalizeUrl((string)($post['web_oficial_url'] ?? '')),
            'fecha_evento'             => $fechaEvento,
            'fecha_limite_inscripcion' => $fechaLimite,
            'aforo_maximo'             => $aforo === '' ? null : (int)$aforo,
            'max_participantes'        => $maxPart === '' ? null : max(1, (int)$maxPart),
            'tallas_sexo'              => self::extractTallasSexo($post),
            'franjas_config'           => self::extractFranjasConfig($post),
            'activo'                   => isset($post['activo']) ? 1 : 0,
            'inscripciones_abiertas'   => isset($post['inscripciones_abiertas']) ? 1 : 0,
            'descuentos_activos'       => isset($post['descuentos_activos']) ? 1 : 0,
            'incidencias_activo'       => isset($post['incidencias_activo']) ? 1 : 0,
            'form_password'            => trim((string)($post['form_password'] ?? '')) ?: null,
            'campos_fijos'             => CamposFijos::fromPost($post),
        ];
    }

    /**
     * Franges de temps (camp fix franja_temps) des del POST:
     * franjas[i][label] + franjas[i][calaix] → JSON [{label, calaix}].
     * Descarta files sense label. Retorna null si no n'hi ha cap.
     */
    private static function extractFranjasConfig(array $post): ?string
    {
        $raw = $post['franjas'] ?? null;
        if (!is_array($raw)) return null;
        $clamp = fn($c) => (($c = (int) $c) >= 1 && $c <= 4) ? $c : 0;

        // Files planes: franjas[i] = {label, tarifa, calaix}. S'agrupen per label en
        // {label, calaix (per defecte = 1r), tarifes:{tarifaId: calaix}}.
        $byLabel = [];   // label => ['calaix'=>int, 'tarifes'=>[tid=>cal]]
        $order   = [];   // per preservar l'ordre d'aparició dels labels
        foreach ($raw as $f) {
            if (!is_array($f)) continue;
            $label = mb_substr(trim((string) ($f['label'] ?? '')), 0, 60);
            if ($label === '') continue;
            $tid = (int) ($f['tarifa'] ?? 0);
            $cal = $clamp($f['calaix'] ?? 0);
            if ($tid <= 0 || $cal < 1) continue; // cada línia necessita tarifa + calaix

            if (!isset($byLabel[$label])) {
                $byLabel[$label] = ['calaix' => $cal, 'tarifes' => []];
                $order[] = $label;
            }
            $byLabel[$label]['tarifes'][$tid] = $cal;
        }

        $out = [];
        foreach ($order as $label) {
            $out[] = [
                'label'   => $label,
                'calaix'  => $byLabel[$label]['calaix'],
                'tarifes' => (object) $byLabel[$label]['tarifes'],
            ];
        }
        return $out ? (string) json_encode($out, JSON_UNESCAPED_UNICODE) : null;
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
        if (!empty($data['carrera_id']) && \App\Models\Carrera::findById((int) $data['carrera_id']) === null) {
            $v->addError('carrera_id', 'La cursa seleccionada no existeix.');
        }
        if (isset($data['anio_edicion']) && $data['anio_edicion'] !== null) {
            $v->integer('anio_edicion', 2000, 2100);
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
            // Només descartem files realment buides (sense nom). Una tarifa amb nom
            // però sense preu es considera GRATUÏTA (preu 0) — p. ex. infantils.
            if ($nombre === '') continue;

            $precio = $precioRaw === '' ? 0.0 : (float) str_replace(',', '.', $precioRaw);
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
                'tramos'       => self::parseTramos((string)($t['tramos_text'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * Parseja el textarea de trams de preu. Una línia per tram:
     *   "2026-03-01 | 15"  (fins l'1/3 → 15 €) · "| 25" o "25" (preu final sense data).
     * @return list<array{fecha_hasta:?string, precio:float}>
     */
    private static function parseTramos(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $fecha = preg_match('/(\d{4}-\d{2}-\d{2})/', $line, $md) ? $md[1] : null;
            $rest = $fecha !== null ? str_replace($fecha, '', $line) : $line;
            if (!preg_match('/(\d+(?:[.,]\d{1,2})?)/', $rest, $mp)) continue; // sense preu → ignora
            $out[] = ['fecha_hasta' => $fecha, 'precio' => (float) str_replace(',', '.', $mp[1])];
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

            // Mapa de calaix per opció: campos[i][calaix_map][<opció>] = calaix (1..4)
            $calaixMap = [];
            if (!empty($c['calaix_map']) && is_array($c['calaix_map'])) {
                foreach ($c['calaix_map'] as $opt => $cal) {
                    $opt = trim((string) $opt);
                    $cal = (int) $cal;
                    if ($opt !== '' && $cal >= 1 && $cal <= 4) $calaixMap[$opt] = $cal;
                }
            }

            $out[] = [
                'id'              => (int) ($c['id'] ?? 0),
                'nombre_campo'    => substr($nombre, 0, 100),
                'etiqueta'        => substr($etiqueta, 0, 255),
                'tipo'            => $tipo,
                'opciones'        => $opcionesJson,
                'opciones_tarifa' => $opcTarifa,
                'calaix_map'      => $calaixMap,
                'antes_estandar'  => !empty($c['antes_estandar']) ? 1 : 0,
                'requerido'       => !empty($c['requerido']) ? 1 : 0,
                'oculto'          => !empty($c['oculto']) ? 1 : 0,
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
