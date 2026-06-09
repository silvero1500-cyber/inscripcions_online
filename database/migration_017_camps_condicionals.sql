-- ============================================================
-- Migration 017: camps condicionals
--   - campos_personalizados.tarifa_id: si està fixat, el camp només es
--     mostra quan s'ha triat aquesta tarifa (NULL = sempre)
--   - eventos.tallas_sexo: JSON amb les talles disponibles per sexe
--     {"H":["S","M",...],"M":["XS","S",...]} (sense valor = totes)
-- ============================================================

ALTER TABLE `campos_personalizados`
    ADD COLUMN `tarifa_id` INT UNSIGNED NULL AFTER `evento_id`,
    ADD KEY `idx_campo_tarifa` (`tarifa_id`),
    ADD CONSTRAINT `fk_campo_tarifa`
        FOREIGN KEY (`tarifa_id`) REFERENCES `tarifas_evento` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `eventos`
    ADD COLUMN `tallas_sexo` VARCHAR(500) NULL;
