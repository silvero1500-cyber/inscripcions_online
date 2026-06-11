<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\CampoPersonalizado;
use App\Models\Evento;
use App\Models\Inscrito;

/**
 * Importació CSV per actualitzar inscrits en bulk.
 *
 * Cas d'ús principal: assignació de dorsals offline. L'organitzador descarrega
 * el CSV de /admin/inscritos/export, omple la columna "Dorsal", torna a pujar
 * el fitxer i s'apliquen els canvis.
 *
 * Columnes permeses per actualitzar (la resta s'ignoren):
 *   numero_dorsal, talla_camiseta, club, poblacion, codigo_postal, estado
 *   + qualsevol camp personalitzat (per coincidència amb la seva etiqueta),
 *     inclosos els ocults → s'escriuen a inscrito_campos_valores.
 *
 * Identificació: per columna "ID" del CSV (la primera del export).
 */
final class InscritosImportController
{
    /** Columnes admeses per a edició (les altres del CSV s'ignoren) */
    private const EDITABLE = ['numero_dorsal', 'talla_camiseta', 'club', 'poblacion', 'codigo_postal', 'estado'];

    /** Map header CSV → columna BD */
    private const HEADER_MAP = [
        'id'           => 'id',
        'dorsal'       => 'numero_dorsal',
        'talla'        => 'talla_camiseta',
        'club'         => 'club',
        'poblacio'     => 'poblacion',
        'poblacion'    => 'poblacion',
        'població'     => 'poblacion',
        'codi postal'  => 'codigo_postal',
        'codigo postal'=> 'codigo_postal',
        'cp'           => 'codigo_postal',
        'estat'        => 'estado',
        'estado'       => 'estado',
    ];

    public function form(Request $req, array $params): void
    {
        $user = Auth::user();
        $eventoId = (int) ($params['id'] ?? 0);

        $evento = Evento::findById($eventoId);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $eventoId)) Response::forbidden();

        View::render('admin/inscritos/import', [
            'user'   => $user,
            'evento' => $evento,
            'flash'  => Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    /**
     * Rep el CSV, el parseja, valida i guarda els canvis pendents en sessió
     * per a la pantalla de previsualització.
     */
    public function preview(Request $req, array $params): void
    {
        $user = Auth::user();
        $eventoId = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/import"));
        }

        $evento = Evento::findById($eventoId);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $eventoId)) Response::forbidden();

        // Validar fitxer
        $file = $_FILES['csv'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            Session::flash('error', 'No s\'ha pogut llegir el fitxer. Comprova que sigui un CSV vàlid.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/import"));
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            Session::flash('error', 'Fitxer massa gran (màx 5 MB).');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/import"));
        }

        $parsed = self::parseCsv($file['tmp_name']);
        if (isset($parsed['error'])) {
            Session::flash('error', $parsed['error']);
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/import"));
        }

        // Validar i preparar canvis. Només superadmin pot canviar l'estat per CSV.
        $canEditEstado = $user->rol === 'superadmin';
        $report = self::buildChanges($eventoId, $parsed['headers'], $parsed['rows'], $canEditEstado);

        // Guardar canvis pendents en fitxer temporal (sessió pot ser massa)
        $token = bin2hex(random_bytes(8));
        $tmpFile = sys_get_temp_dir() . '/import_' . $token . '.json';
        @file_put_contents($tmpFile, json_encode([
            'evento_id' => $eventoId,
            'user_id'   => $user->id,
            'created'   => time(),
            'changes'   => $report['changes'],
        ], JSON_UNESCAPED_UNICODE));

        View::render('admin/inscritos/import_preview', [
            'user'        => $user,
            'evento'      => $evento,
            'token'       => $token,
            'totalRows'   => $report['totalRows'],
            'changes'     => $report['changes'],
            'errors'      => $report['errors'],
            'skipped'     => $report['skipped'],
            'changedCols' => $report['changedCols'],
            'estadoIgnored' => $report['estadoIgnored'] ?? false,
        ], layout: 'admin');
    }

    /**
     * Aplica els canvis guardats al fitxer temporal dins d'una transacció.
     */
    public function apply(Request $req, array $params): void
    {
        $user = Auth::user();
        $eventoId = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/import"));
        }

        $evento = Evento::findById($eventoId);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $eventoId)) Response::forbidden();

        $token = (string) $req->post('token', '');
        if (!preg_match('/^[a-f0-9]{16}$/', $token)) {
            Session::flash('error', 'Token de previsualització invàlid.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/import"));
        }
        $tmpFile = sys_get_temp_dir() . '/import_' . $token . '.json';
        if (!is_file($tmpFile)) {
            Session::flash('error', 'Previsualització caducada. Torna a pujar el fitxer.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/import"));
        }
        $payload = json_decode((string) @file_get_contents($tmpFile), true);
        if (!is_array($payload) || (int)($payload['evento_id'] ?? 0) !== $eventoId
            || (int)($payload['user_id'] ?? 0) !== $user->id) {
            Session::flash('error', 'Previsualització no vàlida.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/import"));
        }
        if (time() - (int)$payload['created'] > 1800) { // 30 min
            @unlink($tmpFile);
            Session::flash('error', 'Previsualització caducada (>30 min).');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/import"));
        }

        $changes = is_array($payload['changes'] ?? null) ? $payload['changes'] : [];
        if (count($changes) === 0) {
            Session::flash('error', 'No hi havia canvis per aplicar.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/import"));
        }

        $applied = 0;
        $failed = [];
        $isSuper = $user->rol === 'superadmin';

        try {
            Database::getInstance()->transaction(function () use ($changes, $isSuper, &$applied, &$failed): void {
                foreach ($changes as $c) {
                    $fields = is_array($c['fields'] ?? null) ? $c['fields'] : [];
                    // Doble verificació: només superadmin pot tocar l'estat
                    if (!$isSuper) unset($fields['estado']);
                    $custom = is_array($c['customFields'] ?? null) ? $c['customFields'] : [];
                    if (empty($fields) && empty($custom)) continue;
                    $iid = (int) $c['id'];
                    try {
                        if (!empty($fields)) {
                            Database::getInstance()->update('inscritos', $fields, ['id' => $iid]);
                        }
                        foreach ($custom as $campoId => $val) {
                            $campoId = (int) $campoId;
                            if ($val === null || $val === '') {
                                Database::getInstance()->query(
                                    'DELETE FROM inscrito_campos_valores WHERE inscrito_id = ? AND campo_id = ?',
                                    [$iid, $campoId]
                                );
                            } else {
                                Database::getInstance()->query(
                                    'INSERT INTO inscrito_campos_valores (inscrito_id, campo_id, valor)
                                     VALUES (?, ?, ?)
                                     ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
                                    [$iid, $campoId, (string) $val]
                                );
                            }
                        }
                        $applied++;
                    } catch (\Throwable $e) {
                        $failed[] = ['id' => $c['id'], 'error' => $e->getMessage()];
                    }
                }
            });
        } catch (\Throwable $e) {
            Session::flash('error', 'Error aplicant canvis: ' . $e->getMessage());
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/import"));
        }

        @unlink($tmpFile);

        $msg = "S'han aplicat {$applied} canvis correctament.";
        if (count($failed) > 0) {
            $msg .= ' ' . count($failed) . ' fallits.';
        }
        Session::flash('success', $msg);
        Response::redirect(base_url('/admin/inscritos?evento_id=' . $eventoId));
    }

    // ────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────

    /**
     * Llegeix un CSV detectant separador (; o ,) i BOM UTF-8.
     * @return array{headers: list<string>, rows: list<list<string>>}|array{error:string}
     */
    private static function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) return ['error' => 'No s\'ha pogut obrir el fitxer.'];

        // Llegir primera línia per detectar separador i BOM
        $first = fgets($handle);
        if ($first === false) { fclose($handle); return ['error' => 'Fitxer buit.']; }
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first); // strip BOM
        $sep = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';

        // Tornar al principi i parsejar amb separador detectat
        rewind($handle);
        // Saltar BOM si existeix
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $sep, '"', '\\')) !== false) {
            if (count($row) === 1 && trim((string)($row[0] ?? '')) === '') continue;
            $rows[] = $row;
        }
        fclose($handle);

        if (count($rows) < 2) return ['error' => 'El CSV ha de tenir capçalera i almenys una fila de dades.'];

        $headers = array_map(fn($h) => strtolower(trim((string)$h)), array_shift($rows));
        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Compara cada fila del CSV amb la BD i prepara canvis si hi ha diferències.
     *
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     * @return array{
     *   totalRows:int,
     *   changes: list<array{id:int, dni:string, nom:string, fields:array<string,mixed>, diff:array<string,array{from:?string,to:?string}>}>,
     *   errors: list<array{row:int, msg:string}>,
     *   skipped: int,
     *   changedCols: array<string,int>
     * }
     */
    private static function buildChanges(int $eventoId, array $headers, array $rows, bool $canEditEstado): array
    {
        $idIdx = array_search('id', $headers, true);
        if ($idIdx === false) {
            return [
                'totalRows' => count($rows),
                'changes' => [],
                'errors' => [['row' => 0, 'msg' => 'El CSV no té columna "ID". Descarrega el CSV d\'exportació com a plantilla.']],
                'skipped' => 0,
                'changedCols' => [],
                'estadoIgnored' => false,
            ];
        }

        // Detectar quines columnes fixes editables hi ha al CSV
        $colMap = [];
        $estadoIgnored = false;
        $usedIdx = [$idIdx => true];
        foreach ($headers as $idx => $h) {
            $clean = self::normHeader($h);
            if (isset(self::HEADER_MAP[$clean])) {
                $col = self::HEADER_MAP[$clean];
                // Només superadmin pot canviar l'estat per CSV
                if ($col === 'estado' && !$canEditEstado) {
                    $estadoIgnored = true;
                    continue;
                }
                if (in_array($col, self::EDITABLE, true)) {
                    $colMap[$col] = $idx;
                    $usedIdx[$idx] = true;
                }
            }
        }

        // Detectar columnes de camps personalitzats (per coincidència amb l'etiqueta).
        // Permet omplir a mà els camps (sobretot els ocults) i tornar-los a carregar.
        $campos = CampoPersonalizado::listByEvento($eventoId);
        $campoByNorm = [];
        foreach ($campos as $cp) {
            $n = self::normHeader((string) $cp['etiqueta']);
            if ($n !== '') $campoByNorm[$n] = $cp;
        }
        // Capçaleres base (no editables) reservades, per no confondre-les amb un camp
        $reserved = [];
        foreach (['ID','Comanda','Data inscripció','Nom','Cognoms','DNI','Sexe','Data naixement',
                  'Email','Telèfon','Tarifa','Preu','Check-in'] as $bh) {
            $reserved[self::normHeader($bh)] = true;
        }
        $customColMap = []; // idx => campo (array)
        foreach ($headers as $idx => $h) {
            if (isset($usedIdx[$idx])) continue;
            $clean = self::normHeader($h);
            if ($clean === '' || isset(self::HEADER_MAP[$clean]) || isset($reserved[$clean])) continue;
            if (isset($campoByNorm[$clean])) {
                $customColMap[$idx] = $campoByNorm[$clean];
                $usedIdx[$idx] = true;
            }
        }

        if (empty($colMap) && empty($customColMap)) {
            $msg = $estadoIgnored
                ? 'L\'única columna editable era "Estat", però només un superadmin pot canviar-la per CSV. Edita dorsal, talla, club, població o codi postal.'
                : 'El CSV no conté cap columna editable (dorsal, talla, club, població, codi postal, estat) ni cap camp personalitzat reconegut.';
            return [
                'totalRows' => count($rows),
                'changes' => [],
                'errors' => [['row' => 0, 'msg' => $msg]],
                'skipped' => 0,
                'changedCols' => [],
                'estadoIgnored' => $estadoIgnored,
            ];
        }

        // Carregar inscrits del evento per a comparació
        $existing = [];
        $rowsBD = Database::getInstance()->query(
            "SELECT id, dni, nombre, apellido, numero_dorsal, talla_camiseta, club, poblacion, codigo_postal, estado
             FROM inscritos WHERE evento_id = ?",
            [$eventoId]
        )->fetchAll();
        foreach ($rowsBD as $r) $existing[(int)$r['id']] = $r;

        // Valors actuals dels camps personalitzats (per comparar i detectar canvis)
        $customExisting = $customColMap
            ? CampoPersonalizado::valoresPorInscrito(array_keys($existing))
            : [];

        // Comprovar dorsals usats per detectar duplicats dins el CSV mateix
        $dorsalUsats = [];

        $changes = [];
        $errors = [];
        $skipped = 0;
        $changedCols = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2; // +1 per la cap, +1 per ser 1-indexed

            $id = (int) trim((string)($row[$idIdx] ?? ''));
            if ($id === 0) {
                $errors[] = ['row' => $rowNum, 'msg' => 'ID buit o no numèric.'];
                continue;
            }
            if (!isset($existing[$id])) {
                $errors[] = ['row' => $rowNum, 'msg' => "ID {$id} no pertany a aquest evento."];
                continue;
            }
            $orig = $existing[$id];

            $newFields = [];
            $diff = [];
            foreach ($colMap as $col => $idx) {
                $rawVal = trim((string)($row[$idx] ?? ''));
                $newVal = self::normalize($col, $rawVal);
                $oldVal = $orig[$col] ?? null;

                if (!self::valuesEqual($oldVal, $newVal)) {
                    // Validar
                    $vErr = self::validateValue($col, $newVal);
                    if ($vErr !== null) {
                        $errors[] = ['row' => $rowNum, 'msg' => "Camp '{$col}' (ID {$id}): {$vErr}"];
                        continue 2;
                    }
                    $newFields[$col] = $newVal;
                    $diff[$col] = ['from' => $oldVal !== null ? (string)$oldVal : null, 'to' => $newVal !== null ? (string)$newVal : null];

                    // Dorsal collision check dins el CSV
                    if ($col === 'numero_dorsal' && $newVal !== null) {
                        if (isset($dorsalUsats[(int)$newVal])) {
                            $errors[] = ['row' => $rowNum, 'msg' => "Dorsal {$newVal} duplicat (fila " . $dorsalUsats[(int)$newVal] . ")."];
                            continue 2;
                        }
                        $dorsalUsats[(int)$newVal] = $rowNum;
                    }
                }
            }

            // Camps personalitzats (s'escriuen a inscrito_campos_valores)
            $customFields = [];
            foreach ($customColMap as $idx => $campo) {
                $cid = (int) $campo['id'];
                $rawVal = trim((string) ($row[$idx] ?? ''));
                $newVal = ($rawVal === '') ? null : mb_substr($rawVal, 0, 2000);
                $oldVal = $customExisting[$id][$cid] ?? null;
                if ($oldVal === '') $oldVal = null;
                if (!self::valuesEqual($oldVal, $newVal)) {
                    $label = (string) $campo['etiqueta'];
                    $customFields[$cid] = $newVal;
                    $diff[$label] = ['from' => $oldVal, 'to' => $newVal];
                    $changedCols[$label] = ($changedCols[$label] ?? 0) + 1;
                }
            }

            if (count($newFields) === 0 && count($customFields) === 0) {
                $skipped++;
                continue;
            }

            $change = [
                'id'    => $id,
                'dni'   => (string) $orig['dni'],
                'nom'   => (string) ($orig['nombre'] . ' ' . $orig['apellido']),
                'fields'=> $newFields,
                'diff'  => $diff,
            ];
            if ($customFields) $change['customFields'] = $customFields;
            $changes[] = $change;

            foreach (array_keys($newFields) as $c) {
                $changedCols[$c] = ($changedCols[$c] ?? 0) + 1;
            }
        }

        return [
            'totalRows'   => count($rows),
            'changes'     => $changes,
            'errors'      => $errors,
            'skipped'     => $skipped,
            'changedCols' => $changedCols,
            'estadoIgnored' => $estadoIgnored,
        ];
    }

    private static function normalize(string $col, string $raw): mixed
    {
        if ($raw === '') return null;
        return match ($col) {
            'numero_dorsal'   => ctype_digit($raw) ? (int)$raw : $raw, // si no numèric, ho validem després
            'talla_camiseta'  => strtoupper($raw),
            'estado'          => strtolower($raw),
            'club', 'poblacion' => mb_substr($raw, 0, 150),
            'codigo_postal'   => preg_replace('/\D+/', '', $raw),
            default           => $raw,
        };
    }

    private static function validateValue(string $col, mixed $v): ?string
    {
        if ($v === null) return null;
        return match ($col) {
            'numero_dorsal'   => is_int($v) && $v > 0 && $v < 100000 ? null : 'dorsal ha de ser un número 1-99999',
            'talla_camiseta'  => in_array($v, Inscrito::TALLAS, true) ? null : 'talla no vàlida (' . implode('/', Inscrito::TALLAS) . ')',
            'estado'          => in_array($v, ['pendiente', 'confirmado', 'cancelado', 'reembolsado'], true) ? null : 'estat no vàlid',
            'codigo_postal'   => preg_match('/^\d{4,5}$/', (string)$v) ? null : 'CP ha de ser 4-5 dígits',
            default           => mb_strlen((string)$v) > 150 ? 'massa llarg (>150 chars)' : null,
        };
    }

    private static function valuesEqual(mixed $a, mixed $b): bool
    {
        if ($a === null && $b === null) return true;
        if ($a === null || $b === null) return false;
        return (string)$a === (string)$b;
    }

    /**
     * Normalitza una capçalera CSV per comparar-la: treu el sufix "(ocult)" que
     * afegeix l'exportació, passa a minúscules i elimina símbols/accents.
     */
    private static function normHeader(string $h): string
    {
        $h = preg_replace('/\s*\(ocult\)\s*$/iu', '', $h);
        $h = preg_replace('/[^a-z0-9 ]+/u', '', strtolower($h));
        return trim(preg_replace('/\s+/', ' ', (string) $h));
    }
}
