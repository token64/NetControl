-- NetControl v2: planes (catálogo velocidad) y redes (pool IPv4 por MikroTik)
-- Ejecutar en panel_wisp si ya tenías instalación previa:
--   mysql -u root panel_wisp < sql/migration_v2_planes_redes.sql

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS planes (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug         VARCHAR(64)  NOT NULL,
  nombre       VARCHAR(120) NOT NULL,
  max_limit    VARCHAR(64)  NOT NULL COMMENT 'Límite cola simple MikroTik ej. 10M/10M',
  activo       TINYINT(1) NOT NULL DEFAULT 1,
  sort_order   INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_planes_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS redes (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre       VARCHAR(120) NOT NULL,
  mikrotik_id  INT UNSIGNED NOT NULL,
  rango        VARCHAR(160) NOT NULL COMMENT 'Rango inicio-fin para ip_libre()',
  activo       TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_redes_mikrotik (mikrotik_id),
  CONSTRAINT fk_redes_mikrotik
    FOREIGN KEY (mikrotik_id) REFERENCES mikrotiks (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT INTO planes (slug, nombre, max_limit, activo, sort_order)
SELECT * FROM (SELECT 'plan_5m' AS slug, 'Plan 5 Mbps' AS nombre, '5M/5M' AS max_limit, 1 AS activo, 10 AS sort_order) AS row_tmp
WHERE NOT EXISTS (SELECT 1 FROM planes LIMIT 1);

INSERT INTO planes (slug, nombre, max_limit, activo, sort_order)
SELECT 'plan_10m', 'Plan 10 Mbps', '10M/10M', 1, 20
WHERE NOT EXISTS (SELECT 1 FROM planes WHERE slug = 'plan_10m');

INSERT INTO planes (slug, nombre, max_limit, activo, sort_order)
SELECT 'plan_20m', 'Plan 20 Mbps', '20M/20M', 1, 30
WHERE NOT EXISTS (SELECT 1 FROM planes WHERE slug = 'plan_20m');

INSERT INTO redes (nombre, mikrotik_id, rango, activo)
SELECT CONCAT('Rango predeterminado — ', nombre), id, '192.168.10.2-192.168.10.254', 1
FROM mikrotiks m
WHERE NOT EXISTS (SELECT 1 FROM redes r WHERE r.mikrotik_id = m.id);
