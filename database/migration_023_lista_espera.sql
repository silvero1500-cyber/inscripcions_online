-- Migration 023: llista d'espera
-- Quan una tarifa o l'aforament està ple, els interessats deixen el seu correu i
-- l'organitzador els pot avisar (manualment) quan s'allibera una plaça.

CREATE TABLE IF NOT EXISTS `lista_espera` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `evento_id`     INT UNSIGNED NOT NULL,
    `tarifa_id`     INT UNSIGNED NULL COMMENT 'Tarifa preferida (opcional)',
    `nombre`        VARCHAR(150) NULL,
    `email`         VARCHAR(255) NOT NULL,
    `notificado_at` DATETIME     NULL COMMENT 'Quan se l''ha avisat que hi ha plaça',
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_evento_email` (`evento_id`, `email`),
    KEY `idx_evento` (`evento_id`),
    CONSTRAINT `fk_le_evento`
        FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_le_tarifa`
        FOREIGN KEY (`tarifa_id`) REFERENCES `tarifas_evento` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
