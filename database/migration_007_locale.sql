-- ============================================================
-- Migration 007: locale per inscrit (per a emails en idioma correcte)
-- ============================================================

ALTER TABLE `inscritos`
    ADD COLUMN `locale` CHAR(2) NOT NULL DEFAULT 'ca' AFTER `ip_registro`;
