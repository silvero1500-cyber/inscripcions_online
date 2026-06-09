-- ============================================================
-- Migration 018: camp condicional per a VARIES tarifes
--   campos_personalizados.tarifa_ids: JSON amb la llista d'ids de tarifa per a
--   les quals es mostra el camp (buit = totes). Substitueix l'ús de tarifa_id
--   (que es manté com a fallback per a camps ja desats).
-- ============================================================

ALTER TABLE `campos_personalizados`
    ADD COLUMN `tarifa_ids` VARCHAR(255) NULL AFTER `tarifa_id`;
