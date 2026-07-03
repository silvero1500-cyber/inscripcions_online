<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\CamposFijos;
use App\Models\Evento;
use App\Models\Inscrito;
use App\Models\Tarifa;

/**
 * Importació CSV per donar d'ALTA inscrits nous (a diferència de
 * InscritosImportController, que només actualitza inscrits ja existents).
 *
 * Nomes exigeix les columnes que són obligatòries per a aquest evento en
 * concret (segons `campos_fijos`), a més de Nom, Email i Tarifa que sempre
 * calen. Els inscrits creats queden marcats amb origen='importacion' i
 * estado='confirmado' (alta manual de l'organitzador, no passa pel pagament).
 */
final class InscritosAltaImportController
{
    /** Capçalera CSV → clau interna dels camps fixos estàndard */
    private const HEADER_MAP = [
        'nom'                => 'nombre',
        'nombre'             => 'nombre',
        'cognoms'            => 'apellido',
        'apellidos'          => 'apellido',
        'apellido'           => 'apellido',
        'email'              => 'email',
        'correu'             => 'email',
        'telefon'            => 'telefono',
        'telefono'           => 'telefono',
        'dni'                => 'dni',
        'dni nie'            => 'dni',
        'data naixement'     => 'fecha_nacimiento',
        'fecha nacimiento'   => 'fecha_nacimiento',
        'sexe'               => 'sexo',
        'sexo'               => 'sexo',
        'talla'              => 'talla_camiseta',
        'talla samarreta'    => 'talla_camiseta',
        'club'               => 'club',
        'poblacio'           => 'poblacion',
        'poblacion'          => 'poblacion',
        'població'           => 'poblacion',
        'codi postal'        => 'codigo_postal',
        'codigo postal'      => 'codigo_postal',
        'cp'                 => 'codigo_postal',
        'franja de temps'    => 'franja_temps',
        'franja temps'       => 'franja_temps',
        'xip groc'           => 'chip_groc',
        'chip groc'          => 'chip_groc',
        'tarifa'             => 'tarifa',
        'modalitat'          => 'tarifa',
        'modalidad'          => 'tarifa',
    ];

    /** Camps sempre obligatoris per crear un inscrit, independentment de la config de l'event */
    private const SEMPRE_OBLIGATORIS = ['nombre', 'email', 'tarifa'];

    public function form(Request $req, array $params): void
    {
        $user = Auth::user();
        $eventoId = (int) ($params['id'] ?? 0);

        $evento = Evento::findById($eventoId);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $eventoId)) Response::forbidden();

        $config = CamposFijos::resolve($evento['campos_fijos'] ?? null);
        $obligatorios = self::SEMPRE_OBLIGATORIS;
        foreach (CamposFijos::CAMPS as $key => $meta) {
            if (CamposFijos::requerido($config, $key)) $obligatorios[] = $key;
        }

        View::render('admin/inscritos/import_altas', [
            'user'         => $user,
            'evento'       => $evento,
            'tarifas'      => Tarifa::listByEvento($eventoId),
            'obligatorios' => $obligatorios,
            'flash'        => Session::pullAllFlashes(),
        ], layout: 'admin');
    }

    public function preview(Request $req, array $params): void
    {
        $user = Auth::user();
        $eventoId = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/importar-altas"));
        }

        $evento = Evento::findById($eventoId);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $eventoId)) Response::forbidden();

        $file = $_FILES['csv'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            Session::flash('error', 'No s\'ha pogut llegir el fitxer. Comprova que sigui un CSV vàlid.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/importar-altas"));
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            Session::flash('error', 'Fitxer massa gran (màx 5 MB).');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/importar-altas"));
        }

        $parsed = self::parseCsv($file['tmp_name']);
        if (isset($parsed['error'])) {
            Session::flash('error', $parsed['error']);
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/importar-altas"));
        }

        $report = self::buildAltas($evento, $parsed['headers'], $parsed['rows']);

        $token = bin2hex(random_bytes(8));
        $tmpFile = sys_get_temp_dir() . '/altasimport_' . $token . '.json';
        @file_put_contents($tmpFile, json_encode([
            'evento_id' => $eventoId,
            'user_id'   => $user->id,
            'created'   => time(),
            'altas'     => $report['altas'],
        ], JSON_UNESCAPED_UNICODE));

        View::render('admin/inscritos/import_altas_preview', [
            'user'      => $user,
            'evento'    => $evento,
            'token'     => $token,
            'totalRows' => $report['totalRows'],
            'altas'     => $report['altas'],
            'errors'    => $report['errors'],
        ], layout: 'admin');
    }

    public function apply(Request $req, array $params): void
    {
        $user = Auth::user();
        $eventoId = (int) ($params['id'] ?? 0);

        if (!Csrf::verify($req->post('_csrf'))) {
            Session::flash('error', 'Sessió expirada.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/importar-altas"));
        }

        $evento = Evento::findById($eventoId);
        if ($evento === null) Response::notFound();
        if (!Evento::userCanEdit($user->id, $user->rol, $eventoId)) Response::forbidden();

        $token = (string) $req->post('token', '');
        if (!preg_match('/^[a-f0-9]{16}$/', $token)) {
            Session::flash('error', 'Token de previsualització invàlid.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/importar-altas"));
        }
        $tmpFile = sys_get_temp_dir() . '/altasimport_' . $token . '.json';
        if (!is_file($tmpFile)) {
            Session::flash('error', 'Previsualització caducada. Torna a pujar el fitxer.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/importar-altas"));
        }
        $payload = json_decode((string) @file_get_contents($tmpFile), true);
        if (!is_array($payload) || (int)($payload['evento_id'] ?? 0) !== $eventoId
            || (int)($payload['user_id'] ?? 0) !== $user->id) {
            Session::flash('error', 'Previsualització no vàlida.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/importar-altas"));
        }
        if (time() - (int)$payload['created'] > 1800) {
            @unlink($tmpFile);
            Session::flash('error', 'Previsualització caducada (>30 min).');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/importar-altas"));
        }

        $altas = is_array($payload['altas'] ?? null) ? $payload['altas'] : [];
        if (count($altas) === 0) {
            Session::flash('error', 'No hi havia altes vàlides per crear.');
            Response::redirect(base_url("/admin/eventos/{$eventoId}/inscritos/importar-altas"));
        }

        $created = 0;
        $failed = [];

        foreach ($altas as $a) {
            try {
                Inscrito::createWithCustomFields($a['fields'], []);
                $created++;
            } catch (\Throwable $e) {
                $failed[] = ['row' => $a['row'], 'error' => $e->getMessage()];
            }
        }

        @unlink($tmpFile);

        $msg = "S'han donat d'alta {$created} inscrits per importació.";
        if (count($failed) > 0) $msg .= ' ' . count($failed) . ' fallits.';
        Session::flash('success', $msg);
        Response::redirect(base_url('/admin/inscritos?evento_id=' . $eventoId));
    }

    // ────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────

    /** @return array{headers: list<string>, rows: list<list<string>>}|array{error:string} */
    private static function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) return ['error' => 'No s\'ha pogut obrir el fitxer.'];

        $first = fgets($handle);
        if ($first === false) { fclose($handle); return ['error' => 'Fitxer buit.']; }
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        $sep = (substr_count($first, ';') > substr_count($first, ',')) ? ';' : ',';

        rewind($handle);
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $sep, '"', '\\')) !== false) {
            if (count($row) === 1 && trim((string)($row[0] ?? '')) === '') continue;
            $rows[] = $row;
        }
        fclose($handle);

        if (count($rows) < 2) return ['error' => 'El CSV ha de tenir capçalera i almenys una fila de dades.'];

        $headers = array_map(fn($h) => self::normHeader((string) $h), array_shift($rows));
        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     * @return array{totalRows:int, altas: list<array{row:int, fields:array<string,mixed>, resumen:string}>, errors: list<array{row:int, msg:string}>}
     */
    private static function buildAltas(array $evento, array $headers, array $rows): array
    {
        $eventoId = (int) $evento['id'];
        $config = CamposFijos::resolve($evento['campos_fijos'] ?? null);

        $obligatorios = self::SEMPRE_OBLIGATORIS;
        foreach (CamposFijos::CAMPS as $key => $meta) {
            if (CamposFijos::requerido($config, $key)) $obligatorios[] = $key;
        }

        // Mapa columna CSV → clau interna
        $colIdx = []; // clau => index
        foreach ($headers as $idx => $h) {
            if (isset(self::HEADER_MAP[$h]) && !isset($colIdx[self::HEADER_MAP[$h]])) {
                $colIdx[self::HEADER_MAP[$h]] = $idx;
            }
        }

        $faltantes = array_values(array_diff($obligatorios, array_keys($colIdx)));
        if (!empty($faltantes)) {
            $labels = array_map(fn($k) => self::labelOf($k), $faltantes);
            return [
                'totalRows' => count($rows),
                'altas' => [],
                'errors' => [['row' => 0, 'msg' => 'Falten columnes obligatòries al CSV: ' . implode(', ', $labels) . '.']],
            ];
        }

        // Tarifes actives per nom (normalitzat)
        $tarifasByNombre = [];
        foreach (Tarifa::listByEvento($eventoId) as $t) {
            $tarifasByNombre[self::normHeader((string) $t['nombre'])] = $t;
        }

        $altas = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            $get = fn(string $k): string => trim((string) ($row[$colIdx[$k]] ?? ''));

            // Camps obligatoris presents i no buits
            $rowErrors = [];
            foreach ($obligatorios as $k) {
                if ($get($k) === '') $rowErrors[] = self::labelOf($k);
            }
            if (!empty($rowErrors)) {
                $errors[] = ['row' => $rowNum, 'msg' => 'Falten valors obligatoris: ' . implode(', ', array_unique($rowErrors)) . '.'];
                continue;
            }

            // Tarifa
            $tarifaKey = self::normHeader($get('tarifa'));
            if (!isset($tarifasByNombre[$tarifaKey])) {
                $errors[] = ['row' => $rowNum, 'msg' => "Tarifa \"{$get('tarifa')}\" no coincideix amb cap tarifa d'aquest event."];
                continue;
            }
            $tarifa = $tarifasByNombre[$tarifaKey];

            // Email
            $email = strtolower($get('email'));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $rowNum, 'msg' => 'Email no vàlid.'];
                continue;
            }

            $fields = [
                'evento_id'   => $eventoId,
                'tarifa_id'   => (int) $tarifa['id'],
                'nombre'      => mb_substr($get('nombre'), 0, 100),
                'email'       => mb_substr($email, 0, 255),
                'estado'      => 'confirmado',
                'origen'      => 'importacion',
                'ip_registro' => null,
            ];

            if (isset($colIdx['apellido'])) $fields['apellido'] = ($v = $get('apellido')) !== '' ? mb_substr($v, 0, 150) : null;
            if (isset($colIdx['telefono'])) $fields['telefono'] = ($v = $get('telefono')) !== '' ? mb_substr($v, 0, 20) : null;
            if (isset($colIdx['club']))      $fields['club']      = ($v = $get('club')) !== '' ? mb_substr($v, 0, 150) : null;
            if (isset($colIdx['poblacion'])) $fields['poblacion'] = ($v = $get('poblacion')) !== '' ? mb_substr($v, 0, 150) : null;
            if (isset($colIdx['franja_temps'])) $fields['franja_temps'] = ($v = $get('franja_temps')) !== '' ? mb_substr($v, 0, 60) : null;

            if (isset($colIdx['dni'])) {
                $dni = strtoupper($get('dni'));
                if ($dni !== '' && !Inscrito::documentoValido($dni)) {
                    $errors[] = ['row' => $rowNum, 'msg' => 'DNI/document: ha de tenir entre 4 i 20 caràcters alfanumèrics.'];
                    continue;
                }
                $fields['dni'] = $dni !== '' ? $dni : null;
            }

            if (isset($colIdx['codigo_postal'])) {
                $cp = preg_replace('/\D+/', '', $get('codigo_postal'));
                if ($cp !== '' && !preg_match('/^\d{4,5}$/', $cp)) {
                    $errors[] = ['row' => $rowNum, 'msg' => 'Codi postal ha de ser 4-5 dígits.'];
                    continue;
                }
                $fields['codigo_postal'] = $cp !== '' ? $cp : null;
            }

            if (isset($colIdx['sexo'])) {
                $sexoRaw = strtoupper($get('sexo'));
                $sexoMap = ['H' => 'H', 'M' => 'M', 'NB' => 'NB', 'HOME' => 'H', 'DONA' => 'M', 'HOMBRE' => 'H', 'MUJER' => 'M'];
                $sexo = $sexoMap[$sexoRaw] ?? ($sexoRaw !== '' ? null : '');
                if ($sexoRaw !== '' && !in_array($sexo, Inscrito::SEXOS, true)) {
                    $errors[] = ['row' => $rowNum, 'msg' => 'Sexe no vàlid (H/M/NB).'];
                    continue;
                }
                $fields['sexo'] = $sexo ?: null;
            }

            if (isset($colIdx['talla_camiseta'])) {
                $talla = strtoupper($get('talla_camiseta'));
                if ($talla !== '' && !in_array($talla, Inscrito::TALLAS, true)) {
                    $errors[] = ['row' => $rowNum, 'msg' => 'Talla no vàlida (' . implode('/', Inscrito::TALLAS) . ').'];
                    continue;
                }
                $fields['talla_camiseta'] = $talla !== '' ? $talla : null;
            }

            if (isset($colIdx['fecha_nacimiento'])) {
                $fn = $get('fecha_nacimiento');
                $fnNorm = self::normalizeDate($fn);
                if ($fn !== '' && $fnNorm === null) {
                    $errors[] = ['row' => $rowNum, 'msg' => 'Data de naixement no vàlida (format AAAA-MM-DD o DD/MM/AAAA).'];
                    continue;
                }
                $fields['fecha_nacimiento'] = $fnNorm;
            }

            if (isset($colIdx['chip_groc'])) {
                $cgRaw = strtolower($get('chip_groc'));
                $cg = in_array($cgRaw, ['si', 'sí', 'no'], true) ? ($cgRaw === 'no' ? 'no' : 'si') : ($cgRaw !== '' ? null : '');
                if ($cgRaw !== '' && $cg === null) {
                    $errors[] = ['row' => $rowNum, 'msg' => 'Xip groc ha de ser SI/NO.'];
                    continue;
                }
                $fields['chip_groc'] = $cg ?: null;
            }

            $altas[] = [
                'row'     => $rowNum,
                'fields'  => $fields,
                'resumen' => $fields['nombre'] . ' ' . ($fields['apellido'] ?? '') . ' · ' . $fields['email'] . ' · ' . $tarifa['nombre'],
            ];
        }

        return ['totalRows' => count($rows), 'altas' => $altas, 'errors' => $errors];
    }

    private static function normalizeDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') return null;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
            return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $raw : null;
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $raw, $m)) {
            [, $d, $mo, $y] = $m;
            return checkdate((int)$mo, (int)$d, (int)$y)
                ? sprintf('%04d-%02d-%02d', (int)$y, (int)$mo, (int)$d)
                : null;
        }
        return null;
    }

    private static function labelOf(string $k): string
    {
        return match ($k) {
            'nombre' => 'Nom',
            'email'  => 'Email',
            'tarifa' => 'Tarifa',
            default  => CamposFijos::labelOf($k),
        };
    }

    /** Normalitza una capçalera CSV: minúscules, sense accents/símbols. */
    private static function normHeader(string $h): string
    {
        $h = preg_replace('/[^a-z0-9 ]+/u', '', mb_strtolower(trim($h)));
        return trim(preg_replace('/\s+/', ' ', (string) $h));
    }
}
