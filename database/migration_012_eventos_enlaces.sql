-- ============================================================
-- Migration 012: enllaços de l'esdeveniment (reglament + web oficial)
-- ============================================================

ALTER TABLE `eventos`
    ADD COLUMN `reglamento_url`  VARCHAR(500) NULL COMMENT 'Enllaç al reglament' AFTER `localizacion`,
    ADD COLUMN `web_oficial_url` VARCHAR(500) NULL COMMENT 'Enllaç a la web oficial' AFTER `reglamento_url`;
