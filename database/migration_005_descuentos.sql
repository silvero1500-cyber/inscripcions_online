-- ============================================================
-- Migration 005: descuentos por evento (cupones de descuento)
-- ============================================================

CREATE TABLE IF NOT EXISTS `descuentos_evento` (
    `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `evento_id`       INT UNSIGNED     NOT NULL,
    `codigo`          VARCHAR(40)      NOT NULL COMMENT 'Codi únic dintre del evento, sempre majúscules',
    `porcentaje`      DECIMAL(5,2)     NOT NULL COMMENT '0.01-100.00 = % de descompte',
    `usos_max`        INT UNSIGNED     NULL COMMENT 'NULL = sense límit; sino N usos totals',
    `usos_actuales`   INT UNSIGNED     NOT NULL DEFAULT 0,
    `valido_desde`    DATETIME         NULL,
    `valido_hasta`    DATETIME         NULL,
    `activo`          TINYINT(1)       NOT NULL DEFAULT 1,
    `lote`            VARCHAR(40)      NULL COMMENT 'Identificador del lot de generació',
    `nota`            VARCHAR(255)     NULL,
    `created_at`      TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_evento_codigo` (`evento_id`, `codigo`),
    KEY `idx_lote` (`lote`),
    KEY `idx_activo` (`activo`),
    CONSTRAINT `fk_descuento_evento`
        FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `inscritos`
    ADD COLUMN `descuento_id`          INT UNSIGNED NULL AFTER `tarifa_id`,
    ADD COLUMN `descuento_codigo`      VARCHAR(40)  NULL AFTER `descuento_id`,
    ADD COLUMN `descuento_porcentaje`  DECIMAL(5,2) NULL AFTER `descuento_codigo`,
    ADD INDEX `idx_descuento_id` (`descuento_id`),
    ADD CONSTRAINT `fk_inscrito_descuento`
        FOREIGN KEY (`descuento_id`) REFERENCES `descuentos_evento` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;
