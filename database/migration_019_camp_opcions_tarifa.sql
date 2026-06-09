-- ============================================================
-- Migration 019: opcions d'un camp diferents segons la tarifa
--   campos_personalizados.opciones_tarifa: JSON {tarifa_id: ["opt1","opt2"], ...}
--   Quan es tria una tarifa amb opcions pròpies, el camp mostra aquestes;
--   si no en té, fa servir les opcions generals (`opciones`).
-- ============================================================

ALTER TABLE `campos_personalizados`
    ADD COLUMN `opciones_tarifa` VARCHAR(2000) NULL AFTER `opciones`;
