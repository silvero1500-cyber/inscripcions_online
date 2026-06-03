-- ============================================================
-- Migration 011: arxivar esdeveniments (en lloc d'esborrar-los)
--   archivado_at NULL = actiu; amb data = arxivat (ocult del llistat i del públic)
-- ============================================================

ALTER TABLE `eventos`
    ADD COLUMN `archivado_at` DATETIME NULL COMMENT 'Si té data, l''esdeveniment està arxivat' AFTER `campos_fijos`;
