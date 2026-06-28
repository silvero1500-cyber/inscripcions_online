-- ============================================================
-- Migració 035 · Calaix per franja de temps + Early Bird
-- - campos_personalizados.calaix_map: mapa JSON {opció => calaix 1..4}
--   (per al camp 'franja_temps': cada franja → un calaix de sortida amb color).
-- - inscritos.early_bird: marca manual (sí/no) que es mostra a recollida.
-- ============================================================

ALTER TABLE `campos_personalizados`
    ADD COLUMN `calaix_map` JSON NULL COMMENT 'Mapa {opció => calaix 1..4} per a la sortida';

ALTER TABLE `inscritos`
    ADD COLUMN `early_bird` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Inscripció early bird (marca manual)';
