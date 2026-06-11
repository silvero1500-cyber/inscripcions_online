-- Migration 022: camps personalitzats OCULTS
-- Un camp ocult no apareix al formulari públic (ni es valida), però sí com a
-- columna al CSV d'exportació, per omplir info a mà i tornar-la a carregar per
-- importació. S'emmagatzema a inscrito_campos_valores com qualsevol altre camp.

ALTER TABLE `campos_personalizados`
    ADD COLUMN `oculto` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Si 1, no es mostra al formulari públic; només columna CSV (admin)'
    AFTER `activo`;
