-- ============================================================
-- Migration 014: Recollida de dorsal (entrega de dorsal/material)
-- Estat independent del check-in del dia de la cursa.
-- ============================================================
ALTER TABLE `inscritos`
    ADD COLUMN `dorsal_recollit_at`  DATETIME     NULL AFTER `check_in_por`,
    ADD COLUMN `dorsal_recollit_por` INT UNSIGNED NULL AFTER `dorsal_recollit_at`,
    ADD CONSTRAINT `fk_inscrito_recollida_por`
        FOREIGN KEY (`dorsal_recollit_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;
