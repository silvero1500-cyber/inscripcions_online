-- ============================================================
-- Migration 003: poblacion + codigo_postal en inscritos
-- Necesario para los KPIs del organizador (filtros por ciudad)
-- ============================================================

ALTER TABLE `inscritos`
    ADD COLUMN `poblacion`      VARCHAR(120) NULL AFTER `talla_camiseta`,
    ADD COLUMN `codigo_postal`  VARCHAR(10)  NULL AFTER `poblacion`,
    ADD INDEX `idx_poblacion` (`poblacion`);
