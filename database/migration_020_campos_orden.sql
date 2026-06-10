-- ============================================================
-- Migration 020: ordre dels camps estàndard del formulari
--   eventos.campos_orden: JSON amb la llista de claus de camp en l'ordre
--   en què s'han de mostrar (buit = ordre per defecte de CamposFijos).
-- ============================================================

ALTER TABLE `eventos`
    ADD COLUMN `campos_orden` VARCHAR(500) NULL;
