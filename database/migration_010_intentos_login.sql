-- ============================================================
-- Migration 010: registre d'intents de login (throttling persistent)
--   Anti força bruta: comptem fallades per IP i per email en una finestra.
-- ============================================================

CREATE TABLE IF NOT EXISTS `intentos_login` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip`         VARCHAR(45)     NOT NULL,
    `email`      VARCHAR(255)    NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ip_time`    (`ip`, `created_at`),
    KEY `idx_email_time` (`email`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
