-- Migration 024: neutralitza el superadmin per defecte de schema.sql
--
-- schema.sql sembra admin@example.com amb la contrasenya per defecte coneguda
-- (Admin1234!). Aquesta migració el DESACTIVA, però NOMÉS si:
--   (a) encara té exactament aquell hash per defecte (no toca cap compte real
--       que hagi canviat la contrasenya), i
--   (b) existeix algun ALTRE superadmin actiu (evita quedar-se sense accés).
-- És idempotent i segura: si no es compleixen les condicions, no fa res.

UPDATE `usuarios`
SET `activo` = 0
WHERE `email` = 'admin@example.com'
  AND `password_hash` = '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'
  AND (
      SELECT c FROM (
          SELECT COUNT(*) AS c
          FROM `usuarios`
          WHERE `rol` = 'superadmin'
            AND `activo` = 1
            AND `email` <> 'admin@example.com'
      ) AS t
  ) > 0;
