-- Migration 046: correu de contacte opcional a les incidències.
ALTER TABLE `incidencias`
    ADD COLUMN `email` VARCHAR(255) NULL AFTER `missatge`;
