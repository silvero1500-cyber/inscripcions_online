-- Migration 049: origen de les visites al formulari (font de trànsit) per al
-- KPI "Origen de les visites". Agregat com visitas_horas: (evento, dia, font).
-- font = 'facebook' | 'instagram' | 'google' | 'mailing' | 'whatsapp' |
--        'twitter' | 'youtube' | 'web' | 'cerca' | 'directe' | 'altres' ...
-- Sense IP ni dades personals (cap tema RGPD).
CREATE TABLE IF NOT EXISTS `visitas_origen` (
    `evento_id` INT UNSIGNED NOT NULL,
    `fecha`     DATE         NOT NULL,
    `font`      VARCHAR(20)  NOT NULL,
    `n`         INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`evento_id`, `fecha`, `font`),
    CONSTRAINT `fk_visitas_origen_evento`
        FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
