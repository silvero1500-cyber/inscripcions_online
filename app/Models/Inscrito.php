<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Inscrito
{
    public const TALLAS = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
    public const SEXOS  = ['H', 'M', 'NB'];

    /** Minuts que una inscripció pot estar 'pendiente' (sense pagar) abans de caducar. */
    public const PENDING_EXPIRY_MINUTES = 30;

    /**
     * Caduca les inscripcions 'pendiente' abandonades d'un esdeveniment: les que
     * fa més de N minuts que es van crear i NO tenen cap pagament completat (ni
     * pròpia ni via el seu pedido de grup). Les passa a 'cancelado' per alliberar
     * la plaça (l'aforament només compta pendiente+confirmado).
     *
     * S'executa de forma oportunista (a l'inscriure's i en mostrar l'esdeveniment),
     * així no cal cap cron. Idempotent i tolerant a errors.
     *
     * @return int Nombre d'inscripcions caducades.
     */
    public static function expirarPendientes(int $eventoId, ?int $minutes = null): int
    {
        $min = $minutes ?? self::PENDING_EXPIRY_MINUTES;
        if ($min <= 0 || $eventoId <= 0) return 0;

        try {
            $db = Database::getInstance();
            // Pendents caducats sense cap pagament completat (ni propi ni del grup).
            // S'ESBORREN (abans es marcaven 'cancelado' i s'acumulaven).
            $ids = $db->query(
                "SELECT i.id FROM inscritos i
                 WHERE i.evento_id = ?
                   AND i.estado = 'pendiente'
                   AND i.created_at < (NOW() - INTERVAL ? MINUTE)
                   AND NOT EXISTS (
                       SELECT 1 FROM pagos p
                       WHERE p.estado = 'completado'
                         AND (p.inscrito_id = i.id
                              OR (i.pedido_id IS NOT NULL AND p.pedido_id = i.pedido_id))
                   )",
                [$eventoId, $min]
            )->fetchAll(\PDO::FETCH_COLUMN);

            if (!$ids) return 0;
            $in = implode(',', array_map('intval', $ids));

            return $db->transaction(function () use ($db, $in, $ids) {
                // pagos primer (FK RESTRICT); inscrito_campos_valores cau en cascada
                $db->query("DELETE FROM pagos WHERE inscrito_id IN ($in)");
                $db->query("DELETE FROM inscritos WHERE id IN ($in)");
                return count($ids);
            });
        } catch (\Throwable $e) {
            error_log('[Inscrito::expirarPendientes] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Decodifica la config `eventos.tallas_sexo` (JSON {"H":[...],"M":[...]}).
     * Només retorna sexes amb restricció real (llista no buida i vàlida).
     * @return array<string, list<string>>
     */
    public static function tallasSexoDecode(?string $json): array
    {
        if (empty($json)) return [];
        $data = json_decode($json, true);
        if (!is_array($data)) return [];
        $out = [];
        foreach (['H', 'M'] as $s) {
            if (!empty($data[$s]) && is_array($data[$s])) {
                $list = array_values(array_intersect(self::TALLAS, $data[$s]));
                if ($list) $out[$s] = $list;
            }
        }
        return $out;
    }

    /**
     * Talles disponibles per a un sexe segons la config de l'esdeveniment.
     * Sense restricció (o sexe 'NB'/buit) → totes.
     * @return list<string>
     */
    public static function tallasParaSexo(?string $json, ?string $sexo): array
    {
        $map = self::tallasSexoDecode($json);
        if ($sexo !== null && isset($map[$sexo]) && $map[$sexo]) {
            return $map[$sexo];
        }
        return self::TALLAS;
    }

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
     * ¿Hay ya un inscrito activo (pendiente o confirmado) con este DNI o email
     * en el mismo evento? (Disponible como helper; no se aplica al alta pública.)
     */
    public static function existeDuplicado(int $eventoId, string $dni, string $email): bool
    {
        $row = Database::getInstance()->query(
            "SELECT 1 FROM inscritos
             WHERE evento_id = ?
               AND estado IN ('pendiente', 'confirmado')
               AND (dni = ? OR email = ?)
             LIMIT 1",
            [$eventoId, $dni, $email]
        )->fetchColumn();
        return (bool) $row;
    }

    /**
     * Inscrits CONFIRMATS que coincideixen amb un DNI o un email (per recuperar
     * el comprovant). El mateix valor es compara contra dni (majúscules) i email
     * (minúscules), així funciona tant si l'usuari escriu el DNI com el correu.
     *
     * @return list<array<string,mixed>>
     */
    public static function findConfirmadosByDniOrEmail(string $needle, ?int $eventoId = null): array
    {
        $needle = trim($needle);
        if ($needle === '') return [];

        $sql = "SELECT * FROM inscritos
                WHERE estado = 'confirmado' AND (dni = ? OR email = ?)";
        $params = [strtoupper($needle), strtolower($needle)];

        if ($eventoId !== null) {
            $sql .= " AND evento_id = ?";
            $params[] = $eventoId;
        }
        $sql .= " ORDER BY id DESC LIMIT 20";

        return Database::getInstance()->query($sql, $params)->fetchAll();
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
        } elseif (empty($filters['_allEstados'])) {
            // Per defecte el llistat no mostra cancel·lats ni pendents
            // (es poden veure triant-los explícitament al filtre d'estat)
            $where[] = "i.estado NOT IN ('cancelado','pendiente')";
        }
        if (!empty($filters['club'])) {
            $where[] = 'i.club LIKE ?'; $params[] = '%' . $filters['club'] . '%';
        }
        // Filtre de recollida de dorsal: 'fet' (ja recollit) | 'pendent' (encara no)
        if (!empty($filters['recollida'])) {
            if ($filters['recollida'] === 'fet') {
                $where[] = 'i.dorsal_recollit_at IS NOT NULL';
            } elseif ($filters['recollida'] === 'pendent') {
                $where[] = 'i.dorsal_recollit_at IS NULL';
            }
        }
        if (!empty($filters['search'])) {
            $where[] = '(i.nombre LIKE ? OR i.apellido LIKE ? OR i.email LIKE ? OR i.dni LIKE ? OR CAST(i.numero_dorsal AS CHAR) LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
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
        // Tope alt: el llistat paginat fa servir 25/pàgina, però l'export reutilitza
        // aquest mètode i necessita poder treure'ls tots (no només 500).
        $perPage = max(10, min(50000, $perPage));
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
        // No filtrem per estat (volem el desglossament complet del filtre actual,
        // incloent cancel·lats i pendents per als KPIs encara que el llistat els amagui)
        $filtersNoEstado = $filters;
        unset($filtersNoEstado['estado']);
        $filtersNoEstado['_allEstados'] = true;
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
     * Conta els inscrits que ja han recollit el dorsal, respectant els filtres.
     */
    public static function countRecollitsForAdmin(array $filters = []): int
    {
        [$whereSql, $params] = self::buildAdminWhere($filters);
        $clause = $whereSql !== ''
            ? $whereSql . ' AND i.dorsal_recollit_at IS NOT NULL'
            : 'WHERE i.dorsal_recollit_at IS NOT NULL';
        return (int) Database::getInstance()->query(
            "SELECT COUNT(*) FROM inscritos i {$clause}",
            $params
        )->fetchColumn();
    }

    /**
     * @deprecated Usat per l'export CSV. Usa listForAdmin amb perPage alt.
     */
    public static function listForAdminExport(array $filters = [], int $limit = 50000): array
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
            'check_in_at'  => gmdate('Y-m-d H:i:s'), // UTC (es mostra convertit a local)
            'check_in_por' => $usuarioId,
        ], ['id' => $id]);
        return true;
    }

    // ── Recollida de dorsal ──────────────────────────────────

    /**
     * Marca la recollida del dorsal (timestamp + usuari). Idempotent:
     * si ja estava recollit, no torna a tocar el timestamp i retorna false.
     */
    public static function marcarRecollida(int $id, int $usuarioId): bool
    {
        $db = Database::getInstance();
        $row = $db->query('SELECT dorsal_recollit_at FROM inscritos WHERE id = ?', [$id])->fetch();
        if (!$row || !empty($row['dorsal_recollit_at'])) {
            return false;
        }
        $db->update('inscritos', [
            'dorsal_recollit_at'  => gmdate('Y-m-d H:i:s'), // UTC (es mostra convertit a local)
            'dorsal_recollit_por' => $usuarioId,
        ], ['id' => $id]);
        return true;
    }

    /**
     * Desfà la recollida (per corregir un error).
     */
    public static function desferRecollida(int $id): void
    {
        Database::getInstance()->update('inscritos', [
            'dorsal_recollit_at'  => null,
            'dorsal_recollit_por' => null,
        ], ['id' => $id]);
    }

    /**
     * Assigna (o esborra, amb null) el número de dorsal d'un inscrit.
     */
    public static function setDorsal(int $id, ?int $dorsal): void
    {
        Database::getInstance()->update('inscritos', ['numero_dorsal' => $dorsal], ['id' => $id]);
    }

    /**
     * ¿El dorsal ja l'usa un ALTRE inscrit del mateix esdeveniment?
     * (Per validar abans d'assignar i donar un missatge clar.)
     */
    public static function dorsalEnUs(int $eventoId, int $dorsal, int $exceptId): bool
    {
        return (bool) Database::getInstance()->query(
            'SELECT 1 FROM inscritos WHERE evento_id = ? AND numero_dorsal = ? AND id <> ? LIMIT 1',
            [$eventoId, $dorsal, $exceptId]
        )->fetchColumn();
    }

    /**
     * Comptadors de recollida d'un esdeveniment (només confirmats).
     * @return array{total:int, recollits:int, pendents:int}
     */
    public static function recollidaStats(int $eventoId): array
    {
        $row = Database::getInstance()->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN dorsal_recollit_at IS NOT NULL THEN 1 ELSE 0 END) AS recollits
             FROM inscritos
             WHERE evento_id = ? AND estado = 'confirmado'",
            [$eventoId]
        )->fetch();

        $total     = (int) ($row['total'] ?? 0);
        $recollits = (int) ($row['recollits'] ?? 0);
        return ['total' => $total, 'recollits' => $recollits, 'pendents' => $total - $recollits];
    }

    /**
     * Desglossament de talles que encara NO s'han entregat (confirmats pendents
     * de recollir). Per preparar el material del mostrador.
     * @return array<string,int>  [talla => quantitat], ordenat per talla
     */
    public static function tallasPendents(int $eventoId): array
    {
        $rows = Database::getInstance()->query(
            "SELECT COALESCE(talla_camiseta, '—') AS talla, COUNT(*) AS n
             FROM inscritos
             WHERE evento_id = ? AND estado = 'confirmado' AND dorsal_recollit_at IS NULL
             GROUP BY talla",
            [$eventoId]
        )->fetchAll();

        // Ordenar segons l'ordre natural de talles (XS<S<M<...), '—' al final
        $orden = array_flip(self::TALLAS);
        usort($rows, function ($a, $b) use ($orden) {
            $pa = $orden[$a['talla']] ?? 99;
            $pb = $orden[$b['talla']] ?? 99;
            return $pa <=> $pb;
        });

        $out = [];
        foreach ($rows as $r) $out[(string) $r['talla']] = (int) $r['n'];
        return $out;
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
