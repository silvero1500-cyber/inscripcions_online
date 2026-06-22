-- Migration 030: preus per trams de data (inscripció anticipada / early bird).
-- Cada tarifa pot tenir N trams: "fins una data → un preu". El preu vigent és el
-- del tram amb la data límit més propera que encara no ha passat; si no n'hi ha,
-- el tram sense data; si tampoc, el preu base de la tarifa (tarifas_evento.precio).

CREATE TABLE IF NOT EXISTS `tarifa_precios` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tarifa_id`   INT UNSIGNED NOT NULL,
    `precio`      DECIMAL(8,2) NOT NULL,
    `fecha_hasta` DATE         NULL COMMENT 'Vàlid fins aquest dia (inclòs). NULL = tram final/per defecte',
    `orden`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_tarifa` (`tarifa_id`, `orden`),
    CONSTRAINT `fk_tprecio_tarifa` FOREIGN KEY (`tarifa_id`)
        REFERENCES `tarifas_evento` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Preu bloquejat en el moment de la inscripció (el que es mostra = el que es cobra)
ALTER TABLE `inscritos` ADD COLUMN `precio_aplicado` DECIMAL(8,2) NULL;
