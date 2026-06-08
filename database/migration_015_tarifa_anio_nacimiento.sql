-- ============================================================
-- Migration 015: restricció d'any de naixement per tarifa
-- Permet limitar una tarifa a un rang d'anys de naixement
-- (p.ex. infantil: des de 2014; veterans: fins a 1980).
-- Tots dos opcionals (NULL = sense límit per aquell extrem).
-- ============================================================
ALTER TABLE `tarifas_evento`
    ADD COLUMN `anio_nac_min` SMALLINT UNSIGNED NULL AFTER `aforo_maximo`,
    ADD COLUMN `anio_nac_max` SMALLINT UNSIGNED NULL AFTER `anio_nac_min`;
