-- Migration 025: limitador de freqüència genèric (rate limiting)
-- S'usa per protegir endpoints públics sensibles (p. ex. la recuperació de
-- comprovant) contra enumeració massiva i enviament massiu de correus.

CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rate_key`   VARCHAR(150) NOT NULL COMMENT 'Ex: recover:1.2.3.4',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_key_time` (`rate_key`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
