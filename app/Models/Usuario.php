<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Usuario
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly string $email,
        public readonly string $rol,
        public readonly bool $activo,
    ) {}

    public static function findByEmail(string $email): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM usuarios WHERE email = ? LIMIT 1', [$email])
            ->fetch();
        return $row ?: null;
    }

    public static function findById(int $id): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM usuarios WHERE id = ? LIMIT 1', [$id])
            ->fetch();
        return $row ?: null;
    }

    public static function updateUltimoLogin(int $id): void
    {
        Database::getInstance()->query(
            'UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?',
            [$id]
        );
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id:     (int)$row['id'],
            nombre: (string)$row['nombre'],
            email:  (string)$row['email'],
            rol:    (string)$row['rol'],
            activo: (bool)$row['activo'],
        );
    }

    /** @return list<array<string,mixed>> */
    public static function listAll(): array
    {
        return Database::getInstance()->query(
            "SELECT id, nombre, email, rol, activo, ultimo_login, created_at,
                    (SELECT COUNT(*) FROM eventos WHERE propietario_id = usuarios.id) AS num_propios,
                    (
                        (SELECT COUNT(*) FROM eventos WHERE propietario_id = usuarios.id)
                        +
                        (SELECT COUNT(*) FROM organizador_evento oe
                         WHERE oe.usuario_id = usuarios.id
                           AND NOT EXISTS (
                               SELECT 1 FROM eventos e
                               WHERE e.id = oe.evento_id AND e.propietario_id = usuarios.id
                           ))
                    ) AS num_eventos
             FROM usuarios
             ORDER BY rol DESC, nombre ASC"
        )->fetchAll();
    }

    /**
     * Llista d'usuaris actius que poden ser propietaris d'un evento
     * (qualsevol superadmin o organizador actiu, excloent l'usuari $excludeId).
     * @return list<array<string,mixed>>
     */
    public static function listActiveTargetsForTransfer(int $excludeId): array
    {
        return Database::getInstance()->query(
            "SELECT id, nombre, email, rol
             FROM usuarios
             WHERE activo = 1 AND id != ?
             ORDER BY rol DESC, nombre ASC",
            [$excludeId]
        )->fetchAll();
    }

    /**
     * Eventos on aquest usuari és propietari directe.
     * @return list<array<string,mixed>>
     */
    public static function ownedEvents(int $usuarioId): array
    {
        return Database::getInstance()->query(
            "SELECT id, titulo, fecha_evento
             FROM eventos
             WHERE propietario_id = ?
             ORDER BY fecha_evento DESC, titulo ASC",
            [$usuarioId]
        )->fetchAll();
    }

    /**
     * Reassigna tots els eventos propis de $fromId a $toId.
     * Retorna el nombre d'eventos transferits.
     */
    public static function transferOwnedEvents(int $fromId, int $toId): int
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "UPDATE eventos SET propietario_id = ? WHERE propietario_id = ?",
            [$toId, $fromId]
        );
        return $stmt->rowCount();
    }

    /**
     * Crea un nuevo usuario con la contraseña hasheada con bcrypt cost 12.
     * @param array{nombre:string, email:string, password:string, rol:string, activo:int} $data
     */
    public static function create(array $data): int
    {
        return Database::getInstance()->insert('usuarios', [
            'nombre'        => $data['nombre'],
            'email'         => strtolower(trim($data['email'])),
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'rol'           => $data['rol'],
            'activo'        => $data['activo'],
        ]);
    }

    /**
     * Actualiza datos del usuario. Password opcional (si vacío, no se toca).
     * @param array{nombre?:string, email?:string, password?:string, rol?:string, activo?:int} $data
     */
    public static function update(int $id, array $data): void
    {
        $payload = [];
        if (isset($data['nombre']))   $payload['nombre'] = $data['nombre'];
        if (isset($data['email']))    $payload['email']  = strtolower(trim($data['email']));
        if (isset($data['rol']))      $payload['rol']    = $data['rol'];
        if (isset($data['activo']))   $payload['activo'] = $data['activo'];
        if (!empty($data['password'])) {
            $payload['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        if ($payload === []) return;
        Database::getInstance()->update('usuarios', $payload, ['id' => $id]);
    }

    public static function delete(int $id): void
    {
        Database::getInstance()->query('DELETE FROM usuarios WHERE id = ?', [$id]);
    }

    public static function countActiveSuperadmins(): int
    {
        return (int) Database::getInstance()->query(
            "SELECT COUNT(*) FROM usuarios WHERE rol = 'superadmin' AND activo = 1"
        )->fetchColumn();
    }

    /**
     * IDs dels eventos assignats explícitament a aquest usuari via organizador_evento.
     * No inclou els eventos on és propietari directe (això ja li dóna accés).
     * @return list<int>
     */
    public static function assignedEventIds(int $usuarioId): array
    {
        $rows = Database::getInstance()->query(
            'SELECT evento_id FROM organizador_evento WHERE usuario_id = ?',
            [$usuarioId]
        )->fetchAll(\PDO::FETCH_COLUMN);
        return array_map('intval', $rows);
    }

    /**
     * Substitueix les assignacions del usuari pel set indicat.
     * Atòmic: dins de transacció. Si l'usuari és propietari d'un evento,
     * no cal afegir-lo aquí (ja té accés).
     *
     * @param list<int> $eventIds
     */
    public static function setAssignedEvents(int $usuarioId, array $eventIds): void
    {
        $db = Database::getInstance();
        $db->transaction(function ($db) use ($usuarioId, $eventIds): void {
            $db->query('DELETE FROM organizador_evento WHERE usuario_id = ?', [$usuarioId]);
            foreach ($eventIds as $eid) {
                $eid = (int) $eid;
                if ($eid <= 0) continue;
                try {
                    $db->insert('organizador_evento', [
                        'usuario_id'     => $usuarioId,
                        'evento_id'      => $eid,
                        'puede_editar'   => 1,
                        'puede_exportar' => 1,
                    ]);
                } catch (\Throwable $e) {
                    // ignorar si l'evento no existeix (FK fallaria) o duplicat
                }
            }
        });
    }

    public static function emailExists(string $email, ?int $exceptId = null): bool
    {
        $email = strtolower(trim($email));
        if ($exceptId !== null) {
            $row = Database::getInstance()->query(
                'SELECT 1 FROM usuarios WHERE email = ? AND id != ? LIMIT 1',
                [$email, $exceptId]
            )->fetchColumn();
        } else {
            $row = Database::getInstance()->query(
                'SELECT 1 FROM usuarios WHERE email = ? LIMIT 1',
                [$email]
            )->fetchColumn();
        }
        return (bool) $row;
    }
}
