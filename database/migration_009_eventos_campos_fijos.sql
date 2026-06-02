-- ============================================================
-- Migration 009: configuració dels camps fixos per esdeveniment
--   - eventos.campos_fijos: JSON { camp: 'obligatori'|'opcional'|'ocult' }
--   - els camps fixos configurables passen a NULL-ables (es poden ocultar
--     o deixar opcionals; nom i email queden sempre NOT NULL)
-- ============================================================

ALTER TABLE `eventos`
    ADD COLUMN `campos_fijos` JSON NULL COMMENT 'Config per camp fix: obligatori/opcional/ocult' AFTER `inscripciones_abiertas`;

ALTER TABLE `inscritos`
    MODIFY COLUMN `apellido`         VARCHAR(150)        NULL,
    MODIFY COLUMN `sexo`             ENUM('H','M','NB')  NULL,
    MODIFY COLUMN `fecha_nacimiento` DATE                NULL,
    MODIFY COLUMN `dni`              VARCHAR(20)         NULL,
    MODIFY COLUMN `telefono`         VARCHAR(20)         NULL;
