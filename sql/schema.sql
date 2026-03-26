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
