-- ============================================================
-- Migració 033 · Carreres (marques) amb edicions per any
-- Una "carrera" és la marca (ex: Cursa Festa Major) i cada
-- esdeveniment (`eventos`) passa a ser una EDICIÓ d'una carrera
-- en un any concret. La barra superior llista les carreres i
-- en clicar-ne una s'obre la seva edició activa (la de l'any
-- en curs o la més recent no arxivada).
-- ============================================================

CREATE TABLE IF NOT EXISTS `carreres` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nombre`     VARCHAR(150) NOT NULL,
    `slug`       VARCHAR(160) NOT NULL,
    `color`      VARCHAR(7)   NULL COMMENT 'Accent del botó (hex), opcional',
    `orden`      SMALLINT     NOT NULL DEFAULT 0,
    `activa`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_carrera_slug` (`slug`),
    KEY `idx_carrera_activa_orden` (`activa`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Columnes a `eventos`: a quina carrera i any pertany l'edició
ALTER TABLE `eventos`
    ADD COLUMN `carrera_id`   INT UNSIGNED       NULL AFTER `propietario_id`,
    ADD COLUMN `anio_edicion` SMALLINT UNSIGNED  NULL AFTER `carrera_id`;

ALTER TABLE `eventos`
    ADD KEY `idx_evento_carrera_anio` (`carrera_id`, `anio_edicion`);

ALTER TABLE `eventos`
    ADD CONSTRAINT `fk_evento_carrera`
        FOREIGN KEY (`carrera_id`) REFERENCES `carreres` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- Seed de les 3 carreres reals (idempotent per slug)
INSERT INTO `carreres` (`nombre`, `slug`, `orden`, `activa`) VALUES
    ('Corro contra el càncer', 'corro-contra-el-cancer', 1, 1),
    ('Cursa Festa Major',      'cursa-festa-major',      2, 1),
    ('Cap d''Any Race',        'cap-d-any-race',         3, 1)
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);
