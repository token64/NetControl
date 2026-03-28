-- Fase 1 finanzas: cobros registrados (base para facturación y reportes).
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pagos (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id       INT UNSIGNED NOT NULL,
  monto            DECIMAL(12,2) NOT NULL,
  moneda           CHAR(3) NOT NULL DEFAULT 'DOP',
  metodo           VARCHAR(40) NOT NULL DEFAULT 'efectivo',
  referencia       VARCHAR(120) DEFAULT NULL,
  notas            VARCHAR(500) DEFAULT NULL,
  operador         VARCHAR(80) NOT NULL DEFAULT '',
  creado_en        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_pagos_cliente_creado (cliente_id, creado_en),
  CONSTRAINT fk_pagos_cliente
    FOREIGN KEY (cliente_id) REFERENCES clientes (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;
