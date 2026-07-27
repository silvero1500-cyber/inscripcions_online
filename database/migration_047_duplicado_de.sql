-- Migration 047: marcar inscripcions com a duplicades d'una altra.
-- `duplicado_de` apunta a l'ID de la inscripció BONA (la que es queda).
-- Quan s'omple, l'inscrit es posa a estat 'cancelado' (surt d'aforament/KPIs)
-- però es conserva perquè el seu QR segueixi resolent a la inscripció bona.
ALTER TABLE `inscritos`
    ADD COLUMN `duplicado_de` INT UNSIGNED NULL DEFAULT NULL AFTER `estado`,
    ADD KEY `idx_duplicado_de` (`duplicado_de`),
    ADD CONSTRAINT `fk_inscritos_duplicado_de`
        FOREIGN KEY (`duplicado_de`) REFERENCES `inscritos` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;
