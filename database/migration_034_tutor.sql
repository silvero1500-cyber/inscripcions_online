-- ============================================================
-- Migració 034 · Dades del tutor (per a inscripcions infantils)
-- Quan la modalitat triada és només per a menors (anio_nac_min implica
-- < 18 anys), el formulari demana nom, cognoms i DNI del tutor.
-- Columnes nullables: només s'omplen per a inscrits infantils.
-- ============================================================

ALTER TABLE `inscritos`
    ADD COLUMN `tutor_nombre`   VARCHAR(100) NULL AFTER `dni`,
    ADD COLUMN `tutor_apellido` VARCHAR(150) NULL AFTER `tutor_nombre`,
    ADD COLUMN `tutor_dni`      VARCHAR(20)  NULL AFTER `tutor_apellido`;
