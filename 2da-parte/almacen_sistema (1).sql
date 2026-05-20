-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 11-05-2026 a las 02:48:54
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
(1, 1, 1, 30),
(2, 2, 1, 30),
(3, 3, 1, 30),
(4, 4, 1, 20),
(5, 5, 1, 30),
(6, 6, 1, 50),
(8, 18, 1, 20),
(9, 19, 1, 20),
(10, 20, 1, 20),
(11, 21, 1, 5),
(12, 22, 1, 50),
(13, 23, 1, 50);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entradas`
--

CREATE TABLE `entradas` (
  `id` int(11) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `entradas`
--

INSERT INTO `entradas` (`id`, `material_id`, `cantidad`, `fecha`) VALUES
(1, 1, 50, '2026-03-21'),
(2, 1, 10, '2026-03-22'),
(4, 1, 20, '2026-04-05'),
(5, 1, 15, '2026-04-05');

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
(1, 'ELIMINACION', 1, 'admin', '2026-03-22 16:14:10');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materiales`
--

CREATE TABLE `materiales` (
  `id` int(11) NOT NULL,
  `codigo` varchar(50) DEFAULT NULL,
  `nombre` varchar(150) DEFAULT NULL,
  `unidad` varchar(50) DEFAULT NULL,
  `stock` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `materiales`
--

INSERT INTO `materiales` (`id`, `codigo`, `nombre`, `unidad`, `stock`) VALUES
(1, 'mat-001', 'cable utp', 'metros', 123);

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
-- Estructura de tabla para la tabla `reingresos`
--

CREATE TABLE `reingresos` (
  `id` int(11) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `tecnico_id` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `motivo` varchar(150) DEFAULT NULL,
  `fecha` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `requerimientos`
--

CREATE TABLE `requerimientos` (
  `id` int(11) NOT NULL,
  `codigo_req` varchar(50) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `estado` varchar(50) DEFAULT NULL,
  `material_id` int(11) DEFAULT NULL,
  `tecnico_id` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `aprobado_por` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `requerimientos`
--

INSERT INTO `requerimientos` (`id`, `codigo_req`, `fecha`, `estado`, `material_id`, `tecnico_id`, `cantidad`, `aprobado_por`) VALUES
(1, 'REQ-483', '2026-03-21', 'Pendiente', NULL, NULL, NULL, NULL),
(2, 'REQ-855', '2026-03-21', 'Pendiente', NULL, NULL, NULL, NULL),
(3, 'REQ-910', '2026-03-21', 'Pendiente', NULL, NULL, NULL, NULL),
(4, 'REQ-682', '2026-03-21', 'Pendiente', NULL, NULL, NULL, NULL),
(5, 'REQ-728', '2026-03-22', 'Pendiente', NULL, NULL, NULL, NULL),
(6, 'REQ-990', '2026-03-24', 'Pendiente', NULL, NULL, NULL, NULL),
(7, 'REQ-812', '2026-03-25', 'Pendiente', NULL, NULL, NULL, NULL),
(8, 'REQ-365', '2026-04-05', 'Pendiente', NULL, NULL, NULL, NULL),
(9, 'REQ-726', '2026-04-05', 'Pendiente', NULL, NULL, NULL, NULL),
(10, NULL, '2026-04-05', NULL, 1, 1, 12, NULL),
(11, NULL, '2026-04-12', 'Aprobado', 1, 1, 20, 'almacen'),
(12, NULL, '2026-04-12', 'Aprobado', 1, 1, 10, 'almacen'),
(13, NULL, '2026-04-12', 'Aprobado', 1, 1, 10, 'almacen'),
(14, NULL, '2026-04-12', 'Aprobado', 1, 1, 10, 'almacen'),
(15, NULL, '2026-04-12', 'Aprobado', 1, 1, 10, 'almacen'),
(16, NULL, '2026-04-12', 'Aprobado', 1, 1, 20, 'almacen'),
(17, NULL, '2026-04-12', 'Aprobado', 1, 1, 30, 'almacen'),
(18, NULL, '2026-04-18', 'Pendiente', NULL, NULL, NULL, ''),
(19, NULL, '2026-04-18', 'Pendiente', NULL, NULL, NULL, ''),
(20, NULL, '2026-04-30', 'Pendiente', NULL, NULL, NULL, ''),
(21, NULL, '2026-04-30', 'Pendiente', NULL, NULL, NULL, ''),
(22, NULL, '2026-04-30', 'Aprobado', NULL, NULL, NULL, NULL),
(23, NULL, '2026-04-30', 'Aprobado', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `salidas`
--

CREATE TABLE `salidas` (
  `id` int(11) NOT NULL,
  `material_id` int(11) DEFAULT NULL,
  `tecnico_id` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `salidas`
--

INSERT INTO `salidas` (`id`, `material_id`, `tecnico_id`, `cantidad`, `fecha`) VALUES
(1, 1, 1, 5, '2026-03-22'),
(3, 1, 1, 50, '2026-04-05'),
(4, 1, 1, 20, '2026-04-05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tecnicos`
--

CREATE TABLE `tecnicos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tecnicos`
--

INSERT INTO `tecnicos` (`id`, `nombre`, `area`) VALUES
(1, 'Juan Perez', 'Soporte');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `usuario` varchar(50) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `rol` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `usuario`, `password`, `rol`) VALUES
(2, 'admin', '$2y$10$rOgd0A9ZazXaF3mkSnGCBeqUpgynwTSPA/12PylNBVpmUy8HnPYl.', 'admin'),
(5, 'admin', '123456', 'admin'),
(7, 'admin', '$2y$10$I7herOicvnKU1WYbMs5IveUCtuubtMjmiAistBU94dBZzXkcb02Ku', NULL),
(8, 'admin', '$2y$10$IZtYZElSqfbe/mlkr7dO4O./INpTOozJ7lB4F0F7lENrKoPpOT6lm', NULL),
(10, 'almacen', '$2y$10$al8W9tgxfchLXHWwIuQoF.0wHGQCoN6PztQ9rkGPhZSuZIapFsqRW', 'almacen');

--
-- Índices para tablas volcadas
--

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
-- Indices de la tabla `orden_trabajo`
--
ALTER TABLE `orden_trabajo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tecnico_id` (`tecnico_id`);

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
-- AUTO_INCREMENT de la tabla `detalle_ot`
--
ALTER TABLE `detalle_ot`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_requerimiento`
--
ALTER TABLE `detalle_requerimiento`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `entradas`
--
ALTER TABLE `entradas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `historial`
--
ALTER TABLE `historial`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `materiales`
--
ALTER TABLE `materiales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `orden_trabajo`
--
ALTER TABLE `orden_trabajo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reingresos`
--
ALTER TABLE `reingresos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `requerimientos`
--
ALTER TABLE `requerimientos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT de la tabla `salidas`
--
ALTER TABLE `salidas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `tecnicos`
--
ALTER TABLE `tecnicos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  ADD CONSTRAINT `detalle_ot_ibfk_1` FOREIGN KEY (`ot_id`) REFERENCES `orden_trabajo` (`id`),
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
