-- ============================================================
-- Migration 008: localització de l'esdeveniment (lloc / adreça)
-- ============================================================

ALTER TABLE `eventos`
    ADD COLUMN `localizacion` VARCHAR(255) NULL COMMENT 'Lloc o adreça de l''esdeveniment' AFTER `descripcion`;
