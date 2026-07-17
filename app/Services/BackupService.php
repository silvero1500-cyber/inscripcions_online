<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\CampoPersonalizado;
use App\Models\Inscrito;

/**
 * Còpia de seguretat diària en CSV dels inscrits dels esdeveniments ACTIUS.
 * S'executa com a molt un cop al dia (marcador .last-run) i es dispara des del
 * front controller en acabar la resposta (sense cron). Guarda a storage/backups/
 * una carpeta per dia (YYYY-MM-DD) amb un CSV per esdeveniment, i conserva els
 * últims RETENTION_DAYS dies.
 */
final class BackupService
{
    private const RETENTION_DAYS = 14;

    public static function runDailyIfDue(): void
    {
        try {
            $dir = BASE_PATH . '/storage/backups';
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return;

            // Protecció web per si el directori fos accessible
            $ht = $dir . '/.htaccess';
            if (!is_file($ht)) @file_put_contents($ht, "Require all denied\n");

            $marker = $dir . '/.last-run';
            $today  = date('Y-m-d');
            if (is_file($marker) && trim((string) @file_get_contents($marker)) === $today) return;

            // Reserva el torn abans de treballar (evita dobles execucions concurrents)
            if (@file_put_contents($marker, $today, LOCK_EX) === false) return;

            self::backupEventosActivos($dir . '/' . $today);
            self::prune($dir);
        } catch (\Throwable $e) {
            error_log('[Backup] Error: ' . $e->getMessage());
        }
    }

    private static function backupEventosActivos(string $destDir): void
    {
        $db = Database::getInstance();
        $eventos = $db->query(
            "SELECT id, titulo, slug FROM eventos WHERE activo = 1 AND archivado_at IS NULL"
        )->fetchAll();
        if (!$eventos) return;

        if (!is_dir($destDir) && !@mkdir($destDir, 0755, true)) return;

        foreach ($eventos as $e) {
            self::backupEvento((int) $e['id'], (string) $e['slug'], $destDir);
        }
    }

    private static function backupEvento(int $eventoId, string $slug, string $destDir): void
    {
        $inscritos = Inscrito::listForAdminExport(['evento_id' => $eventoId]);
        $campos    = CampoPersonalizado::listByEvento($eventoId);
        $valores   = CampoPersonalizado::valoresPorInscrito(array_map(fn($i) => (int) $i['id'], $inscritos));

        $file = $destDir . '/inscrits_' . preg_replace('/[^a-z0-9_-]+/i', '_', $slug) . '.csv';
        $out = @fopen($file, 'w');
        if (!$out) return;

        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 per a Excel

        $headers = [
            'ID', 'Comanda', 'Data inscripció', 'Nom', 'Cognoms', 'DNI', 'Sexe', 'Data naixement',
            'Email', 'Telèfon', 'Club', 'Població', 'Codi postal', 'Talla', 'Franja temps',
            'Xip groc', 'Núm xip groc',
            'Tutor nom', 'Tutor cognoms', 'Tutor DNI',
            'Tarifa', 'Preu', 'Estat', 'Origen', 'Dorsal'
        ];
        foreach ($campos as $c) {
            $headers[] = (string) $c['etiqueta'] . (!empty($c['oculto']) ? ' (ocult)' : '');
        }
        fputcsv($out, $headers, ';', '"', '\\');

        foreach ($inscritos as $i) {
            $row = [
                $i['id'],
                !empty($i['pedido_id']) ? '#' . (int) $i['pedido_id'] : '',
                format_datetime_local($i['created_at'], 'd/m/Y H:i'),
                $i['nombre'],
                $i['apellido'],
                $i['dni'],
                $i['sexo'],
                $i['fecha_nacimiento'],
                $i['email'],
                $i['telefono'],
                $i['club'] ?? '',
                $i['poblacion'] ?? '',
                $i['codigo_postal'] ?? '',
                $i['talla_camiseta'] ?? '',
                $i['franja_temps'] ?? '',
                !empty($i['chip_groc']) ? ($i['chip_groc'] === 'si' ? 'Sí' : 'No') : '',
                $i['chip_groc_num'] ?? '',
                $i['tutor_nombre'] ?? '',
                $i['tutor_apellido'] ?? '',
                $i['tutor_dni'] ?? '',
                $i['tarifa_nombre'],
                number_format((float) $i['tarifa_precio'], 2, ',', '.'),
                $i['estado'],
                ($i['origen'] ?? 'formulario') === 'importacion' ? 'Importació' : 'Formulari',
                $i['numero_dorsal'] ?? '',
            ];
            $vals = $valores[(int) $i['id']] ?? [];
            foreach ($campos as $c) {
                $row[] = $vals[(int) $c['id']] ?? '';
            }
            fputcsv($out, $row, ';', '"', '\\');
        }

        fclose($out);
    }

    /** Esborra les carpetes de backup més antigues que RETENTION_DAYS dies. */
    private static function prune(string $dir): void
    {
        $limit = date('Y-m-d', strtotime('-' . self::RETENTION_DAYS . ' days'));
        foreach (glob($dir . '/????-??-??', GLOB_ONLYDIR) ?: [] as $sub) {
            if (basename($sub) < $limit) {
                foreach (glob($sub . '/*') ?: [] as $f) @unlink($f);
                @rmdir($sub);
            }
        }
    }
}
