-- Migration 027: permet pagaments de botiga sense inscrit.
-- Les comandes de tenda no tenen inscrito_id; el pagament s'enllaça per
-- tienda_pedido_id (afegit a la 026). Cal que inscrito_id pugui ser NULL.

ALTER TABLE `pagos` MODIFY COLUMN `inscrito_id` INT UNSIGNED NULL;
