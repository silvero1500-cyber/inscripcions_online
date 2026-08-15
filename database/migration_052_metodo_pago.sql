-- Migration 052: mètode de pagament per inscrit (Targeta / Bizum / Transferència…).
-- S'omple en imports amb dades de pagament; per als pagaments Redsys reals el KPI
-- continua deduint TPV/Manual si aquesta columna és buida.
ALTER TABLE `inscritos`
    ADD COLUMN `metodo_pago` VARCHAR(30) NULL DEFAULT NULL
        COMMENT 'Mètode de pagament (Targeta/Bizum/Transferència), si es coneix' AFTER `origen`;
