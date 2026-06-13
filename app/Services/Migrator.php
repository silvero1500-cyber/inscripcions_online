<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

/**
 * Aplicador de migracions SQL segur, pensat per a hosting només-FTP (sense SSH).
 *
 * Substitueix el patró antic de pujar un script PHP amb token a l'arrel web:
 * aquí les migracions s'apliquen des d'una pàgina d'admin protegida (superadmin
 * + CSRF) i només s'executen els fitxers database/migration_*.sql del repositori.
 * El control d'aplicació es guarda a la taula schema_migrations.
 *
 * Baseline: la primera vegada, totes les migracions fins a BASELINE_THROUGH es
 * marquen com aplicades sense executar-les (ja ho estaven en aquest desplegament
 * i també a qualsevol instal·lació feta des de schema.sql). Només les posteriors
 * s'executaran realment.
 */
final class Migrator
{
    /** Última migració que JA estava aplicada quan es va introduir aquest sistema. */
    private const BASELINE_THROUGH = 'migration_023_lista_espera.sql';

    private static function dir(): string
    {
        return BASE_PATH . '/database';
    }

    private static function files(): array
    {
        $files = glob(self::dir() . '/migration_*.sql') ?: [];
        sort($files, SORT_STRING);
        return $files;
    }

    private static function ensureTable(): void
    {
        $db = Database::getInstance();
        $db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS `schema_migrations` (
                `filename`   VARCHAR(255) NOT NULL,
                `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`filename`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Baseline: si encara no hi ha cap registre, marca com aplicades les
        // migracions fins a BASELINE_THROUGH (ja existents en aquest desplegament).
        $count = (int) $db->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
        if ($count === 0) {
            foreach (self::files() as $f) {
                $name = basename($f);
                if (strcmp($name, self::BASELINE_THROUGH) <= 0) {
                    $db->query('INSERT IGNORE INTO schema_migrations (filename) VALUES (?)', [$name]);
                }
            }
        }
    }

    /**
     * Estat de cada migració: aplicada o pendent.
     * @return list<array{filename:string, applied:bool, applied_at:?string}>
     */
    public static function status(): array
    {
        self::ensureTable();
        $applied = Database::getInstance()
            ->query('SELECT filename, applied_at FROM schema_migrations')
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        $out = [];
        foreach (self::files() as $f) {
            $name = basename($f);
            $out[] = [
                'filename'   => $name,
                'applied'    => isset($applied[$name]),
                'applied_at' => $applied[$name] ?? null,
            ];
        }
        return $out;
    }

    /**
     * Aplica totes les migracions pendents en ordre. S'atura a la primera que falli.
     * @return list<array{filename:string, ok:bool, error?:string}>
     */
    public static function applyPending(): array
    {
        self::ensureTable();
        $db = Database::getInstance();
        $applied = $db->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $appliedSet = array_flip($applied);

        $results = [];
        foreach (self::files() as $f) {
            $name = basename($f);
            if (isset($appliedSet[$name])) continue;

            try {
                self::runFile($f);
                $db->query('INSERT INTO schema_migrations (filename) VALUES (?)', [$name]);
                $results[] = ['filename' => $name, 'ok' => true];
            } catch (\Throwable $e) {
                error_log('[Migrator] Fallida ' . $name . ': ' . $e->getMessage());
                $results[] = ['filename' => $name, 'ok' => false, 'error' => $e->getMessage()];
                break; // no continuar si una falla
            }
        }
        return $results;
    }

    /** Executa un fitxer .sql (pot tenir diverses sentències separades per ;). */
    private static function runFile(string $path): void
    {
        $sql = (string) file_get_contents($path);
        // Treure comentaris de línia -- ... per evitar trossos buits en partir per ;
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;

        $pdo = Database::getInstance()->pdo();
        foreach (explode(';', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') continue;
            $pdo->exec($stmt);
        }
    }
}
