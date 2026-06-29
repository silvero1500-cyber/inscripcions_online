-- ============================================================
-- Migració 037 · Camp fix "Xip groc" (SÍ/NO + número condicional)
-- - chip_groc: resposta del corredor (si/no).
-- - chip_groc_num: número del xip (només si la resposta és "si"), màx 10.
-- ============================================================

ALTER TABLE `inscritos`
    ADD COLUMN `chip_groc`     ENUM('si','no') NULL COMMENT 'Porta xip groc propi (si/no)',
    ADD COLUMN `chip_groc_num` VARCHAR(10)     NULL COMMENT 'Número del xip groc (si chip_groc=si)';
