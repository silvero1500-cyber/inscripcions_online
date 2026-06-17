-- Migration 028: nou estat 'listo' (llest per recollir) per a les comandes de botiga.
-- Cicle: pendiente → pagado (en preparació) → listo (llest per recollir) → entregado (recollit).

ALTER TABLE `tienda_pedidos`
    MODIFY COLUMN `estado`
    ENUM('pendiente','pagado','listo','cancelado','entregado')
    NOT NULL DEFAULT 'pendiente';
