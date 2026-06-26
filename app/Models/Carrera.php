<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Carrera (marca): agrupa diverses edicions per any. Cada `evento` és una
 * edició d'una carrera. La barra superior de l'admin llista les carreres
 * actives i, en clicar-ne una, s'obre la seva edició activa.
 */
final class Carrera
{
    /**
     * Carreres actives, ordenades per a la barra superior.
     * @return list<array<string,mixed>>
     */
    public static function allActivas(): array
    {
        return Database::getInstance()->query(
            'SELECT * FROM carreres WHERE activa = 1 ORDER BY orden, nombre'
        )->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM carreres WHERE id = ?', [$id])
            ->fetch();
        return $row ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM carreres WHERE slug = ?', [$slug])
            ->fetch();
        return $row ?: null;
    }

    /**
     * L'edició a obrir en clicar la carrera: la de l'any en curs si existeix i
     * no està arxivada; si no, la més recent no arxivada. NULL si no en té cap.
     */
    public static function edicionActiva(int $carreraId): ?array
    {
        $row = Database::getInstance()->query(
            'SELECT * FROM eventos
             WHERE carrera_id = ? AND archivado_at IS NULL
             ORDER BY (anio_edicion = YEAR(CURDATE())) DESC,
                      anio_edicion DESC,
                      fecha_evento DESC
             LIMIT 1',
            [$carreraId]
        )->fetch();
        return $row ?: null;
    }

    /**
     * Totes les edicions d'una carrera (per a un futur selector d'anys).
     * @return list<array<string,mixed>>
     */
    public static function edicionesByCarrera(int $carreraId, bool $incloureArxivades = false): array
    {
        $arch = $incloureArxivades ? '' : ' AND archivado_at IS NULL';
        return Database::getInstance()->query(
            "SELECT * FROM eventos
             WHERE carrera_id = ?{$arch}
             ORDER BY anio_edicion DESC, fecha_evento DESC",
            [$carreraId]
        )->fetchAll();
    }
}
