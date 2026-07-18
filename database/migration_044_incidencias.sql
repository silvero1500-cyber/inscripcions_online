-- Migration 044: bústia d'incidències al formulari públic, activable per event.
ALTER TABLE `eventos`
    ADD COLUMN `incidencias_activo` TINYINT(1) NOT NULL DEFAULT 0 AFTER `descuentos_activos`;
