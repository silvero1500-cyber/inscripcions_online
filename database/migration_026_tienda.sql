-- Migration 026: Tienda (botiga) — productes (simples i variables), galeria,
-- atributs/variacions, comandes i línies. Recollida en tienda (sense enviament).
-- Productes gestionats NOMÉS pel superadmin. Pagament reutilitza Redsys (pagos).

-- Producte
CREATE TABLE IF NOT EXISTS `tienda_productos` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `nombre`      VARCHAR(255)  NOT NULL,
    `slug`        VARCHAR(255)  NOT NULL,
    `descripcion` TEXT          NULL,
    `tipo`        ENUM('simple','variable') NOT NULL DEFAULT 'simple',
    `precio`      DECIMAL(8,2)  NOT NULL DEFAULT 0 COMMENT 'Preu (simple) o referència "des de" (variable)',
    `stock`       INT           NULL COMMENT 'Només simple. NULL = il·limitat',
    `activo`      TINYINT(1)    NOT NULL DEFAULT 1,
    `destacado`   TINYINT(1)    NOT NULL DEFAULT 0,
    `orden`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug` (`slug`),
    KEY `idx_activo_orden` (`activo`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Galeria d'imatges (diverses per producte)
CREATE TABLE IF NOT EXISTS `tienda_producto_imagenes` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `producto_id` INT UNSIGNED  NOT NULL,
    `ruta`        VARCHAR(500)  NOT NULL COMMENT 'Ruta relativa dins /public/uploads/',
    `orden`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_producto` (`producto_id`, `orden`),
    CONSTRAINT `fk_timg_producto` FOREIGN KEY (`producto_id`)
        REFERENCES `tienda_productos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Atributs d'un producte variable (Color, Talla...) amb els seus valors
CREATE TABLE IF NOT EXISTS `tienda_producto_atributos` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `producto_id` INT UNSIGNED  NOT NULL,
    `nombre`      VARCHAR(100)  NOT NULL COMMENT 'Ex: Color, Talla',
    `valores`     JSON          NOT NULL COMMENT '["S","M","L"]',
    `orden`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_producto` (`producto_id`, `orden`),
    CONSTRAINT `fk_tattr_producto` FOREIGN KEY (`producto_id`)
        REFERENCES `tienda_productos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Variacions (combinacions) amb preu i stock propis
CREATE TABLE IF NOT EXISTS `tienda_producto_variaciones` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `producto_id` INT UNSIGNED  NOT NULL,
    `combinacion` JSON          NOT NULL COMMENT '{"Color":"Roig","Talla":"M"}',
    `sku`         VARCHAR(100)  NULL,
    `precio`      DECIMAL(8,2)  NOT NULL,
    `stock`       INT           NULL COMMENT 'NULL = il·limitat',
    `activo`      TINYINT(1)    NOT NULL DEFAULT 1,
    `orden`       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_producto` (`producto_id`, `orden`),
    CONSTRAINT `fk_tvar_producto` FOREIGN KEY (`producto_id`)
        REFERENCES `tienda_productos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comandes de botiga (independents de les inscripcions)
CREATE TABLE IF NOT EXISTS `tienda_pedidos` (
    `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `codigo`      VARCHAR(20)   NOT NULL COMMENT 'Referència pública',
    `nombre`      VARCHAR(150)  NOT NULL,
    `email`       VARCHAR(255)  NOT NULL,
    `telefono`    VARCHAR(20)   NULL,
    `total`       DECIMAL(10,2) NOT NULL,
    `estado`      ENUM('pendiente','pagado','cancelado','entregado') NOT NULL DEFAULT 'pendiente',
    `notas`       VARCHAR(500)  NULL,
    `recollit_at` DATETIME      NULL,
    `locale`      VARCHAR(5)    NULL,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_codigo` (`codigo`),
    KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Línies de comanda (snapshot de nom/variant/preu)
CREATE TABLE IF NOT EXISTS `tienda_pedido_lineas` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `pedido_id`    INT UNSIGNED  NOT NULL,
    `producto_id`  INT UNSIGNED  NULL,
    `variacion_id` INT UNSIGNED  NULL,
    `nombre`       VARCHAR(255)  NOT NULL,
    `variante`     VARCHAR(255)  NULL COMMENT 'Ex: Roig / M',
    `precio_unit`  DECIMAL(8,2)  NOT NULL,
    `cantidad`     INT UNSIGNED  NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_pedido` (`pedido_id`),
    CONSTRAINT `fk_tlin_pedido` FOREIGN KEY (`pedido_id`)
        REFERENCES `tienda_pedidos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enllaç del pagament Redsys amb una comanda de botiga
ALTER TABLE `pagos`
    ADD COLUMN `tienda_pedido_id` INT UNSIGNED NULL;
