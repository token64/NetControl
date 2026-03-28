-- NetControl / Panel WISP — esquema inicial (MariaDB / MySQL 5.7+)
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS panel_wisp
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE panel_wisp;

CREATE TABLE IF NOT EXISTS mikrotiks (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(100) NOT NULL,
  ip            VARCHAR(45)  NOT NULL,
  api_port      SMALLINT UNSIGNED NOT NULL DEFAULT 8728,
  use_ssl       TINYINT(1) NOT NULL DEFAULT 0,
  usuario       VARCHAR(64) NOT NULL,
  password      VARCHAR(255) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

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
SELECT * FROM (SELECT 'plan_5m' AS slug, 'Plan 5 Mbps' AS nombre, '5M/5M' AS max_limit, 1 AS activo, 10 AS sort_order) AS t
WHERE NOT EXISTS (SELECT 1 FROM planes LIMIT 1);

INSERT INTO planes (slug, nombre, max_limit, activo, sort_order)
SELECT 'plan_10m', 'Plan 10 Mbps', '10M/10M', 1, 20
WHERE NOT EXISTS (SELECT 1 FROM planes WHERE slug = 'plan_10m');

INSERT INTO planes (slug, nombre, max_limit, activo, sort_order)
SELECT 'plan_20m', 'Plan 20 Mbps', '20M/20M', 1, 30
WHERE NOT EXISTS (SELECT 1 FROM planes WHERE slug = 'plan_20m');

CREATE TABLE IF NOT EXISTS clientes (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre         VARCHAR(180) NOT NULL,
  cedula         VARCHAR(32) DEFAULT NULL,
  telefono       VARCHAR(32) DEFAULT NULL,
  direccion      VARCHAR(255) DEFAULT NULL,
  latitud        DECIMAL(10,7) DEFAULT NULL,
  longitud       DECIMAL(10,7) DEFAULT NULL,
  usuario        VARCHAR(64) DEFAULT NULL,
  password       VARCHAR(128) DEFAULT NULL,
  plan           VARCHAR(64) NOT NULL,
  tipo_conexion  ENUM('pppoe','ip') NOT NULL DEFAULT 'pppoe',
  ip_fija        VARCHAR(45) DEFAULT NULL,
  mikrotik_id    INT UNSIGNED NOT NULL,
  estado         ENUM('activo','suspendido') NOT NULL DEFAULT 'activo',
  fecha_pago     DATE DEFAULT NULL,
  promesa_hasta  DATE DEFAULT NULL,
  promesa_notas  VARCHAR(500) DEFAULT NULL,
  creado_en      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clientes_mikrotik (mikrotik_id),
  KEY idx_clientes_estado_pago (estado, fecha_pago),
  KEY idx_clientes_promesa (promesa_hasta, estado),
  CONSTRAINT fk_clientes_mikrotik
    FOREIGN KEY (mikrotik_id) REFERENCES mikrotiks (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- MikroTik de ejemplo (ejecutar una sola vez o ajustar IP/usuario/clave)
INSERT INTO mikrotiks (nombre, ip, api_port, use_ssl, usuario, password)
SELECT 'Principal', '192.168.88.1', 8728, 0, 'admin', 'CAMBIAR_PASSWORD'
WHERE NOT EXISTS (SELECT 1 FROM mikrotiks LIMIT 1);

INSERT INTO redes (nombre, mikrotik_id, rango, activo)
SELECT CONCAT('Rango predeterminado — ', nombre), id, '192.168.10.2-192.168.10.254', 1
FROM mikrotiks m
WHERE NOT EXISTS (SELECT 1 FROM redes r WHERE r.mikrotik_id = m.id);
