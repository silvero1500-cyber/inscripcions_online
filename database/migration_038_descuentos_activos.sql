-- ============================================================
-- Migration 038: flag per activar/desactivar els codis de descompte per evento
-- ============================================================

ALTER TABLE `eventos`
    ADD COLUMN `descuentos_activos` TINYINT(1) NOT NULL DEFAULT 1
        COMMENT '1 = formulari mostra el bloc de codi de descompte; 0 = amagat i ignorat'
        AFTER `inscripciones_abiertas`;
