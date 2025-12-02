-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 02-12-2025 a las 02:39:29
-- Versión del servidor: 8.3.0
-- Versión de PHP: 8.2.18

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `sistema_pagos_saldos_banana`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bitacora`
--

DROP TABLE IF EXISTS `bitacora`;
CREATE TABLE IF NOT EXISTS `bitacora` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `usuario_id` int DEFAULT NULL,
  `accion` varchar(60) NOT NULL,
  `entidad_tipo` enum('cotizacion','orden','pago','cliente','chat','usuario') NOT NULL,
  `entidad_id` int NOT NULL,
  `descripcion` text,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_bitacora_ent` (`entidad_tipo`,`entidad_id`),
  KEY `fk_bit_user` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cargos`
--

DROP TABLE IF EXISTS `cargos`;
CREATE TABLE IF NOT EXISTS `cargos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orden_id` int NOT NULL,
  `rfc_id` int DEFAULT NULL,
  `periodo_inicio` date NOT NULL,
  `periodo_fin` date NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `iva` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `estatus` enum('emitido','pagado','vencido','cancelado') NOT NULL DEFAULT 'emitido',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_periodo` (`orden_id`,`periodo_inicio`,`periodo_fin`),
  KEY `rfc_id` (`rfc_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cargos`
--

INSERT INTO `cargos` (`id`, `orden_id`, `rfc_id`, `periodo_inicio`, `periodo_fin`, `subtotal`, `iva`, `total`, `estatus`, `creado_en`) VALUES
(8, 9, 1, '2025-11-01', '2025-11-30', 17142.00, 2742.72, 19884.72, 'pagado', '2025-11-29 00:55:55'),
(9, 5, 1, '2025-11-01', '2025-11-30', 17142.00, 2742.72, 19884.72, 'pagado', '2025-11-29 17:53:50'),
(10, 8, 2, '2025-11-01', '2025-11-30', 10606.00, 1696.96, 12302.96, 'pagado', '2025-11-30 17:25:51'),
(11, 6, 1, '2025-11-01', '2025-11-30', 12268.00, 1962.88, 14230.88, 'emitido', '2025-11-30 17:26:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cargo_items`
--

DROP TABLE IF EXISTS `cargo_items`;
CREATE TABLE IF NOT EXISTS `cargo_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cargo_id` int NOT NULL,
  `orden_item_id` int DEFAULT NULL,
  `concepto` varchar(255) NOT NULL,
  `monto_base` decimal(10,2) NOT NULL,
  `iva` decimal(10,2) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `cargo_id` (`cargo_id`),
  KEY `orden_item_id` (`orden_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=273 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cargo_items`
--

INSERT INTO `cargo_items` (`id`, `cargo_id`, `orden_item_id`, `concepto`, `monto_base`, `iva`, `total`) VALUES
(1, 1, 1, 'cuenta - 1575', 1575.00, 252.00, 1827.00),
(2, 1, 2, 'publicaciones - 2363', 2363.00, 378.08, 2741.08),
(3, 1, 3, 'campañas - 1260', 1260.00, 201.60, 1461.60),
(4, 1, 4, 'reposteo - 1050', 1050.00, 168.00, 1218.00),
(5, 1, 5, 'stories - 1575', 1575.00, 252.00, 1827.00),
(6, 1, 6, 'imprenta - 1050', 1050.00, 168.00, 1218.00),
(7, 1, 7, 'fotos - 1750', 1750.00, 280.00, 2030.00),
(8, 1, 8, 'video - 1925', 1925.00, 308.00, 2233.00),
(9, 1, 9, 'ads - 1969', 1969.00, 315.04, 2284.04),
(10, 1, 10, 'web - 1575', 1575.00, 252.00, 1827.00),
(11, 1, 11, 'mkt - 1050', 1050.00, 168.00, 1218.00),
(23, 2, 1, 'cuenta - 1575', 1575.00, 252.00, 1827.00),
(24, 2, 2, 'publicaciones - 2363', 2363.00, 378.08, 2741.08),
(25, 2, 3, 'campañas - 1260', 1260.00, 201.60, 1461.60),
(26, 2, 4, 'reposteo - 1050', 1050.00, 168.00, 1218.00),
(27, 2, 5, 'stories - 1575', 1575.00, 252.00, 1827.00),
(28, 2, 6, 'imprenta - 1050', 1050.00, 168.00, 1218.00),
(29, 2, 7, 'fotos - 1750', 1750.00, 280.00, 2030.00),
(30, 2, 8, 'video - 1925', 1925.00, 308.00, 2233.00),
(31, 2, 9, 'ads - 1969', 1969.00, 315.04, 2284.04),
(32, 2, 10, 'web - 1575', 1575.00, 252.00, 1827.00),
(33, 2, 11, 'mkt - 1050', 1050.00, 168.00, 1218.00),
(198, 8, 45, 'cuenta - 1575', 1575.00, 252.00, 1827.00),
(199, 8, 46, 'publicaciones - 2363', 2363.00, 378.08, 2741.08),
(200, 8, 47, 'campañas - 1260', 1260.00, 201.60, 1461.60),
(201, 8, 48, 'reposteo - 1050', 1050.00, 168.00, 1218.00),
(202, 8, 49, 'stories - 1575', 1575.00, 252.00, 1827.00),
(203, 8, 50, 'imprenta - 1050', 1050.00, 168.00, 1218.00),
(204, 8, 51, 'fotos - 1750', 1750.00, 280.00, 2030.00),
(205, 8, 52, 'video - 1925', 1925.00, 308.00, 2233.00),
(206, 8, 53, 'ads - 1969', 1969.00, 315.04, 2284.04),
(207, 8, 54, 'web - 1575', 1575.00, 252.00, 1827.00),
(208, 8, 55, 'mkt - 1050', 1050.00, 168.00, 1218.00),
(242, 11, 12, 'cuenta - 1575', 1575.00, 252.00, 1827.00),
(243, 11, 13, 'publicaciones - 1181', 1181.00, 188.96, 1369.96),
(244, 11, 14, 'campañas - 630', 630.00, 100.80, 730.80),
(245, 11, 15, 'reposteo - 1050', 1050.00, 168.00, 1218.00),
(246, 11, 16, 'stories - 0', 0.00, 0.00, 0.00),
(247, 11, 17, 'imprenta - 525', 525.00, 84.00, 609.00),
(248, 11, 18, 'fotos - 1750', 1750.00, 280.00, 2030.00),
(249, 11, 19, 'video - 963', 963.00, 154.08, 1117.08),
(250, 11, 20, 'ads - 1969', 1969.00, 315.04, 2284.04),
(251, 11, 21, 'web - sistema', 1575.00, 252.00, 1827.00),
(252, 11, 22, 'mkt - 1050', 1050.00, 168.00, 1218.00),
(253, 9, 1, 'cuenta - 1575', 1575.00, 252.00, 1827.00),
(254, 9, 2, 'publicaciones - 2363', 2363.00, 378.08, 2741.08),
(255, 9, 3, 'campañas - 1260', 1260.00, 201.60, 1461.60),
(256, 9, 4, 'reposteo - 1050', 1050.00, 168.00, 1218.00),
(257, 9, 5, 'stories - 1575', 1575.00, 252.00, 1827.00),
(258, 9, 6, 'imprenta - 1050', 1050.00, 168.00, 1218.00),
(259, 9, 7, 'fotos - 1750', 1750.00, 280.00, 2030.00),
(260, 9, 8, 'video - 1925', 1925.00, 308.00, 2233.00),
(261, 9, 9, 'ads - 1969', 1969.00, 315.04, 2284.04),
(262, 9, 10, 'web - 1575', 1575.00, 252.00, 1827.00),
(263, 9, 11, 'mkt - 1050', 1050.00, 168.00, 1218.00),
(264, 10, 34, 'cuenta - 1575', 1575.00, 252.00, 1827.00),
(265, 10, 35, 'publicaciones - 1181', 1181.00, 188.96, 1369.96),
(266, 10, 36, 'campañas - 630', 630.00, 100.80, 730.80),
(267, 10, 37, 'reposteo - 1050', 1050.00, 168.00, 1218.00),
(268, 10, 38, 'stories - 788', 788.00, 126.08, 914.08),
(269, 10, 40, 'fotos - 875', 875.00, 140.00, 1015.00),
(270, 10, 41, 'video - 963', 963.00, 154.08, 1117.08),
(271, 10, 42, 'ads - 1969', 1969.00, 315.04, 2284.04),
(272, 10, 43, 'web - 1575', 1575.00, 252.00, 1827.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chat_archivos`
--

DROP TABLE IF EXISTS `chat_archivos`;
CREATE TABLE IF NOT EXISTS `chat_archivos` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `mensaje_id` bigint NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `ruta` varchar(400) NOT NULL,
  `mime` varchar(100) NOT NULL,
  `tam_bytes` int NOT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_file_msg` (`mensaje_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chat_hilos`
--

DROP TABLE IF EXISTS `chat_hilos`;
CREATE TABLE IF NOT EXISTS `chat_hilos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `scope_tipo` enum('cotizacion','orden') NOT NULL,
  `scope_id` int NOT NULL,
  `estado` enum('abierto','en_revision','resuelto') NOT NULL DEFAULT 'abierto',
  `creado_por_usuario_id` int DEFAULT NULL,
  `creado_por_cliente_id` int DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_chat_scope` (`scope_tipo`,`scope_id`),
  KEY `fk_chat_creador_user` (`creado_por_usuario_id`),
  KEY `fk_chat_creador_cli` (`creado_por_cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chat_mensajes`
--

DROP TABLE IF EXISTS `chat_mensajes`;
CREATE TABLE IF NOT EXISTS `chat_mensajes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `hilo_id` int NOT NULL,
  `autor_usuario_id` int DEFAULT NULL,
  `autor_cliente_id` int DEFAULT NULL,
  `tipo` enum('texto','archivo') NOT NULL DEFAULT 'texto',
  `mensaje` text,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_msg_hilo` (`hilo_id`,`creado_en`),
  KEY `fk_msg_user` (`autor_usuario_id`),
  KEY `fk_msg_cli` (`autor_cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chat_participantes`
--

DROP TABLE IF EXISTS `chat_participantes`;
CREATE TABLE IF NOT EXISTS `chat_participantes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hilo_id` int NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `cliente_id` int DEFAULT NULL,
  `rol` enum('autor','colaborador','cliente') NOT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cp_user` (`hilo_id`,`usuario_id`),
  UNIQUE KEY `uq_cp_cli` (`hilo_id`,`cliente_id`),
  KEY `fk_cp_user` (`usuario_id`),
  KEY `fk_cp_cli` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

DROP TABLE IF EXISTS `clientes`;
CREATE TABLE IF NOT EXISTS `clientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `empresa` varchar(150) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_clientes_correo` (`correo`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `empresa`, `correo`, `telefono`, `creado_en`) VALUES
(7, 'fantasma', 'fastama@gmail.com', NULL, '2025-10-18 23:03:29'),
(8, 'Smarnex', 'smarnex@gmail.com', NULL, '2025-11-27 20:11:52'),
(10, 'ShowBussines', 'showbussines@gmail.com', NULL, '2025-11-28 22:56:15'),
(11, 'Dolcevilla', 'Sales@dolcevilla.com', NULL, '2025-11-28 23:12:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `company_rfcs`
--

DROP TABLE IF EXISTS `company_rfcs`;
CREATE TABLE IF NOT EXISTS `company_rfcs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rfc` varchar(13) NOT NULL,
  `razon_social` varchar(255) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `company_rfcs`
--

INSERT INTO `company_rfcs` (`id`, `rfc`, `razon_social`, `descripcion`, `creado_en`) VALUES
(1, 'BACJ9205028JA', 'Jesús Antonio Barraza Ceseña', 'RFC principal (testing)', '2025-11-09 20:29:13'),
(2, 'ROMD970621R10', 'Diana Fernanda Rodríguez Marrón', 'RFC alterno (testing)', '2025-11-09 20:29:13');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizaciones`
--

DROP TABLE IF EXISTS `cotizaciones`;
CREATE TABLE IF NOT EXISTS `cotizaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cliente_id` int DEFAULT NULL,
  `empresa` varchar(150) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `adicionales` decimal(12,2) NOT NULL DEFAULT '0.00',
  `impuestos` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tasa_iva` decimal(5,2) NOT NULL DEFAULT '0.00',
  `minimo` int NOT NULL DEFAULT '0',
  `cumple_minimo` tinyint(1) NOT NULL DEFAULT '0',
  `estado` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_cot_correo` (`correo`),
  KEY `ix_cot_estado` (`estado`),
  KEY `fk_cot_cliente` (`cliente_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cotizaciones`
--

INSERT INTO `cotizaciones` (`id`, `cliente_id`, `empresa`, `correo`, `subtotal`, `adicionales`, `impuestos`, `total`, `tasa_iva`, `minimo`, `cumple_minimo`, `estado`, `creado_en`, `actualizado_en`) VALUES
(1, 10, 'ShowBussines', 'showbussines@gmail.com', 10606.00, 0.00, 1696.96, 12302.96, 16.00, 10079, 1, 'aprobada', '2025-10-18 17:11:51', '2025-11-28 22:56:15'),
(2, 7, 'fantasma', 'fastama@gmail.com', 17142.00, 0.00, 2742.72, 19884.72, 16.00, 10079, 1, 'aprobada', '2025-10-18 17:25:14', '2025-10-18 23:03:29'),
(3, NULL, 'jaja', 'daniel@gmail.com', 14280.00, 0.00, 2284.80, 16564.80, 16.00, 10079, 1, 'rechazada', '2025-10-18 17:31:07', '2025-10-18 18:18:53'),
(5, 8, 'Smarnex', 'smarnex@gmail.com', 12268.00, 0.00, 1962.88, 14230.88, 16.00, 10079, 1, 'aprobada', '2025-11-27 18:41:35', '2025-11-27 20:11:52'),
(6, 11, 'Dolcevilla', 'Sales@dolcevilla.com', 17142.00, 0.00, 2742.72, 19884.72, 16.00, 10079, 1, 'aprobada', '2025-11-28 23:12:20', '2025-11-28 23:12:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizacion_items`
--

DROP TABLE IF EXISTS `cotizacion_items`;
CREATE TABLE IF NOT EXISTS `cotizacion_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cotizacion_id` int NOT NULL,
  `grupo` varchar(60) NOT NULL,
  `opcion` varchar(60) NOT NULL,
  `valor` decimal(12,2) NOT NULL,
  `periodicidad` enum('unico','mensual','bimestral') NOT NULL DEFAULT 'unico',
  `proxima_facturacion` date DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_item_cot` (`cotizacion_id`),
  KEY `ix_item_periodo` (`periodicidad`,`proxima_facturacion`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `cotizacion_items`
--

INSERT INTO `cotizacion_items` (`id`, `cotizacion_id`, `grupo`, `opcion`, `valor`, `periodicidad`, `proxima_facturacion`, `creado_en`) VALUES
(1, 1, 'cuenta', '1575', 1575.00, 'unico', NULL, '2025-10-18 17:11:51'),
(2, 1, 'publicaciones', '1181', 1181.00, 'unico', NULL, '2025-10-18 17:11:51'),
(3, 1, 'campañas', '630', 630.00, 'unico', NULL, '2025-10-18 17:11:51'),
(4, 1, 'reposteo', '1050', 1050.00, 'unico', NULL, '2025-10-18 17:11:51'),
(5, 1, 'stories', '788', 788.00, 'unico', NULL, '2025-10-18 17:11:51'),
(6, 1, 'imprenta', '0', 0.00, 'unico', NULL, '2025-10-18 17:11:51'),
(7, 1, 'fotos', '875', 875.00, 'unico', NULL, '2025-10-18 17:11:51'),
(8, 1, 'video', '963', 963.00, 'unico', NULL, '2025-10-18 17:11:51'),
(9, 1, 'ads', '1969', 1969.00, 'unico', NULL, '2025-10-18 17:11:51'),
(10, 1, 'web', '1575', 1575.00, 'unico', NULL, '2025-10-18 17:11:51'),
(11, 1, 'mkt', '0', 0.00, 'unico', NULL, '2025-10-18 17:11:51'),
(12, 2, 'cuenta', '1575', 1575.00, 'unico', NULL, '2025-10-18 17:25:14'),
(13, 2, 'publicaciones', '2363', 2363.00, 'unico', NULL, '2025-10-18 17:25:14'),
(14, 2, 'campañas', '1260', 1260.00, 'unico', NULL, '2025-10-18 17:25:14'),
(15, 2, 'reposteo', '1050', 1050.00, 'unico', NULL, '2025-10-18 17:25:14'),
(16, 2, 'stories', '1575', 1575.00, 'unico', NULL, '2025-10-18 17:25:14'),
(17, 2, 'imprenta', '1050', 1050.00, 'unico', NULL, '2025-10-18 17:25:14'),
(18, 2, 'fotos', '1750', 1750.00, 'unico', NULL, '2025-10-18 17:25:14'),
(19, 2, 'video', '1925', 1925.00, 'unico', NULL, '2025-10-18 17:25:14'),
(20, 2, 'ads', '1969', 1969.00, 'unico', NULL, '2025-10-18 17:25:14'),
(21, 2, 'web', '1575', 1575.00, 'unico', NULL, '2025-10-18 17:25:14'),
(22, 2, 'mkt', '1050', 1050.00, 'unico', NULL, '2025-10-18 17:25:14'),
(23, 3, 'cuenta', '1575', 1575.00, 'unico', NULL, '2025-10-18 17:31:07'),
(24, 3, 'publicaciones', '1181', 1181.00, 'unico', NULL, '2025-10-18 17:31:07'),
(25, 3, 'campañas', '630', 630.00, 'unico', NULL, '2025-10-18 17:31:07'),
(26, 3, 'reposteo', '1050', 1050.00, 'unico', NULL, '2025-10-18 17:31:07'),
(27, 3, 'stories', '1575', 1575.00, 'unico', NULL, '2025-10-18 17:31:07'),
(28, 3, 'imprenta', '525', 525.00, 'unico', NULL, '2025-10-18 17:31:07'),
(29, 3, 'fotos', '1750', 1750.00, 'unico', NULL, '2025-10-18 17:31:07'),
(30, 3, 'video', '1925', 1925.00, 'unico', NULL, '2025-10-18 17:31:07'),
(31, 3, 'ads', '1969', 1969.00, 'unico', NULL, '2025-10-18 17:31:07'),
(32, 3, 'web', '1575', 1575.00, 'unico', NULL, '2025-10-18 17:31:07'),
(33, 3, 'mkt', '525', 525.00, 'unico', NULL, '2025-10-18 17:31:07'),
(50, 5, 'cuenta', '1575', 1575.00, 'unico', NULL, '2025-11-27 18:41:35'),
(51, 5, 'publicaciones', '1181', 1181.00, 'unico', NULL, '2025-11-27 18:41:35'),
(52, 5, 'campañas', '630', 630.00, 'unico', NULL, '2025-11-27 18:41:35'),
(53, 5, 'reposteo', '1050', 1050.00, 'unico', NULL, '2025-11-27 18:41:35'),
(54, 5, 'stories', '0', 0.00, 'unico', NULL, '2025-11-27 18:41:35'),
(55, 5, 'imprenta', '525', 525.00, 'unico', NULL, '2025-11-27 18:41:35'),
(56, 5, 'fotos', '1750', 1750.00, 'unico', NULL, '2025-11-27 18:41:35'),
(57, 5, 'video', '963', 963.00, 'unico', NULL, '2025-11-27 18:41:35'),
(58, 5, 'ads', '1969', 1969.00, 'unico', NULL, '2025-11-27 18:41:35'),
(59, 5, 'web', 'sistema', 1575.00, 'unico', NULL, '2025-11-27 18:41:35'),
(60, 5, 'mkt', '1050', 1050.00, 'unico', NULL, '2025-11-27 18:41:35'),
(61, 6, 'cuenta', '1575', 1575.00, 'unico', NULL, '2025-11-28 23:12:20'),
(62, 6, 'publicaciones', '2363', 2363.00, 'unico', NULL, '2025-11-28 23:12:20'),
(63, 6, 'campañas', '1260', 1260.00, 'unico', NULL, '2025-11-28 23:12:20'),
(64, 6, 'reposteo', '1050', 1050.00, 'unico', NULL, '2025-11-28 23:12:20'),
(65, 6, 'stories', '1575', 1575.00, 'unico', NULL, '2025-11-28 23:12:20'),
(66, 6, 'imprenta', '1050', 1050.00, 'unico', NULL, '2025-11-28 23:12:20'),
(67, 6, 'fotos', '1750', 1750.00, 'unico', NULL, '2025-11-28 23:12:20'),
(68, 6, 'video', '1925', 1925.00, 'unico', NULL, '2025-11-28 23:12:20'),
(69, 6, 'ads', '1969', 1969.00, 'unico', NULL, '2025-11-28 23:12:20'),
(70, 6, 'web', '1575', 1575.00, 'unico', NULL, '2025-11-28 23:12:20'),
(71, 6, 'mkt', '1050', 1050.00, 'unico', NULL, '2025-11-28 23:12:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `tipo` enum('interna','externa') NOT NULL,
  `canal` enum('sistema','email') NOT NULL DEFAULT 'sistema',
  `titulo` varchar(200) NOT NULL,
  `cuerpo` text NOT NULL,
  `usuario_id` int DEFAULT NULL,
  `cliente_id` int DEFAULT NULL,
  `correo_destino` varchar(150) DEFAULT NULL,
  `ref_tipo` enum('cotizacion','orden','pago','chat') DEFAULT NULL,
  `ref_id` int DEFAULT NULL,
  `estado` enum('pendiente','enviada','leida','fallida') NOT NULL DEFAULT 'pendiente',
  `programada_en` datetime DEFAULT NULL,
  `enviada_en` datetime DEFAULT NULL,
  `leida_en` datetime DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_notif_dest_user` (`usuario_id`,`estado`),
  KEY `ix_notif_dest_cli` (`cliente_id`,`estado`),
  KEY `ix_notif_ref` (`ref_tipo`,`ref_id`),
  KEY `ix_notif_chat` (`ref_tipo`,`ref_id`,`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes`
--

DROP TABLE IF EXISTS `ordenes`;
CREATE TABLE IF NOT EXISTS `ordenes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cotizacion_id` int NOT NULL,
  `cliente_id` int NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `saldo` decimal(12,2) NOT NULL,
  `estado` enum('activa','finalizada','cancelada') NOT NULL DEFAULT 'activa',
  `periodicidad` enum('unico','mensual','bimestral') NOT NULL DEFAULT 'unico',
  `proxima_facturacion` date DEFAULT NULL,
  `vence_en` date DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `rfc_id` int DEFAULT NULL,
  `billing_policy` enum('prepaid_anchor','fixed_day') NOT NULL DEFAULT 'prepaid_anchor',
  `cut_day` tinyint DEFAULT NULL,
  `grace_days` tinyint NOT NULL DEFAULT '5',
  `suspend_after_days` tinyint NOT NULL DEFAULT '10',
  PRIMARY KEY (`id`),
  KEY `ix_ord_cli` (`cliente_id`),
  KEY `ix_ord_venc` (`vence_en`,`estado`,`saldo`),
  KEY `ix_ord_prox` (`proxima_facturacion`),
  KEY `fk_ord_cot` (`cotizacion_id`),
  KEY `fk_ordenes_company_rfc` (`rfc_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `ordenes`
--

INSERT INTO `ordenes` (`id`, `cotizacion_id`, `cliente_id`, `total`, `saldo`, `estado`, `periodicidad`, `proxima_facturacion`, `vence_en`, `creado_en`, `rfc_id`, `billing_policy`, `cut_day`, `grace_days`, `suspend_after_days`) VALUES
(5, 2, 7, 19884.72, 0.00, 'activa', 'mensual', '2025-12-01', '2025-11-17', '2025-10-18 23:03:29', NULL, 'prepaid_anchor', NULL, 5, 10),
(6, 5, 8, 14230.88, 0.00, 'activa', 'mensual', NULL, '2025-12-27', '2025-11-27 20:11:52', 1, 'prepaid_anchor', NULL, 5, 10),
(8, 1, 10, 12302.96, 0.00, 'activa', 'mensual', '2025-12-01', NULL, '2025-11-28 22:56:15', 2, 'prepaid_anchor', NULL, 5, 10),
(9, 6, 11, 19884.72, 0.00, 'activa', 'mensual', '2025-12-01', NULL, '2025-11-28 23:12:48', 1, 'prepaid_anchor', NULL, 5, 10);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orden_items`
--

DROP TABLE IF EXISTS `orden_items`;
CREATE TABLE IF NOT EXISTS `orden_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orden_id` int NOT NULL,
  `concepto` varchar(160) NOT NULL,
  `tipo` enum('base','extra','descuento') NOT NULL DEFAULT 'base',
  `monto` decimal(12,2) NOT NULL,
  `periodicidad` enum('unico','mensual','bimestral') NOT NULL DEFAULT 'unico',
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `billing_type` enum('una_vez','recurrente') NOT NULL DEFAULT 'recurrente',
  `interval_unit` enum('mensual','anual') DEFAULT NULL,
  `interval_count` int DEFAULT NULL,
  `next_run` date DEFAULT NULL,
  `end_at` date DEFAULT NULL,
  `prorate` tinyint(1) NOT NULL DEFAULT '0',
  `pausado` tinyint(1) NOT NULL DEFAULT '0',
  `pausa_desde` date DEFAULT NULL,
  `reanudar_en` date DEFAULT NULL,
  `ultimo_periodo_inicio` date DEFAULT NULL,
  `ultimo_periodo_fin` date DEFAULT NULL,
  `ancla_ciclo` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_oit_ord` (`orden_id`),
  KEY `idx_orden_items_orden` (`orden_id`),
  KEY `idx_orden_items_next` (`next_run`),
  KEY `idx_orden_items_tipo` (`billing_type`,`pausado`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `orden_items`
--

INSERT INTO `orden_items` (`id`, `orden_id`, `concepto`, `tipo`, `monto`, `periodicidad`, `creado_en`, `billing_type`, `interval_unit`, `interval_count`, `next_run`, `end_at`, `prorate`, `pausado`, `pausa_desde`, `reanudar_en`, `ultimo_periodo_inicio`, `ultimo_periodo_fin`, `ancla_ciclo`) VALUES
(1, 5, 'cuenta - 1575', '', 1575.00, 'mensual', '2025-10-18 23:03:29', 'recurrente', NULL, NULL, '2025-12-01', NULL, 0, 0, '2025-11-26', '2025-11-26', '2025-11-01', '2025-11-30', '2025-11-09'),
(2, 5, 'publicaciones - 2363', '', 2363.00, 'mensual', '2025-10-18 23:03:29', 'recurrente', NULL, NULL, '2025-12-01', NULL, 0, 0, '2025-11-15', '2025-11-15', '2025-11-01', '2025-11-30', '2025-11-09'),
(3, 5, 'campañas - 1260', '', 1260.00, 'mensual', '2025-10-18 23:03:29', 'recurrente', NULL, NULL, '2025-12-01', NULL, 0, 0, '2025-11-15', '2025-11-15', '2025-11-01', '2025-11-30', '2025-11-09'),
(4, 5, 'reposteo - 1050', '', 1050.00, 'mensual', '2025-10-18 23:03:29', 'recurrente', NULL, NULL, '2025-12-01', NULL, 0, 0, '2025-11-15', '2025-11-15', '2025-11-01', '2025-11-30', '2025-11-09'),
(5, 5, 'stories - 1575', '', 1575.00, 'mensual', '2025-10-18 23:03:29', 'recurrente', NULL, NULL, '2025-12-01', NULL, 0, 0, '2025-11-15', '2025-11-15', '2025-11-01', '2025-11-30', '2025-11-09'),
(6, 5, 'imprenta - 1050', '', 1050.00, 'mensual', '2025-10-18 23:03:29', 'recurrente', NULL, NULL, '2025-12-01', NULL, 0, 0, '2025-11-15', '2025-11-15', '2025-11-01', '2025-11-30', '2025-11-09'),
(7, 5, 'fotos - 1750', '', 1750.00, 'mensual', '2025-10-18 23:03:29', 'recurrente', NULL, NULL, '2025-12-01', NULL, 0, 0, '2025-11-15', '2025-11-15', '2025-11-01', '2025-11-30', '2025-11-09'),
(8, 5, 'video - 1925', '', 1925.00, 'mensual', '2025-10-18 23:03:29', 'recurrente', NULL, NULL, '2025-12-01', NULL, 0, 0, '2025-11-15', '2025-11-15', '2025-11-01', '2025-11-30', '2025-11-09'),
(9, 5, 'ads - 1969', '', 1969.00, 'mensual', '2025-10-18 23:03:29', 'recurrente', NULL, NULL, '2025-12-01', NULL, 0, 0, '2025-11-15', '2025-11-15', '2025-11-01', '2025-11-30', '2025-11-09'),
(10, 5, 'web - 1575', '', 1575.00, 'mensual', '2025-10-18 23:03:29', 'recurrente', NULL, NULL, '2025-12-01', NULL, 0, 0, '2025-11-15', '2025-11-15', '2025-11-01', '2025-11-30', '2025-11-09'),
(11, 5, 'mkt - 1050', '', 1050.00, 'mensual', '2025-10-18 23:03:29', 'recurrente', NULL, NULL, '2025-12-01', NULL, 0, 0, '2025-11-15', '2025-11-15', '2025-11-01', '2025-11-30', '2025-11-09'),
(12, 6, 'cuenta - 1575', 'base', 1575.00, 'unico', '2025-11-27 20:11:52', 'recurrente', 'mensual', 1, '2025-12-27', NULL, 0, 0, '2025-11-27', '2025-11-27', NULL, NULL, NULL),
(13, 6, 'publicaciones - 1181', 'base', 1181.00, 'unico', '2025-11-27 20:11:52', 'recurrente', 'mensual', 1, '2025-12-27', NULL, 0, 0, NULL, NULL, NULL, NULL, NULL),
(14, 6, 'campañas - 630', 'base', 630.00, 'unico', '2025-11-27 20:11:52', 'recurrente', 'mensual', 1, '2025-12-27', NULL, 0, 0, NULL, NULL, NULL, NULL, NULL),
(15, 6, 'reposteo - 1050', 'base', 1050.00, 'unico', '2025-11-27 20:11:52', 'recurrente', 'mensual', 1, '2025-12-27', NULL, 0, 0, NULL, NULL, NULL, NULL, NULL),
(16, 6, 'stories - 0', 'base', 0.00, 'unico', '2025-11-27 20:11:52', 'una_vez', NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL),
(17, 6, 'imprenta - 525', 'base', 525.00, 'unico', '2025-11-27 20:11:52', 'recurrente', 'mensual', 1, '2025-12-27', NULL, 0, 0, NULL, NULL, NULL, NULL, NULL),
(18, 6, 'fotos - 1750', 'base', 1750.00, 'unico', '2025-11-27 20:11:52', 'recurrente', 'mensual', 1, '2025-12-27', NULL, 0, 0, NULL, NULL, NULL, NULL, NULL),
(19, 6, 'video - 963', 'base', 963.00, 'unico', '2025-11-27 20:11:52', 'recurrente', 'mensual', 1, '2025-12-27', NULL, 0, 0, NULL, NULL, NULL, NULL, NULL),
(20, 6, 'ads - 1969', 'base', 1969.00, 'unico', '2025-11-27 20:11:52', 'recurrente', 'mensual', 1, '2025-12-27', NULL, 0, 0, NULL, NULL, NULL, NULL, NULL),
(21, 6, 'web - sistema', 'base', 1575.00, 'unico', '2025-11-27 20:11:52', 'recurrente', 'mensual', 1, '2025-12-27', NULL, 0, 0, NULL, NULL, NULL, NULL, NULL),
(22, 6, 'mkt - 1050', 'base', 1050.00, 'unico', '2025-11-27 20:11:52', 'recurrente', 'mensual', 1, '2025-12-27', NULL, 0, 0, NULL, NULL, NULL, NULL, NULL),
(34, 8, 'cuenta - 1575', 'base', 1575.00, 'unico', '2025-11-28 22:56:15', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(35, 8, 'publicaciones - 1181', 'base', 1181.00, 'unico', '2025-11-28 22:56:15', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(36, 8, 'campañas - 630', 'base', 630.00, 'unico', '2025-11-28 22:56:15', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(37, 8, 'reposteo - 1050', 'base', 1050.00, 'unico', '2025-11-28 22:56:15', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(38, 8, 'stories - 788', 'base', 788.00, 'unico', '2025-11-28 22:56:15', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(39, 8, 'imprenta - 0', 'base', 0.00, 'unico', '2025-11-28 22:56:15', 'una_vez', NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL),
(40, 8, 'fotos - 875', 'base', 875.00, 'unico', '2025-11-28 22:56:15', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(41, 8, 'video - 963', 'base', 963.00, 'unico', '2025-11-28 22:56:15', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(42, 8, 'ads - 1969', 'base', 1969.00, 'unico', '2025-11-28 22:56:15', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(43, 8, 'web - 1575', 'base', 1575.00, 'unico', '2025-11-28 22:56:15', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(44, 8, 'mkt - 0', 'base', 0.00, 'unico', '2025-11-28 22:56:15', 'una_vez', NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, NULL),
(45, 9, 'cuenta - 1575', 'base', 1575.00, 'unico', '2025-11-28 23:12:48', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(46, 9, 'publicaciones - 2363', 'base', 2363.00, 'unico', '2025-11-28 23:12:48', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(47, 9, 'campañas - 1260', 'base', 1260.00, 'unico', '2025-11-28 23:12:48', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(48, 9, 'reposteo - 1050', 'base', 1050.00, 'unico', '2025-11-28 23:12:48', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(49, 9, 'stories - 1575', 'base', 1575.00, 'unico', '2025-11-28 23:12:48', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(50, 9, 'imprenta - 1050', 'base', 1050.00, 'unico', '2025-11-28 23:12:48', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(51, 9, 'fotos - 1750', 'base', 1750.00, 'unico', '2025-11-28 23:12:48', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(52, 9, 'video - 1925', 'base', 1925.00, 'unico', '2025-11-28 23:12:48', 'recurrente', 'mensual', 2, '2026-01-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(53, 9, 'ads - 1969', 'base', 1969.00, 'unico', '2025-11-28 23:12:48', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(54, 9, 'web - 1575', 'base', 1575.00, 'unico', '2025-11-28 23:12:48', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL),
(55, 9, 'mkt - 1050', 'base', 1050.00, 'unico', '2025-11-28 23:12:48', 'recurrente', 'mensual', 1, '2025-12-01', NULL, 0, 0, NULL, NULL, '2025-11-01', '2025-11-30', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

DROP TABLE IF EXISTS `pagos`;
CREATE TABLE IF NOT EXISTS `pagos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orden_id` int NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `metodo` enum('efectivo','transferencia') NOT NULL,
  `referencia` varchar(120) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `cargo_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_pago_ord` (`orden_id`),
  KEY `ix_pago_cargo` (`cargo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `pagos`
--

INSERT INTO `pagos` (`id`, `orden_id`, `monto`, `metodo`, `referencia`, `creado_en`, `cargo_id`) VALUES
(9, 9, 19884.72, 'efectivo', '6666666', '2025-11-29 00:55:55', 8),
(10, 5, 19884.72, 'efectivo', '', '2025-11-30 20:43:33', 9),
(11, 8, 12302.96, 'efectivo', '45454343', '2025-11-30 20:49:18', 10);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `selector` char(16) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `ip_solicita` varchar(45) DEFAULT NULL,
  `ua_solicita` varchar(255) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_selector` (`selector`),
  KEY `ix_user_exp` (`usuario_id`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`) VALUES
(1, 'admin'),
(3, 'cliente'),
(2, 'operador');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `pass_hash` varchar(255) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `foto_url` varchar(500) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `correo` (`correo`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `correo`, `pass_hash`, `activo`, `foto_url`, `creado_en`) VALUES
(1, 'Diego Rafael Mosso Nava', 'diegonava444@gmail.com', 'hola1234', 1, NULL, '2025-11-30 21:53:49'),
(2, 'Leonel Pimentel', 'admin@bananagap.com', '$2y$10$kltcf7D2RKb3RMfdxiqTLOgFUEfpij89qTTeW7o2e8irW4CFQA4NK', 1, NULL, '2025-11-30 22:28:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_rol`
--

DROP TABLE IF EXISTS `usuario_rol`;
CREATE TABLE IF NOT EXISTS `usuario_rol` (
  `usuario_id` int NOT NULL,
  `rol_id` int NOT NULL,
  PRIMARY KEY (`usuario_id`,`rol_id`),
  KEY `fk_ur_role` (`rol_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `usuario_rol`
--

INSERT INTO `usuario_rol` (`usuario_id`, `rol_id`) VALUES
(1, 1),
(2, 1);

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `bitacora`
--
ALTER TABLE `bitacora`
  ADD CONSTRAINT `fk_bit_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `cargos`
--
ALTER TABLE `cargos`
  ADD CONSTRAINT `cargos_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cargos_ibfk_2` FOREIGN KEY (`rfc_id`) REFERENCES `company_rfcs` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `cargo_items`
--
ALTER TABLE `cargo_items`
  ADD CONSTRAINT `cargo_items_ibfk_1` FOREIGN KEY (`cargo_id`) REFERENCES `cargos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cargo_items_ibfk_2` FOREIGN KEY (`orden_item_id`) REFERENCES `orden_items` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `chat_archivos`
--
ALTER TABLE `chat_archivos`
  ADD CONSTRAINT `fk_file_msg` FOREIGN KEY (`mensaje_id`) REFERENCES `chat_mensajes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `chat_hilos`
--
ALTER TABLE `chat_hilos`
  ADD CONSTRAINT `fk_chat_creador_cli` FOREIGN KEY (`creado_por_cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_chat_creador_user` FOREIGN KEY (`creado_por_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `chat_mensajes`
--
ALTER TABLE `chat_mensajes`
  ADD CONSTRAINT `fk_msg_cli` FOREIGN KEY (`autor_cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_msg_hilo` FOREIGN KEY (`hilo_id`) REFERENCES `chat_hilos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_msg_user` FOREIGN KEY (`autor_usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `chat_participantes`
--
ALTER TABLE `chat_participantes`
  ADD CONSTRAINT `fk_cp_cli` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cp_hilo` FOREIGN KEY (`hilo_id`) REFERENCES `chat_hilos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cp_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  ADD CONSTRAINT `fk_cot_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`);

--
-- Filtros para la tabla `cotizacion_items`
--
ALTER TABLE `cotizacion_items`
  ADD CONSTRAINT `fk_items_cot` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `fk_notif_cli` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `ordenes`
--
ALTER TABLE `ordenes`
  ADD CONSTRAINT `fk_ord_cli` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `fk_ord_cot` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id`),
  ADD CONSTRAINT `fk_ordenes_company_rfc` FOREIGN KEY (`rfc_id`) REFERENCES `company_rfcs` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `orden_items`
--
ALTER TABLE `orden_items`
  ADD CONSTRAINT `fk_oit_ord` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `fk_pago_cargo` FOREIGN KEY (`cargo_id`) REFERENCES `cargos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pago_ord` FOREIGN KEY (`orden_id`) REFERENCES `ordenes` (`id`);

--
-- Filtros para la tabla `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_pwreset_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuario_rol`
--
ALTER TABLE `usuario_rol`
  ADD CONSTRAINT `fk_ur_role` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ur_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
