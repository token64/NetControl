-- Enlace hacia MikroTik (documentación operativa) + notas (túnel, CGNAT, etc.)
SET NAMES utf8mb4;

ALTER TABLE mikrotiks
  ADD COLUMN enlace_tipo VARCHAR(32) NOT NULL DEFAULT 'directo'
    COMMENT 'directo | tunel_vpn | cgnat (sin IPv4 pública / NAT del operador)' AFTER password,
  ADD COLUMN notas VARCHAR(500) DEFAULT NULL COMMENT 'Libre: IP del túnel, perfil VPN, etc.' AFTER enlace_tipo;
