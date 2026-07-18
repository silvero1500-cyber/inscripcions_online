-- Migration 043: nou rol restringit 'export' (només descarregar CSV dels
-- eventos assignats via organizador_evento). Encapsulat a /admin/export.

ALTER TABLE `usuarios`
    MODIFY COLUMN `rol` ENUM('superadmin','organizador','recollida','export')
    NOT NULL DEFAULT 'organizador';
