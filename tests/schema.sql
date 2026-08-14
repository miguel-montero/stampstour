-- tests/schema.sql
-- Schema-only structure for the payment-layer test database. Extracted
-- from the real local dump's CREATE TABLE statements - no data, no real
-- customer information. See
-- docs/superpowers/specs/2026-08-06-payment-test-infrastructure-design.md

CREATE TABLE IF NOT EXISTS `reservas` (
  `id_reserva` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_externo` varchar(100) DEFAULT NULL,
  `reference_id` varchar(64) NOT NULL,
  `process_id` int(11) DEFAULT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `capture_id` varchar(50) DEFAULT NULL,
  `fecha_reserva` date NOT NULL,
  `fecha_actividad` date NOT NULL,
  `adultos` int(11) NOT NULL DEFAULT '0',
  `ninos` int(11) NOT NULL DEFAULT '0',
  `infantes` int(11) NOT NULL DEFAULT '0',
  `airport_pickup` tinyint(1) NOT NULL DEFAULT '0',
  `airport_dropoff` tinyint(1) NOT NULL DEFAULT '0',
  `pais_origen` varchar(100) DEFAULT NULL,
  `idioma_actividad` varchar(100) DEFAULT NULL,
  `id_cupon` int(11) DEFAULT NULL,
  `id_titular` int(11) NOT NULL,
  `id_hotel` int(11) DEFAULT NULL,
  `hotel_manual` varchar(255) DEFAULT NULL,
  `id_guia` int(11) DEFAULT NULL,
  `id_conductor` int(11) DEFAULT NULL,
  `id_vendedor` int(11) DEFAULT NULL,
  `id_experiencia` bigint(20) UNSIGNED DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_venta` decimal(10,2) NOT NULL DEFAULT '0.00',
  `monto_pagado` decimal(10,2) DEFAULT NULL,
  `moneda` varchar(3) DEFAULT NULL,
  `refund_id` varchar(50) DEFAULT NULL,
  `refund_monto` decimal(10,2) DEFAULT NULL,
  `estado` enum('pendiente','realizado','fallido','refund') NOT NULL DEFAULT 'pendiente',
  `email_sent_at` datetime DEFAULT NULL,
  `email_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `last_email_error` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_reserva`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `titulares` (
  `id_titular` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `apellido` varchar(255) NOT NULL,
  `area_code` varchar(8) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  PRIMARY KEY (`id_titular`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `experiencias` (
  `id_experiencia` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `nombre_publico` varchar(255) NOT NULL,
  `precio_adulto` decimal(10,2) NOT NULL,
  `precio_nino` decimal(10,2) DEFAULT NULL,
  `precio_infante` decimal(10,2) DEFAULT NULL,
  `precio_concierge` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sale_adulto` decimal(10,2) DEFAULT NULL,
  `sale_nino` decimal(10,2) DEFAULT NULL,
  `TAR` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id_experiencia`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `hoteles` (
  `id_hotel` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_hotel` varchar(255) NOT NULL,
  `direccion` text,
  `comuna` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_hotel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vendedores` (
  `id_vendedor` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_vendedor` varchar(255) NOT NULL,
  `canal_venta` varchar(100) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_vendedor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `paypal_webhook_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_id` varchar(64) NOT NULL,
  `event_type` varchar(64) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'received',
  `verified` enum('SUCCESS','FAILURE') DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `handled_at` timestamp NULL DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `headers` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `getnet_webhook_events` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `received_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `source_ip` varchar(45) DEFAULT NULL,
  `request_id` varchar(64) NOT NULL,
  `reference` varchar(64) NOT NULL,
  `status_text` varchar(32) NOT NULL,
  `status_date` varchar(40) NOT NULL,
  `signature` varchar(64) NOT NULL,
  `calc_signature` varchar(64) NOT NULL,
  `signature_valid` tinyint(1) NOT NULL,
  `http_response` int(11) DEFAULT NULL,
  `raw_payload` json DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
