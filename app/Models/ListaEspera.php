<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Llista d'espera d'un esdeveniment: correus d'interessats quan no queden places.
 * L'organitzador els avisa manualment quan s'allibera alguna plaça.
 */
final class ListaEspera
{
    /**
     * Afegeix un interessat. Si el correu ja existeix per a aquest esdeveniment,
     * actualitza nom/tarifa i reinicia l'avís (torna a ser "pendent").
     */
    public static function add(int $eventoId, ?int $tarifaId, ?string $nombre, string $email): void
    {
        Database::getInstance()->query(
            'INSERT INTO lista_espera (evento_id, tarifa_id, nombre, email)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                tarifa_id = VALUES(tarifa_id),
                nombre    = VALUES(nombre),
                notificado_at = NULL',
            [$eventoId, $tarifaId, $nombre, $email]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function listByEvento(int $eventoId): array
    {
        return Database::getInstance()->query(
            'SELECT le.*, t.nombre AS tarifa_nombre
             FROM lista_espera le
             LEFT JOIN tarifas_evento t ON t.id = le.tarifa_id
             WHERE le.evento_id = ?
             ORDER BY le.created_at ASC, le.id ASC',
            [$eventoId]
        )->fetchAll();
    }

    /** Interessats encara no avisats. @return list<array<string,mixed>> */
    public static function pendientes(int $eventoId): array
    {
        return Database::getInstance()->query(
            'SELECT le.*, t.nombre AS tarifa_nombre
             FROM lista_espera le
             LEFT JOIN tarifas_evento t ON t.id = le.tarifa_id
             WHERE le.evento_id = ? AND le.notificado_at IS NULL
             ORDER BY le.created_at ASC, le.id ASC',
            [$eventoId]
        )->fetchAll();
    }

    public static function marcarNotificado(int $id): void
    {
        Database::getInstance()->query(
            'UPDATE lista_espera SET notificado_at = NOW() WHERE id = ?',
            [$id]
        );
    }

    public static function countByEvento(int $eventoId): int
    {
        return (int) Database::getInstance()->query(
            'SELECT COUNT(*) FROM lista_espera WHERE evento_id = ?',
            [$eventoId]
        )->fetchColumn();
    }
}
