-- ============================================================
-- Migration 016: Inscripcions de grup (N inscrits en un sol pagament)
--   - pedidos: comanda que agrupa N inscrits amb un contacte i un pagament
--   - inscritos.pedido_id: a quin pedido pertany (NULL = inscripció individual antiga)
--   - pagos.pedido_id: el pagament passa a referenciar el pedido
--   - eventos.max_participantes: límit de participants per inscripció (NULL/1 = individual)
-- ============================================================

CREATE TABLE IF NOT EXISTS `pedidos` (
    `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `token`         VARCHAR(64)   NOT NULL,
    `evento_id`     INT UNSIGNED  NOT NULL,
    `email`         VARCHAR(255)  NOT NULL,
    `nombre`        VARCHAR(150)  NULL,
    `telefono`      VARCHAR(20)   NULL,
    `importe_total` DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    `estado`        ENUM('pendiente','confirmado','cancelado') NOT NULL DEFAULT 'pendiente',
    `locale`        VARCHAR(5)    NULL,
    `ip_registro`   VARCHAR(45)   NULL,
    `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pedido_token` (`token`),
    KEY `idx_pedido_evento` (`evento_id`),
    CONSTRAINT `fk_pedido_evento`
        FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `inscritos`
    ADD COLUMN `pedido_id` INT UNSIGNED NULL AFTER `tarifa_id`,
    ADD KEY `idx_inscrito_pedido` (`pedido_id`),
    ADD CONSTRAINT `fk_inscrito_pedido`
        FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `pagos`
    ADD COLUMN `pedido_id` INT UNSIGNED NULL AFTER `inscrito_id`,
    ADD KEY `idx_pago_pedido` (`pedido_id`),
    ADD CONSTRAINT `fk_pago_pedido`
        FOREIGN KEY (`pedido_id`) REFERENCES `pedidos` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `eventos`
    ADD COLUMN `max_participantes` SMALLINT UNSIGNED NULL;
