-- ============================================================
-- SaaS Inscripcions Online - Esquema de Base de Datos
-- Motor: InnoDB | Charset: utf8mb4_unicode_ci
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

-- ------------------------------------------------------------
-- 1. USUARIOS (Superadmin + Organizadors)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `nombre`         VARCHAR(100)     NOT NULL,
    `email`          VARCHAR(255)     NOT NULL,
    `password_hash`  VARCHAR(255)     NOT NULL,
    `rol`            ENUM('superadmin','organizador') NOT NULL DEFAULT 'organizador',
    `activo`         TINYINT(1)       NOT NULL DEFAULT 1,
    `ultimo_login`   DATETIME         NULL,
    `token_reset`    VARCHAR(100)     NULL COMMENT 'Token para reset de contraseña',
    `token_reset_at` DATETIME         NULL,
    `created_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_email` (`email`),
    KEY `idx_rol` (`rol`),
    KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. EVENTOS (Carreras populars)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `eventos` (
    `id`                        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `propietario_id`            INT UNSIGNED    NOT NULL COMMENT 'Organizador principal del evento',
    `titulo`                    VARCHAR(255)    NOT NULL,
    `slug`                      VARCHAR(255)    NOT NULL,
    `descripcion`               LONGTEXT        NULL,
    `localizacion`              VARCHAR(255)    NULL COMMENT 'Lloc o adreça de l''esdeveniment',
    `reglamento_url`            VARCHAR(500)    NULL COMMENT 'Enllaç al reglament',
    `web_oficial_url`           VARCHAR(500)    NULL COMMENT 'Enllaç a la web oficial',
    `fecha_evento`              DATE            NOT NULL,
    `fecha_limite_inscripcion`  DATETIME        NULL,
    `aforo_maximo`              INT UNSIGNED    NULL COMMENT 'NULL = sin límite',
    `imagen_portada`            VARCHAR(500)    NULL,
    `activo`                    TINYINT(1)      NOT NULL DEFAULT 1,
    `inscripciones_abiertas`    TINYINT(1)      NOT NULL DEFAULT 1,
    `campos_fijos`              JSON            NULL COMMENT 'Config per camp fix: obligatori/opcional/ocult',
    `archivado_at`              DATETIME        NULL COMMENT 'Si té data, esdeveniment arxivat',
    `created_at`                TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_slug` (`slug`),
    KEY `idx_propietario` (`propietario_id`),
    KEY `idx_fecha_evento` (`fecha_evento`),
    KEY `idx_activo` (`activo`),
    CONSTRAINT `fk_evento_propietario`
        FOREIGN KEY (`propietario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. PERMISOS ORGANIZADOR-EVENTO (Gestión SaaS multi-organizador)
--    Un organizador sólo puede ver/gestionar los eventos aquí listados.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `organizador_evento` (
    `usuario_id`   INT UNSIGNED NOT NULL,
    `evento_id`    INT UNSIGNED NOT NULL,
    `puede_editar` TINYINT(1)   NOT NULL DEFAULT 1,
    `puede_exportar` TINYINT(1) NOT NULL DEFAULT 1,
    `asignado_en`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`usuario_id`, `evento_id`),
    CONSTRAINT `fk_oe_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_oe_evento`
        FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3.5 GRUPOS DE AFORO COMPARTIDO (varias tarifas restan del mismo cupo)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `grupos_aforo` (
    `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `evento_id`    INT UNSIGNED     NOT NULL,
    `nombre`       VARCHAR(100)     NOT NULL,
    `aforo_maximo` INT UNSIGNED     NOT NULL,
    `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_evento` (`evento_id`),
    CONSTRAINT `fk_grupo_evento`
        FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. TARIFAS POR EVENTO (Adult, Infantil, VIP...)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tarifas_evento` (
    `id`            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `evento_id`     INT UNSIGNED     NOT NULL,
    `nombre`        VARCHAR(100)     NOT NULL COMMENT 'Adult, Infantil, VIP...',
    `descripcion`   VARCHAR(500)     NULL,
    `precio`        DECIMAL(8,2)     NOT NULL,
    `aforo_maximo`  INT UNSIGNED     NULL COMMENT 'NULL = sense límit propi',
    `grupo_aforo_id` INT UNSIGNED    NULL COMMENT 'Si està definit, comparteix cupo amb el grup',
    `fecha_inicio`  DATETIME         NULL COMMENT 'Disponible des de',
    `fecha_fin`     DATETIME         NULL COMMENT 'Disponible fins a',
    `orden`         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `activo`        TINYINT(1)       NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_evento_orden` (`evento_id`, `orden`),
    KEY `idx_activo` (`activo`),
    KEY `idx_grupo_aforo` (`grupo_aforo_id`),
    CONSTRAINT `fk_tarifa_evento`
        FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_tarifa_grupo`
        FOREIGN KEY (`grupo_aforo_id`) REFERENCES `grupos_aforo` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. CAMPOS PERSONALIZADOS POR EVENTO
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `campos_personalizados` (
    `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `evento_id`    INT UNSIGNED  NOT NULL,
    `nombre_campo` VARCHAR(100)  NOT NULL COMMENT 'Clave interna, ej: acepta_reglamento',
    `etiqueta`     VARCHAR(255)  NOT NULL COMMENT 'Label visible al corredor',
    `tipo`         ENUM('text','textarea','select','checkbox','radio','date','number','email','tel','file')
                                 NOT NULL DEFAULT 'text',
    `opciones`     JSON          NULL COMMENT 'Array de opciones para select/radio/checkbox',
    `requerido`    TINYINT(1)    NOT NULL DEFAULT 0,
    `orden`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `activo`       TINYINT(1)    NOT NULL DEFAULT 1,
    `placeholder`  VARCHAR(255)  NULL,
    `ayuda`        VARCHAR(500)  NULL COMMENT 'Texto de ayuda bajo el campo',
    PRIMARY KEY (`id`),
    KEY `idx_evento_orden` (`evento_id`, `orden`),
    CONSTRAINT `fk_campo_evento`
        FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. INSCRITOS (Registros de corredores)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inscritos` (
    `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `evento_id`       INT UNSIGNED  NOT NULL,
    `tarifa_id`       INT UNSIGNED  NOT NULL,
    -- Campos estándar del corredor (nombre y email siempre obligatorios;
    -- el resto pueden ocultarse/hacerse opcionales por evento → nullable)
    `nombre`          VARCHAR(100)  NOT NULL,
    `apellido`        VARCHAR(150)  NULL,
    `sexo`            ENUM('H','M','NB') NULL,
    `fecha_nacimiento` DATE         NULL,
    `dni`             VARCHAR(20)   NULL,
    `email`           VARCHAR(255)  NOT NULL,
    `telefono`        VARCHAR(20)   NULL,
    `club`            VARCHAR(150)  NULL,
    `talla_camiseta`  ENUM('XS','S','M','L','XL','XXL','XXXL') NULL,
    -- Estado de inscripción/pago
    `estado`          ENUM('pendiente','confirmado','cancelado','reembolsado')
                                    NOT NULL DEFAULT 'pendiente',
    `numero_dorsal`   INT UNSIGNED  NULL,
    -- Metadatos de seguridad/auditoría
    `ip_registro`     VARCHAR(45)   NULL COMMENT 'IPv4 o IPv6',
    `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_evento_estado` (`evento_id`, `estado`),
    KEY `idx_email` (`email`),
    KEY `idx_dni` (`dni`),
    CONSTRAINT `fk_inscrito_evento`
        FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_inscrito_tarifa`
        FOREIGN KEY (`tarifa_id`) REFERENCES `tarifas_evento` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. VALORES DE CAMPOS PERSONALIZADOS POR INSCRIPCIÓN
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inscrito_campos_valores` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `inscrito_id` INT UNSIGNED NOT NULL,
    `campo_id`    INT UNSIGNED NOT NULL,
    `valor`       TEXT         NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_inscrito_campo` (`inscrito_id`, `campo_id`),
    CONSTRAINT `fk_icv_inscrito`
        FOREIGN KEY (`inscrito_id`) REFERENCES `inscritos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_icv_campo`
        FOREIGN KEY (`campo_id`) REFERENCES `campos_personalizados` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. PAGOS (Registro transacciones Redsys)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pagos` (
    `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `inscrito_id`          INT UNSIGNED  NOT NULL,
    -- Campos enviados a Redsys
    `ds_order`             VARCHAR(20)   NOT NULL COMMENT 'Ds_Merchant_Order único por transacción',
    `ds_merchant_code`     VARCHAR(20)   NULL,
    `ds_terminal`          VARCHAR(10)   NULL,
    `importe`              DECIMAL(8,2)  NOT NULL,
    `moneda`               CHAR(3)       NOT NULL DEFAULT 'EUR',
    -- Respuesta de Redsys (IPN)
    `ds_response`          VARCHAR(10)   NULL COMMENT 'Código respuesta (0000-0099 = OK)',
    `ds_auth_code`         VARCHAR(20)   NULL,
    `ds_transaction_type`  VARCHAR(5)    NULL,
    `ds_secure_payment`    TINYINT(1)    NULL,
    `ds_card_type`         VARCHAR(10)   NULL,
    `ds_card_brand`        VARCHAR(50)   NULL,
    `respuesta_completa`   JSON          NULL COMMENT 'JSON completo de Redsys para auditoría',
    -- Estado local
    `estado`               ENUM('iniciado','completado','fallido','cancelado')
                                         NOT NULL DEFAULT 'iniciado',
    `created_at`           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ds_order` (`ds_order`),
    KEY `idx_inscrito` (`inscrito_id`),
    KEY `idx_estado` (`estado`),
    CONSTRAINT `fk_pago_inscrito`
        FOREIGN KEY (`inscrito_id`) REFERENCES `inscritos` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. LOG DE NOTIFICACIONES IPN (auditoría Redsys)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `redsys_notificaciones` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ds_order`      VARCHAR(20)  NOT NULL,
    `payload_raw`   TEXT         NOT NULL COMMENT 'POST raw de Redsys',
    `ip_origen`     VARCHAR(45)  NULL,
    `procesado`     TINYINT(1)   NOT NULL DEFAULT 0,
    `error`         TEXT         NULL,
    `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ds_order` (`ds_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. INTENTOS DE LOGIN (throttling anti fuerza bruta)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `intentos_login` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip`         VARCHAR(45)     NOT NULL,
    `email`      VARCHAR(255)    NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ip_time`    (`ip`, `created_at`),
    KEY `idx_email_time` (`email`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Superadmin inicial (cambiar contraseña tras primer login)
-- Password: Admin1234! (bcrypt)
-- ------------------------------------------------------------
INSERT INTO `usuarios` (`nombre`, `email`, `password_hash`, `rol`) VALUES
(
    'Superadministrador',
    'admin@example.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'superadmin'
);
