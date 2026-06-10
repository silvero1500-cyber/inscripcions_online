-- ============================================================
-- Migration 021: posició del camp personalitzat respecte als estàndard
--   campos_personalizados.antes_estandar: 1 = es mostra ABANS dels camps
--   estàndard del corredor; 0 (per defecte) = després.
-- ============================================================

ALTER TABLE `campos_personalizados`
    ADD COLUMN `antes_estandar` TINYINT(1) NOT NULL DEFAULT 0 AFTER `tarifa_ids`;
