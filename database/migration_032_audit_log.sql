-- ============================================================
-- Migració 032 · Registre d'auditoria de seguretat (audit_log)
-- Accions sensibles: login (ok/fallit), gestió d'usuaris,
-- esborrat/arxivat d'esdeveniments, exportació d'inscrits, config.
-- Sense FK a usuarios (es desa el nom snapshot perquè es conservi
-- encara que s'esborri l'usuari).
-- ============================================================
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `usuario_id`  INT UNSIGNED NULL,
    `usuario_nom` VARCHAR(150) NULL,
    `accion`      VARCHAR(40)  NOT NULL,
    `detall`      VARCHAR(255) NULL,
    `ip`          VARCHAR(45)  NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_created` (`created_at`),
    KEY `idx_accion` (`accion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
