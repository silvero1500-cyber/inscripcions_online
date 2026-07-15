-- ============================================================
-- Migration 042: banner superior configurable per evento
-- (abans era una imatge fixa al codi per a tots els events)
-- ============================================================

ALTER TABLE `eventos`
    ADD COLUMN `banner_superior` VARCHAR(500) NULL
        COMMENT 'Imatge de banner a dalt del formulari public (uploads/), opcional'
        AFTER `imagen_portada`;
