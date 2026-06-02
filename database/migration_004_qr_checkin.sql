-- ============================================================
-- Migration 004: QR check-in en inscritos
-- ============================================================
ALTER TABLE `inscritos`
    ADD COLUMN `qr_token`       VARCHAR(64) NULL UNIQUE AFTER `numero_dorsal`,
    ADD COLUMN `check_in_at`    DATETIME    NULL AFTER `qr_token`,
    ADD COLUMN `check_in_por`   INT UNSIGNED NULL AFTER `check_in_at`,
    ADD CONSTRAINT `fk_inscrito_checkin_por`
        FOREIGN KEY (`check_in_por`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE;
