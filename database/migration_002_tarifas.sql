-- ============================================================
-- Migración 002 — Tarifas múltiples por evento
-- ============================================================

-- 1. Nueva tabla de tarifas
CREATE TABLE IF NOT EXISTS `tarifas_evento` (
    `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `evento_id`     INT UNSIGNED     NOT NULL,
    `nombre`        VARCHAR(100)     NOT NULL COMMENT 'Adult, Infantil, VIP...',
    `descripcion`   VARCHAR(500)     NULL,
    `precio`        DECIMAL(8,2)     NOT NULL,
    `aforo_maximo`  INT UNSIGNED     NULL COMMENT 'NULL = sense límit propi (usa l aforament del esdeveniment)',
    `fecha_inicio`  DATETIME         NULL COMMENT 'Disponible des de (NULL = sempre)',
    `fecha_fin`     DATETIME         NULL COMMENT 'Disponible fins a (NULL = sempre)',
    `orden`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `activo`        TINYINT(1)       NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_evento_orden` (`evento_id`, `orden`),
    KEY `idx_activo` (`activo`),
    CONSTRAINT `fk_tarifa_evento`
        FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Añadir tarifa_id a inscritos
ALTER TABLE `inscritos`
    ADD COLUMN `tarifa_id` INT UNSIGNED NOT NULL AFTER `evento_id`,
    ADD CONSTRAINT `fk_inscrito_tarifa`
        FOREIGN KEY (`tarifa_id`) REFERENCES `tarifas_evento` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    ADD INDEX `idx_tarifa` (`tarifa_id`);

-- 3. Eliminar columnas obsoletas de eventos
ALTER TABLE `eventos`
    DROP COLUMN `precio`,
    DROP COLUMN `precio_incluye_iva`;
