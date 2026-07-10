-- ============================================================
-- Migration 041: preferències d'interfície per usuari (ex: columnes del grid)
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_prefs` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `usuario_id` INT UNSIGNED  NOT NULL,
    `pref_key`   VARCHAR(100)  NOT NULL COMMENT 'ex: inscritos_cols_evt_2',
    `valor`      TEXT          NULL COMMENT 'JSON amb la preferència',
    `updated_at` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_pref` (`usuario_id`, `pref_key`),
    CONSTRAINT `fk_pref_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
