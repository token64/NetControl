-- Promesa de pago: fecha límite de compromiso que retrasa el corte automático
-- Ejecutar una vez si ya tenías panel_wisp creado antes de esta función.

USE panel_wisp;

ALTER TABLE clientes
  ADD COLUMN promesa_hasta DATE DEFAULT NULL COMMENT 'Válido hasta: no cortar por mora antes de esta fecha' AFTER fecha_pago,
  ADD COLUMN promesa_notas VARCHAR(500) DEFAULT NULL COMMENT 'Nota interna del compromiso' AFTER promesa_hasta;

CREATE INDEX idx_clientes_promesa ON clientes (promesa_hasta, estado);
