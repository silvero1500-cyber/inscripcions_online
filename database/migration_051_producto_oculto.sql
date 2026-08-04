-- Migration 051: producte ocult a la botiga. Un producte actiu però ocult NO
-- surt al catàleg públic ni activa el menú "Botiga", però SÍ es pot comprar per
-- URL directa (/tienda/{slug}). Útil per a proves de pagament o vendes privades.
ALTER TABLE `tienda_productos`
    ADD COLUMN `oculto` TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Actiu però no llistat al catàleg (comprable per URL directa)' AFTER `activo`;
