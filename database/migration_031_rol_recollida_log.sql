-- Migration 031: rol restringit 'recollida' (només recollida de dorsals dels
-- eventos assignats) + registre d'auditoria de les accions de recollida.

ALTER TABLE `usuarios`
    MODIFY COLUMN `rol` ENUM('superadmin','organizador','recollida')
    NOT NULL DEFAULT 'organizador';

CREATE TABLE IF NOT EXISTS `recollida_log` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `evento_id`    INT UNSIGNED NOT NULL,
    `inscrito_id`  INT UNSIGNED NULL,
    `inscrito_nom` VARCHAR(255) NULL COMMENT 'Snapshot del nom de l''inscrit',
    `dorsal`       INT UNSIGNED NULL,
    `usuario_id`   INT UNSIGNED NULL,
    `usuario_nom`  VARCHAR(150) NULL COMMENT 'Snapshot de qui ho ha fet',
    `accion`       VARCHAR(30)  NOT NULL COMMENT 'recollit | desfet | dorsal | dorsal_esborrat',
    `created_at`   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_evento_time` (`evento_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
