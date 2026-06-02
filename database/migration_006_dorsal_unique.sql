-- ============================================================
-- Migration 006: UNIQUE (evento_id, numero_dorsal) en inscritos
-- Permet múltiples NULLs (MySQL) però evita duplicats reals.
-- ============================================================

ALTER TABLE `inscritos`
    ADD UNIQUE KEY `uq_evento_dorsal` (`evento_id`, `numero_dorsal`);
