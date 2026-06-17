-- Migration 029: taula d'ajustos globals (clau/valor). Primer ús: lloc i horari
-- de recollida de la botiga (es mostra a la confirmació i als correus).

CREATE TABLE IF NOT EXISTS `ajustes` (
    `clave`      VARCHAR(100) NOT NULL,
    `valor`      TEXT         NULL,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
