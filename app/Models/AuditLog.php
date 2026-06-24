<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;

/**
 * Registre d'auditoria de seguretat: accions sensibles fetes a l'admin
 * (login, gestió d'usuaris, esborrat/arxivat d'esdeveniments, exportació
 * d'inscrits, configuració). Desa qui (id + nom snapshot), què, IP i quan.
 * Tolerant a errors: mai bloqueja l'acció principal si el registre falla.
 */
final class AuditLog
{
    // Accions conegudes (per a etiquetes a la vista)
    public const LOGIN_OK     = 'login_ok';
    public const LOGIN_FAIL   = 'login_fail';
    public const USUARI_CREAT = 'usuari_creat';
    public const USUARI_ESBORRAT = 'usuari_esborrat';
    public const EVENTO_ESBORRAT = 'evento_esborrat';
    public const EVENTO_ARXIVAT  = 'evento_arxivat';
    public const INSCRITS_EXPORT = 'inscrits_export';
    public const CONFIG_DESADA   = 'config_desada';

    /**
     * Registra una acció. Si no es passa usuari, l'agafa de la sessió (Auth).
     * Per a accions sense sessió (login fallit) es deixa l'usuari buit i el
     * detall (p. ex. el correu provat) identifica l'intent.
     */
    public static function registrar(
        string $accion,
        ?string $detall = null,
        ?int $usuarioId = null,
        ?string $usuarioNom = null,
        ?string $ip = null
    ): void {
        try {
            if ($usuarioId === null && $usuarioNom === null) {
                $u = Auth::user();
                if ($u) { $usuarioId = (int) $u->id; $usuarioNom = (string) $u->nombre; }
            }
            $ip ??= ($_SERVER['REMOTE_ADDR'] ?? null);

            Database::getInstance()->insert('audit_log', [
                'usuario_id'  => $usuarioId,
                'usuario_nom' => $usuarioNom !== null ? mb_substr($usuarioNom, 0, 150) : null,
                'accion'      => mb_substr($accion, 0, 40),
                'detall'      => $detall !== null ? mb_substr($detall, 0, 255) : null,
                'ip'          => $ip !== null ? mb_substr((string) $ip, 0, 45) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('[AuditLog] ' . $e->getMessage());
        }
    }

    /** @return list<array<string,mixed>> */
    public static function listRecent(int $limit = 200, ?string $accion = null): array
    {
        try {
            $limit = max(1, min(1000, $limit));
            if ($accion !== null && $accion !== '') {
                return Database::getInstance()->query(
                    "SELECT * FROM audit_log WHERE accion = ? ORDER BY id DESC LIMIT {$limit}",
                    [$accion]
                )->fetchAll();
            }
            return Database::getInstance()->query(
                "SELECT * FROM audit_log ORDER BY id DESC LIMIT {$limit}"
            )->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Esborra registres més antics de $days (purga RGPD). Retorna files esborrades. */
    public static function prune(int $days = 365): int
    {
        try {
            $days = max(30, $days);
            return Database::getInstance()->query(
                "DELETE FROM audit_log WHERE created_at < (NOW() - INTERVAL {$days} DAY)"
            )->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
