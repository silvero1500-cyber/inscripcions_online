-- ============================================================
-- Migration 039: origen de l'inscripció (formulari públic o importació)
-- ============================================================

ALTER TABLE `inscritos`
    ADD COLUMN `origen` ENUM('formulario','importacion') NOT NULL DEFAULT 'formulario'
        COMMENT 'formulario = alta pel formulari public; importacion = alta manual per CSV'
        AFTER `estado`;
