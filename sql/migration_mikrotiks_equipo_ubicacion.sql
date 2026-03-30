-- Equipo y ubicación del router (alineado a fichas ISP habituales).
-- Ejecutar si ya tenés BD previa:
--   mysql -u root panel_wisp < sql/migration_mikrotiks_equipo_ubicacion.sql

USE panel_wisp;

ALTER TABLE mikrotiks
  ADD COLUMN tipo_equipo VARCHAR(32) NOT NULL DEFAULT 'mikrotik' COMMENT 'mikrotik | …' AFTER nombre,
  ADD COLUMN latitud DECIMAL(10,7) DEFAULT NULL AFTER tipo_equipo,
  ADD COLUMN longitud DECIMAL(10,7) DEFAULT NULL AFTER latitud;
