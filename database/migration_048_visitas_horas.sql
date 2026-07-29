-- Migration 048: comptador de connexions (visites) al formulari públic per
-- hora, per al KPI d'hores punta. Agregat (no una fila per visita): com a molt
-- 24 files per dia i esdeveniment. Sense IP ni dades personals (cap tema RGPD).
CREATE TABLE IF NOT EXISTS `visitas_horas` (
    `evento_id` INT UNSIGNED   NOT NULL,
    `fecha`     DATE           NOT NULL,
    `hora`      TINYINT UNSIGNED NOT NULL,
    `n`         INT UNSIGNED   NOT NULL DEFAULT 0,
    PRIMARY KEY (`evento_id`, `fecha`, `hora`),
    CONSTRAINT `fk_visitas_evento`
        FOREIGN KEY (`evento_id`) REFERENCES `eventos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
