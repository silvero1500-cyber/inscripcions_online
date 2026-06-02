<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Singleton PDO con conexión segura a MySQL.
 *
 * Características de seguridad:
 * - Prepared statements reales (ATTR_EMULATE_PREPARES = false)
 * - Modo estricto: lanza excepciones en lugar de retornar false
 * - charset utf8mb4 forzado a nivel de DSN y de conexión
 * - Credenciales leídas de variables de entorno, nunca hardcoded
 * - Errores de conexión logueados sin exponer credenciales al usuario
 */
final class Database
{
    private static ?self $instance = null;
    private readonly PDO $pdo;

    private function __construct()
    {
        $cfg = require dirname(__DIR__, 2) . '/config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            $cfg['port'],
            $cfg['database']
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'",
        ];

        try {
            $this->pdo = new PDO($dsn, $cfg['user'], $cfg['password'], $options);
        } catch (PDOException $e) {
            error_log('[DB] Fallo de conexión: ' . $e->getMessage());
            throw new RuntimeException('No se pudo conectar a la base de datos. Inténtalo de nuevo.');
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Ejecuta una consulta preparada y devuelve el Statement.
     *
     * Uso:
     *   $stmt = DB::getInstance()->query('SELECT * FROM usuarios WHERE email = ?', [$email]);
     *   $rows = $stmt->fetchAll();
     *
     * @param list<mixed>|array<string,mixed> $params
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Inserta una fila y devuelve el último ID insertado.
     *
     * @param array<string,mixed> $data  Columna => valor
     */
    public function insert(string $table, array $data): int
    {
        $table   = $this->quoteIdentifier($table);
        $columns = implode(', ', array_map($this->quoteIdentifier(...), array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $stmt = $this->pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
        $stmt->execute(array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Actualiza filas y devuelve el número de filas afectadas.
     *
     * @param array<string,mixed> $data   Columna => nuevo valor
     * @param array<string,mixed> $where  Columna => valor de filtro (AND entre todos)
     */
    public function update(string $table, array $data, array $where): int
    {
        $table = $this->quoteIdentifier($table);

        $set = implode(', ', array_map(
            fn(string $col) => $this->quoteIdentifier($col) . ' = ?',
            array_keys($data)
        ));

        $conditions = implode(' AND ', array_map(
            fn(string $col) => $this->quoteIdentifier($col) . ' = ?',
            array_keys($where)
        ));

        $params = [...array_values($data), ...array_values($where)];

        $stmt = $this->pdo->prepare("UPDATE {$table} SET {$set} WHERE {$conditions}");
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    // ── Transacciones ────────────────────────────────────────

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }

    /**
     * Ejecuta un callable dentro de una transacción.
     * Si ya hay una transacción activa (llamada anidada), delega en ella sin
     * abrir otra; el commit/rollback lo gestiona la transacción más externa.
     */
    public function transaction(callable $fn): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $fn($this);
        }
        $this->beginTransaction();
        try {
            $result = $fn($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    // ── Helpers internos ─────────────────────────────────────

    private function quoteIdentifier(string $name): string
    {
        // Solo permite letras, números y guiones bajos para evitar inyección de identificadores
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new RuntimeException("Nombre de columna/tabla no válido: '{$name}'");
        }
        return "`{$name}`";
    }

    // Prevenir clonado y deserialización del singleton
    private function __clone() {}

    public function __wakeup(): never
    {
        throw new RuntimeException('No se puede deserializar el singleton Database.');
    }
}
