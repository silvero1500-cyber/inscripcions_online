-- ============================================================
-- Migration 013: grups d'aforament compartit entre tarifes
--   Diverses tarifes poden restar d'un mateix cupo (grup).
--   Una tarifa amb grup ignora el seu aforo propi.
-- ============================================================

CREATE TABLE IF NOT EXISTS `grupos_aforo` (
    `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `evento_id`    INT UNSIGNED     NOT NULL,
    `nombre`       VARCHAR(100)     NOT NULL,
    `aforo_maximo` INT UNSIGNED     NOT NULL,
    `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_evento` (`evento_id`),
    CONSTRAINT `fk_grupo_evento`
        FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `tarifas_evento`
    ADD COLUMN `grupo_aforo_id` INT UNSIGNED NULL AFTER `aforo_maximo`,
    ADD KEY `idx_grupo_aforo` (`grupo_aforo_id`),
    ADD CONSTRAINT `fk_tarifa_grupo`
        FOREIGN KEY (`grupo_aforo_id`) REFERENCES `grupos_aforo` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;
