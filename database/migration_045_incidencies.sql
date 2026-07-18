-- Migration 045: taula d'incidències del formulari públic (a més del correu).
CREATE TABLE IF NOT EXISTS `incidencias` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `evento_id`   INT UNSIGNED NULL,
    `evento_nom`  VARCHAR(255) NULL COMMENT 'Nom desnormalitzat per si s''esborra l''event',
    `missatge`    TEXT         NOT NULL,
    `estado`      ENUM('nova','resolta') NOT NULL DEFAULT 'nova',
    `ip`          VARCHAR(45)  NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolt_at`   DATETIME     NULL,
    PRIMARY KEY (`id`),
    KEY `idx_estado_time` (`estado`, `created_at`),
    KEY `idx_evento` (`evento_id`),
    CONSTRAINT `fk_incidencia_evento`
        FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
