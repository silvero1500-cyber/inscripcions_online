<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Inscrito
{
    public const TALLAS = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
    public const SEXOS  = ['H', 'M', 'NB'];

    public static function findById(int $id): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM inscritos WHERE id = ?', [$id])
            ->fetch();
        return $row ?: null;
    }

    /**
     * Crea una inscripción con sus valores de campos personalizados, todo en una transacción.
     *
     * @param array<string,mixed>   $datosCorredor   Columnas de la tabla `inscritos`
     * @param array<int,string>     $camposValores   Map [campo_id => valor]
     * @return int  ID de la inscripción creada
     */
    public static function createWithCustomFields(array $datosCorredor, array $camposValores): int
    {
        $db = Database::getInstance();
        return $db->transaction(function ($db) use ($datosCorredor, $camposValores) {
            $inscritoId = $db->insert('inscritos', $datosCorredor);

            foreach ($camposValores as $campoId => $valor) {
                $db->insert('inscrito_campos_valores', [
                    'inscrito_id' => $inscritoId,
                    'campo_id'    => (int) $campoId,
                    'valor'       => $valor,
                ]);
            }

            return $inscritoId;
        });
    }

    /**
     * ¿Ya existe una inscripción activa (pendiente o confirmada) con este DNI
     * en el mismo evento? Identifica a la persona por DNI, de modo que una misma
     * familia (mismo email, distinto DNI) sí puede inscribir a varios.
     * Si no se recoge DNI (null/vacío), no se puede deduplicar → devuelve false.
     */
    public static function existeDuplicado(int $eventoId, ?string $dni): bool
    {
        $dni = strtoupper(trim((string) $dni));
        if ($dni === '') return false;

        $row = Database::getInstance()->query(
            "SELECT 1 FROM inscritos
             WHERE evento_id = ?
               AND estado IN ('pendiente', 'confirmado')
               AND dni = ?
             LIMIT 1",
            [$eventoId, $dni]
        )->fetchColumn();
        return (bool) $row;
    }

    /**
     * Cuenta cuántas plazas hay confirmadas o pendientes (para verificar aforo).
     */
    public static function countActivasByEvento(int $eventoId): int
    {
        return (int) Database::getInstance()->query(
            "SELECT COUNT(*) FROM inscritos
             WHERE evento_id = ?
               AND estado IN ('pendiente', 'confirmado')",
            [$eventoId]
        )->fetchColumn();
    }

    public static function marcarConfirmado(int $id): int
    {
        return Database::getInstance()->update(
            'inscritos',
            ['estado' => 'confirmado'],
            ['id' => $id]
        );
    }

    /**
     * Construeix la part WHERE comuna per a listForAdmin / countForAdmin.
     * @return array{0:string, 1:list<mixed>}
     */
    private static function buildAdminWhere(array $filters): array
    {
        $where  = [];
        $params = [];
        if (!empty($filters['evento_id'])) {
            $where[] = 'i.evento_id = ?'; $params[] = (int) $filters['evento_id'];
        }
        if (!empty($filters['estado'])) {
            $where[] = 'i.estado = ?'; $params[] = (string) $filters['estado'];
        }
        if (!empty($filters['club'])) {
            $where[] = 'i.club LIKE ?'; $params[] = '%' . $filters['club'] . '%';
        }
        if (!empty($filters['search'])) {
            $where[] = '(i.nombre LIKE ? OR i.apellido LIKE ? OR i.email LIKE ? OR i.dni LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        }
        return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
    }

    /**
     * Llistat d'inscrits paginat. Pàgines de $perPage (default 100).
     *
     * @param array{evento_id?:int, estado?:string, search?:string, club?:string} $filters
     * @return list<array<string,mixed>>
     */
    public static function listForAdmin(array $filters = [], int $page = 1, int $perPage = 100): array
    {
        [$whereSql, $params] = self::buildAdminWhere($filters);
        $perPage = max(10, min(500, $perPage));
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        return Database::getInstance()->query(
            "SELECT i.*,
                    e.titulo AS evento_titulo, e.slug AS evento_slug, e.fecha_evento,
                    t.nombre AS tarifa_nombre, t.precio AS tarifa_precio
             FROM inscritos i
             JOIN eventos e ON e.id = i.evento_id
             JOIN tarifas_evento t ON t.id = i.tarifa_id
             {$whereSql}
             ORDER BY i.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        )->fetchAll();
    }

    /**
     * Conta el total d'inscrits que matchejen els filtres (sense paginar).
     */
    public static function countForAdmin(array $filters = []): int
    {
        [$whereSql, $params] = self::buildAdminWhere($filters);
        return (int) Database::getInstance()->query(
            "SELECT COUNT(*) FROM inscritos i {$whereSql}",
            $params
        )->fetchColumn();
    }

    /**
     * Comptes per estat respectant els filtres (excepte el propi filtre d'estat).
     * @return array<string,int>
     */
    public static function countsByEstadoForAdmin(array $filters = []): array
    {
        // No filtrem per estat (volem el desglossament complet del filtre actual)
        $filtersNoEstado = $filters;
        unset($filtersNoEstado['estado']);
        [$whereSql, $params] = self::buildAdminWhere($filtersNoEstado);

        $rows = Database::getInstance()->query(
            "SELECT i.estado, COUNT(*) AS n FROM inscritos i {$whereSql} GROUP BY i.estado",
            $params
        )->fetchAll();

        $out = ['pendiente' => 0, 'confirmado' => 0, 'cancelado' => 0, 'reembolsado' => 0];
        foreach ($rows as $r) $out[(string)$r['estado']] = (int)$r['n'];
        return $out;
    }

    /**
     * @deprecated Usat per l'export CSV. Usa listForAdmin amb perPage alt.
     */
    public static function listForAdminExport(array $filters = [], int $limit = 5000): array
    {
        return self::listForAdmin($filters, 1, $limit);
    }

    /**
     * Busca una inscripción por su qr_token (para check-in con QR).
     */
    public static function findByQrToken(string $token): ?array
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{32,64}$/', $token)) {
            return null;
        }
        $row = Database::getInstance()
            ->query('SELECT * FROM inscritos WHERE qr_token = ?', [$token])
            ->fetch();
        return $row ?: null;
    }

    /**
     * Marca check-in (timestamp + usuario que lo hace). Idempotente:
     * si ya estaba marcado, devuelve false sin tocar nada.
     */
    public static function marcarCheckIn(int $id, int $usuarioId): bool
    {
        $db = Database::getInstance();
        $row = $db->query('SELECT check_in_at FROM inscritos WHERE id = ?', [$id])->fetch();
        if (!$row || !empty($row['check_in_at'])) {
            return false;
        }
        $db->update('inscritos', [
            'check_in_at'  => date('Y-m-d H:i:s'),
            'check_in_por' => $usuarioId,
        ], ['id' => $id]);
        return true;
    }

    /**
     * Asigna un qr_token si no lo tiene aún. Devuelve el token actual o el nuevo.
     */
    public static function ensureQrToken(int $id): string
    {
        $row = Database::getInstance()
            ->query('SELECT qr_token FROM inscritos WHERE id = ?', [$id])
            ->fetch();

        if (!empty($row['qr_token'])) {
            return (string) $row['qr_token'];
        }

        $token = bin2hex(random_bytes(32));
        Database::getInstance()->update('inscritos', ['qr_token' => $token], ['id' => $id]);
        return $token;
    }

    public static function marcarCancelado(int $id): int
    {
        return Database::getInstance()->update(
            'inscritos',
            ['estado' => 'cancelado'],
            ['id' => $id]
        );
    }

    /**
     * Validador específico de DNI español o NIE.
     */
    public static function dniValido(string $dni): bool
    {
        $dni = strtoupper(trim($dni));
        // NIE empieza por X, Y o Z
        $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';

        if (preg_match('/^[XYZ]\d{7}[A-Z]$/', $dni)) {
            $num = (int) strtr(substr($dni, 0, 8), ['X' => '0', 'Y' => '1', 'Z' => '2']);
            return $letras[$num % 23] === $dni[8];
        }
        if (preg_match('/^\d{8}[A-Z]$/', $dni)) {
            $num = (int) substr($dni, 0, 8);
            return $letras[$num % 23] === $dni[8];
        }
        return false;
    }
}
