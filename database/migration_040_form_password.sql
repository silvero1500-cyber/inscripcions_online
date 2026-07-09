-- ============================================================
-- Migration 040: contrasenya d'accés opcional al formulari públic
-- ============================================================

ALTER TABLE `eventos`
    ADD COLUMN `form_password` VARCHAR(100) NULL
        COMMENT 'Si té valor, el formulari public demana aquesta contrasenya abans de mostrar-se'
        AFTER `descuentos_activos`;
