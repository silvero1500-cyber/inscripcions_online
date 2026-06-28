-- ============================================================
-- Migració 036 · "Franja de temps" com a camp fix del corredor
-- - inscritos.franja_temps: el valor (etiqueta) de franja triat.
-- - eventos.franjas_config: JSON [{label, calaix 1..4}] per event
--   (opcions del select + calaix de sortida de cada franja).
-- ============================================================

ALTER TABLE `inscritos`
    ADD COLUMN `franja_temps` VARCHAR(60) NULL COMMENT 'Franja de temps prevista (camp fix)';

ALTER TABLE `eventos`
    ADD COLUMN `franjas_config` JSON NULL COMMENT 'Franges de temps + calaix per event: [{label, calaix}]';
