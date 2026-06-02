-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 02-06-2026 a las 14:24:11
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `almacen_sistema`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `usuario` varchar(100) NOT NULL,
  `rol` varchar(50) NOT NULL,
  `accion` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `ip` varchar(45) NOT NULL,
  `fecha` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `audit_log`
--

INSERT INTO `audit_log` (`id`, `usuario`, `rol`, `accion`, `descripcion`, `ip`, `fecha`) VALUES
(1, 'admin', 'admin', 'SUBIR_PLANTILLA', 'Plantilla: Requerimientos (word)', '::1', '2026-05-31 23:25:14'),
(2, 'admin', 'admin', 'TOGGLE_PLANTILLA', 'ID 1', '::1', '2026-05-31 23:25:31'),
(3, 'admin', 'admin', 'TOGGLE_PLANTILLA', 'ID 1', '::1', '2026-05-31 23:25:32'),
(4, 'admin', 'admin', 'ELIMINAR_PLANTILLA', 'ID 1', '::1', '2026-05-31 23:25:49'),
(5, 'admin', 'admin', 'SUBIR_PLANTILLA', 'Plantilla: Requerimientos (word)', '::1', '2026-05-31 23:26:29'),
(6, 'admin', 'admin', 'ENTRADA_MATERIAL', 'Material ID 8, cantidad 100', '::1', '2026-05-31 23:27:03'),
(7, 'admin', 'admin', 'CREAR_REQUERIMIENTO', 'Código: REQ-260531-6D71, ID: 26, ítems: 1', '::1', '2026-05-31 23:27:45'),
(8, 'admin', 'admin', 'GENERAR_PLANTILLA', 'Plantilla ID 2: Requerimientos', '::1', '2026-05-31 23:27:57'),
(9, 'admin', 'admin', 'EXPORT_TRABAJOS', 'Trabajos ejecutados 2026-05', '::1', '2026-05-31 23:42:46'),
(10, 'admin', 'admin', 'CREAR_REQUERIMIENTO', 'Código: REQ-260531-1888, ID: 27, ítems: 1', '::1', '2026-05-31 23:52:43'),
(11, 'admin', 'admin', 'GENERAR_WORD', 'Carta pedido requerimiento ID 26', '::1', '2026-05-31 23:52:57'),
(12, 'admin', 'admin', 'GENERAR_WORD', 'Carta pedido requerimiento ID 26', '::1', '2026-05-31 23:52:57'),
(13, 'admin', 'admin', 'CREAR_TECNICO', 'Técnico: Henrry Limo Siguiña', '::1', '2026-06-01 21:05:12'),
(14, 'admin', 'admin', 'CREAR_TECNICO', 'Técnico: Agripino Santos Quispe', '::1', '2026-06-01 21:05:22'),
(15, 'admin', 'admin', 'CREAR_TECNICO', 'Técnico: Lucho Moreano Caceres', '::1', '2026-06-01 21:05:40'),
(16, 'admin', 'admin', 'CREAR_TECNICO', 'Técnico: Percy Ccayo Figueroa', '::1', '2026-06-01 21:05:57'),
(17, 'admin', 'admin', 'CREAR_TECNICO', 'Técnico: Eduardo Puma Mamani', '::1', '2026-06-01 21:06:12'),
(18, 'admin', 'admin', 'CREAR_TECNICO', 'Técnico: Wilber Rios Ccuyo', '::1', '2026-06-01 21:06:26'),
(19, 'admin', 'admin', 'CREAR_TECNICO', 'Técnico: Cesar Oscar Araujo Vera', '::1', '2026-06-01 21:07:06'),
(20, 'admin', 'admin', 'CREAR_TECNICO', 'Técnico: Ernesto Salas Vera', '::1', '2026-06-01 21:07:24'),
(21, 'admin', 'admin', 'CREAR_TECNICO', 'Técnico: Marios Quispe guillen', '::1', '2026-06-01 21:07:41'),
(22, 'admin', 'admin', 'EDITAR_TECNICO', 'ID 3: Agripino Santos Quispe', '::1', '2026-06-01 21:07:53'),
(23, 'admin', 'admin', 'EDITAR_TECNICO', 'ID 6: Eduardo Puma Mamani', '::1', '2026-06-01 21:07:57'),
(24, 'admin', 'admin', 'EDITAR_TECNICO', 'ID 2: Henrry Limo Siguiña', '::1', '2026-06-01 21:08:00'),
(25, 'admin', 'admin', 'EDITAR_TECNICO', 'ID 2: Henrry Limo Siguiña', '::1', '2026-06-01 21:08:04'),
(26, 'admin', 'admin', 'EDITAR_TECNICO', 'ID 4: Lucho Moreano Caceres', '::1', '2026-06-01 21:08:09'),
(27, 'admin', 'admin', 'EDITAR_TECNICO', 'ID 5: Percy Ccayo Figueroa', '::1', '2026-06-01 21:08:14'),
(28, 'admin', 'admin', 'EDITAR_TECNICO', 'ID 7: Wilber Rios Ccuyo', '::1', '2026-06-01 21:08:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_ot`
--

CREATE TABLE `detalle_ot` (
  `id` int(11) NOT NULL,
  `ot_id` int(11) DEFAULT NULL,
  `material_id` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_requerimiento`
--

CREATE TABLE `detalle_requerimiento` (
  `id` int(11) NOT NULL,
  `requerimiento_id` int(11) DEFAULT NULL,
  `material_id` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_requerimiento`
--

INSERT INTO `detalle_requerimiento` (`id`, `requerimiento_id`, `material_id`, `cantidad`) VALUES
(16, 26, 8, 20),
(17, 27, 8, 20);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entradas`
--

CREATE TABLE `entradas` (
  `id` int(11) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `usuario` varchar(100) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `entradas`
--

INSERT INTO `entradas` (`id`, `material_id`, `cantidad`, `fecha`, `observacion`, `usuario`) VALUES
(1, 1, 50, '2026-03-21', NULL, ''),
(2, 1, 10, '2026-03-22', NULL, ''),
(4, 1, 20, '2026-04-05', NULL, ''),
(5, 1, 15, '2026-04-05', NULL, ''),
(6, 8, 100, NULL, '', 'admin');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial`
--

CREATE TABLE `historial` (
  `id` int(11) NOT NULL,
  `tipo` varchar(20) DEFAULT NULL,
  `material_id` int(11) DEFAULT NULL,
  `usuario` varchar(50) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `historial`
--

INSERT INTO `historial` (`id`, `tipo`, `material_id`, `usuario`, `fecha`) VALUES
(1, 'ELIMINACION', 1, 'admin', '2026-03-22 16:14:10'),
(2, 'REQUERIMIENTO', 8, 'admin', '2026-05-31 23:27:45'),
(3, 'REQUERIMIENTO', 8, 'admin', '2026-05-31 23:52:43');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materiales`
--

CREATE TABLE `materiales` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `codigo_electrosur` varchar(20) DEFAULT '',
  `nombre` varchar(150) DEFAULT NULL,
  `unidad` varchar(50) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `materiales`
--

INSERT INTO `materiales` (`id`, `codigo`, `codigo_electrosur`, `nombre`, `unidad`, `stock`, `activo`) VALUES
(1, 'mat-001', '', 'cable utp', 'metros', 123, 0),
(8, '314720', '', 'CABLE CONCENTRICO D/ AL 2X16MM2', 'Metro', 60, 1),
(9, '305559', '', 'CABLE CONCENTRICO D/ AL 2X6MM2', 'Metro', 0, 1),
(10, '314721', '', 'CABLE CONCENTRICO D/ AL 3X16MM2', 'Metro', 0, 1),
(11, '317126', '', 'CABLE CONCENTRICO D/ AL 4X16MM2', 'Metro', 0, 1),
(12, '304249', '', 'CAJA PORTAMEDIDOR 1F POLIMERICO', 'Unidad', 0, 1),
(13, '309778', '', 'CAJATOMA METALICA ELSE 1', 'Unidad', 0, 1),
(14, '306370', '', 'CAJATOMA METALICA TRIFASICA ESTANDAR', 'Unidad', 0, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos`
--

CREATE TABLE `movimientos` (
  `id` int(11) NOT NULL,
  `tipo` varchar(20) DEFAULT NULL,
  `material_id` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `tecnico` varchar(100) DEFAULT NULL,
  `fecha` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `movimientos`
--

INSERT INTO `movimientos` (`id`, `tipo`, `material_id`, `cantidad`, `tecnico`, `fecha`) VALUES
(1, 'entrada', 1, 10, 'Almacen', '2026-03-22 20:44:01'),
(2, 'salida', 1, 5, 'Juan Perez', '2026-03-22 20:44:35'),
(3, 'entrada', 3, 12, 'Almacen', '2026-03-24 06:04:39'),
(4, 'salida', 3, 5, 'Juan Perez', '2026-03-24 06:05:04'),
(5, 'salida', 1, 50, 'Juan Perez', '2026-04-05 06:49:02'),
(6, 'entrada', 1, 20, 'Almacen', '2026-04-05 06:50:49'),
(7, 'salida', 1, 20, 'Juan Perez', '2026-04-05 06:53:10'),
(8, 'entrada', 1, 15, 'Almacen', '2026-04-05 06:53:18');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_trabajo`
--

CREATE TABLE `ordenes_trabajo` (
  `id` int(11) NOT NULL,
  `numero_ot` varchar(20) NOT NULL,
  `tipo` enum('IN','CM','MJ','REUB','REAC') NOT NULL,
  `tecnico_id` int(11) NOT NULL,
  `estado` enum('Programado','Aprobado','Ejecutado') NOT NULL DEFAULT 'Programado',
  `serie_medidor` varchar(50) DEFAULT NULL,
  `fecha` date NOT NULL,
  `registrado_por` varchar(100) NOT NULL,
  `observacion` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orden_trabajo`
--

CREATE TABLE `orden_trabajo` (
  `id` int(11) NOT NULL,
  `codigo_ot` varchar(50) DEFAULT NULL,
  `tecnico_id` int(11) DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  `fecha` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plantillas`
--

CREATE TABLE `plantillas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` enum('word','excel','pdf') NOT NULL,
  `modulo` varchar(50) NOT NULL DEFAULT 'general',
  `archivo` varchar(255) NOT NULL,
  `variables_detectadas` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `plantillas`
--

INSERT INTO `plantillas` (`id`, `nombre`, `descripcion`, `tipo`, `modulo`, `archivo`, `variables_detectadas`, `activo`, `creado_por`, `created_at`, `updated_at`) VALUES
(2, 'Requerimientos', 'Requerimiento 01/06/2026', 'word', 'requerimiento', '20260531_232629_requerimientos.docx', '', 1, 'admin', '2026-05-31 23:26:29', '2026-05-31 23:26:29');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reingresos`
--

CREATE TABLE `reingresos` (
  `id` int(11) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `tecnico_id` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `motivo` varchar(150) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `registrado_por` varchar(100) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `requerimientos`
--

CREATE TABLE `requerimientos` (
  `id` int(11) NOT NULL,
  `codigo_req` varchar(50) DEFAULT NULL,
  `tipo_liq` enum('Instalaciones','Mantenimiento') NOT NULL DEFAULT 'Instalaciones',
  `fecha` date DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  `material_id` int(11) DEFAULT NULL,
  `tecnico_id` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `aprobado_por` varchar(50) DEFAULT NULL,
  `observacion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `requerimientos`
--

INSERT INTO `requerimientos` (`id`, `codigo_req`, `tipo_liq`, `fecha`, `estado`, `material_id`, `tecnico_id`, `cantidad`, `aprobado_por`) VALUES
(26, 'REQ-260531-6D71', 'Instalaciones', '2026-05-31', 'Aprobado', NULL, NULL, NULL, 'admin'),
(27, 'REQ-260531-1888', 'Instalaciones', '2026-05-31', 'Pendiente', NULL, NULL, NULL, 'admin');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `salidas`
--

CREATE TABLE `salidas` (
  `id` int(11) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `tecnico_id` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `observacion` text DEFAULT NULL,
  `usuario` varchar(100) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `salidas`
--

INSERT INTO `salidas` (`id`, `material_id`, `tecnico_id`, `cantidad`, `fecha`, `observacion`, `usuario`) VALUES
(1, 1, NULL, 5, '2026-03-22', NULL, ''),
(3, 1, NULL, 50, '2026-04-05', NULL, ''),
(4, 1, NULL, 20, '2026-04-05', NULL, '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tecnicos`
--

CREATE TABLE `tecnicos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `dni` varchar(20) DEFAULT '',
  `celular` varchar(20) DEFAULT '',
  `cargo` varchar(100) DEFAULT '',
  `area` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tecnicos`
--

INSERT INTO `tecnicos` (`id`, `nombre`, `dni`, `celular`, `cargo`, `area`) VALUES
(2, 'Henrry Limo Siguiña', '', '', 'Tenico Electricista', ''),
(3, 'Agripino Santos Quispe', '', '', 'Tenico Electricista', ''),
(4, 'Lucho Moreano Caceres', '', '', 'Tenico Electricista', ''),
(5, 'Percy Ccayo Figueroa', '', '', 'Tenico Electricista', ''),
(6, 'Eduardo Puma Mamani', '', '', 'Tenico Electricista', ''),
(7, 'Wilber Rios Ccuyo', '', '', 'Tenico Electricista', ''),
(8, 'Cesar Oscar Araujo Vera', '', '', 'Tenico Electricista', ''),
(9, 'Ernesto Salas Vera', '', '', 'Tenico Electricista', ''),
(10, 'Marios Quispe guillen', '', '', 'Tenico Electricista', '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) DEFAULT NULL,
  `nombre_completo` varchar(100) DEFAULT '',
  `dni` varchar(20) DEFAULT '',
  `fecha_nacimiento` date DEFAULT NULL,
  `celular` varchar(20) DEFAULT '',
  `password` varchar(100) DEFAULT NULL,
  `rol` varchar(20) DEFAULT NULL,
  `estado` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `nombre_completo`, `dni`, `fecha_nacimiento`, `celular`, `password`, `rol`, `estado`, `created_at`) VALUES
(2, 'admin', '', '', NULL, '', '$2y$10$rOgd0A9ZazXaF3mkSnGCBeqUpgynwTSPA/12PylNBVpmUy8HnPYl.', 'admin', 1, '2026-05-12 22:40:18'),
(10, 'almacen', '', '', NULL, '', '$2y$10$al8W9tgxfchLXHWwIuQoF.0wHGQCoN6PztQ9rkGPhZSuZIapFsqRW', 'almacen', 1, '2026-05-12 22:40:18');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `detalle_ot`
--
ALTER TABLE `detalle_ot`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ot_id` (`ot_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indices de la tabla `detalle_requerimiento`
--
ALTER TABLE `detalle_requerimiento`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requerimiento_id` (`requerimiento_id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indices de la tabla `entradas`
--
ALTER TABLE `entradas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_id` (`material_id`);

--
-- Indices de la tabla `historial`
--
ALTER TABLE `historial`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `materiales`
--
ALTER TABLE `materiales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo` (`codigo`);

--
-- Indices de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `ordenes_trabajo`
--
ALTER TABLE `ordenes_trabajo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_numero_ot` (`numero_ot`),
  ADD KEY `tecnico_id` (`tecnico_id`);

--
-- Indices de la tabla `orden_trabajo`
--
ALTER TABLE `orden_trabajo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tecnico_id` (`tecnico_id`);

--
-- Indices de la tabla `plantillas`
--
ALTER TABLE `plantillas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_modulo` (`modulo`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indices de la tabla `reingresos`
--
ALTER TABLE `reingresos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `tecnico_id` (`tecnico_id`);

--
-- Indices de la tabla `requerimientos`
--
ALTER TABLE `requerimientos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `salidas`
--
ALTER TABLE `salidas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `material_id` (`material_id`),
  ADD KEY `tecnico_id` (`tecnico_id`);

--
-- Indices de la tabla `tecnicos`
--
ALTER TABLE `tecnicos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT de la tabla `detalle_ot`
--
ALTER TABLE `detalle_ot`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_requerimiento`
--
ALTER TABLE `detalle_requerimiento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `entradas`
--
ALTER TABLE `entradas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `historial`
--
ALTER TABLE `historial`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `materiales`
--
ALTER TABLE `materiales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `ordenes_trabajo`
--
ALTER TABLE `ordenes_trabajo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `orden_trabajo`
--
ALTER TABLE `orden_trabajo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `plantillas`
--
ALTER TABLE `plantillas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `reingresos`
--
ALTER TABLE `reingresos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `requerimientos`
--
ALTER TABLE `requerimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT de la tabla `salidas`
--
ALTER TABLE `salidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tecnicos`
--
ALTER TABLE `tecnicos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_ot`
--
ALTER TABLE `detalle_ot`
  ADD CONSTRAINT `detalle_ot_ibfk_1` FOREIGN KEY (`ot_id`) REFERENCES `ordenes_trabajo` (`id`),
  ADD CONSTRAINT `detalle_ot_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`id`);

--
-- Filtros para la tabla `detalle_requerimiento`
--
ALTER TABLE `detalle_requerimiento`
  ADD CONSTRAINT `detalle_requerimiento_ibfk_1` FOREIGN KEY (`requerimiento_id`) REFERENCES `requerimientos` (`id`),
  ADD CONSTRAINT `detalle_requerimiento_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`id`);

--
-- Filtros para la tabla `entradas`
--
ALTER TABLE `entradas`
  ADD CONSTRAINT `entradas_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`id`);

--
-- Filtros para la tabla `ordenes_trabajo`
--
ALTER TABLE `ordenes_trabajo`
  ADD CONSTRAINT `ordenes_trabajo_ibfk_1` FOREIGN KEY (`tecnico_id`) REFERENCES `tecnicos` (`id`);

--
-- Filtros para la tabla `orden_trabajo`
--
ALTER TABLE `orden_trabajo`
  ADD CONSTRAINT `orden_trabajo_ibfk_1` FOREIGN KEY (`tecnico_id`) REFERENCES `tecnicos` (`id`);

--
-- Filtros para la tabla `reingresos`
--
ALTER TABLE `reingresos`
  ADD CONSTRAINT `reingresos_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`id`),
  ADD CONSTRAINT `reingresos_ibfk_2` FOREIGN KEY (`tecnico_id`) REFERENCES `tecnicos` (`id`);

--
-- Filtros para la tabla `salidas`
--
ALTER TABLE `salidas`
  ADD CONSTRAINT `salidas_ibfk_1` FOREIGN KEY (`material_id`) REFERENCES `materiales` (`id`),
  ADD CONSTRAINT `salidas_ibfk_2` FOREIGN KEY (`tecnico_id`) REFERENCES `tecnicos` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
