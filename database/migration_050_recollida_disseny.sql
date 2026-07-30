-- Migration 050: disseny de la pantalla de recollida (lectura QR) per event.
-- 1 = clàssic (dorsal + talla, DNI, tarifa) · 2 = mostrador (dorsal, DNI,
-- tarifa, tipus xip; sense talla). Ampliable a més dissenys en el futur.
ALTER TABLE `eventos`
    ADD COLUMN `recollida_disseny` TINYINT UNSIGNED NOT NULL DEFAULT 1
        COMMENT 'Disseny de la pantalla de recollida (lectura QR)' AFTER `campos_orden`;
