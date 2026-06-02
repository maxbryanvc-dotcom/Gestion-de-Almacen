-- ============================================================
-- SETUP COMPLETO - Sistema ERP Almacén
-- Ejecutar en phpMyAdmin (sin seleccionar BD previa)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `almacen_sistema`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `almacen_sistema`;

-- ============================================================
-- TABLA: usuarios
-- ============================================================
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `usuario`         VARCHAR(50)  NOT NULL UNIQUE,
    `nombre_completo` VARCHAR(100) DEFAULT '',
    `dni`             VARCHAR(20)  DEFAULT '',
    `fecha_nacimiento` DATE        NULL,
    `celular`         VARCHAR(20)  DEFAULT '',
    `password`        VARCHAR(255) NOT NULL,
    `rol`             ENUM('admin','almacen','tecnico') NOT NULL DEFAULT 'tecnico',
    `estado`          TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_usuario` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: materiales
-- ============================================================
CREATE TABLE IF NOT EXISTS `materiales` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `codigo`      VARCHAR(50)  DEFAULT '',
    `nombre`      VARCHAR(200) NOT NULL,
    `descripcion` TEXT         NULL,
    `categoria`   VARCHAR(100) DEFAULT '',
    `unidad`      VARCHAR(50)  DEFAULT 'unidad',
    `stock`       INT          NOT NULL DEFAULT 0,
    `stock_minimo` INT         NOT NULL DEFAULT 0,
    `precio`      DECIMAL(10,2) DEFAULT 0.00,
    `ubicacion`   VARCHAR(100) DEFAULT '',
    `estado`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_codigo`    (`codigo`),
    INDEX `idx_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: tecnicos
-- ============================================================
CREATE TABLE IF NOT EXISTS `tecnicos` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `nombre`          VARCHAR(100) NOT NULL,
    `dni`             VARCHAR(20)  DEFAULT '',
    `especialidad`    VARCHAR(100) DEFAULT '',
    `celular`         VARCHAR(20)  DEFAULT '',
    `email`           VARCHAR(100) DEFAULT '',
    `estado`          TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: entradas
-- ============================================================
CREATE TABLE IF NOT EXISTS `entradas` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `material_id` INT          NOT NULL,
    `cantidad`    INT          NOT NULL,
    `proveedor`   VARCHAR(200) DEFAULT '',
    `factura`     VARCHAR(100) DEFAULT '',
    `precio_unit` DECIMAL(10,2) DEFAULT 0.00,
    `observacion` TEXT         NULL,
    `usuario`     VARCHAR(100) DEFAULT '',
    `fecha`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`material_id`) REFERENCES `materiales`(`id`) ON DELETE CASCADE,
    INDEX `idx_material` (`material_id`),
    INDEX `idx_fecha`    (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: salidas
-- ============================================================
CREATE TABLE IF NOT EXISTS `salidas` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `material_id` INT          NOT NULL,
    `tecnico_id`  INT          NULL,
    `cantidad`    INT          NOT NULL,
    `motivo`      VARCHAR(255) DEFAULT '',
    `observacion` TEXT         NULL,
    `usuario`     VARCHAR(100) DEFAULT '',
    `fecha`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`material_id`) REFERENCES `materiales`(`id`) ON DELETE CASCADE,
    INDEX `idx_material` (`material_id`),
    INDEX `idx_tecnico`  (`tecnico_id`),
    INDEX `idx_fecha`    (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: ordenes_trabajo
-- ============================================================
CREATE TABLE IF NOT EXISTS `ordenes_trabajo` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `codigo`       VARCHAR(50)  DEFAULT '',
    `descripcion`  TEXT         NOT NULL,
    `tecnico_id`   INT          NULL,
    `estado`       ENUM('pendiente','en_proceso','completado','cancelado') DEFAULT 'pendiente',
    `prioridad`    ENUM('baja','media','alta','urgente') DEFAULT 'media',
    `fecha_inicio` DATE         NULL,
    `fecha_fin`    DATE         NULL,
    `observacion`  TEXT         NULL,
    `usuario`      VARCHAR(100) DEFAULT '',
    `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tecnico` (`tecnico_id`),
    INDEX `idx_estado`  (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: requerimientos
-- ============================================================
CREATE TABLE IF NOT EXISTS `requerimientos` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `titulo`       VARCHAR(200) NOT NULL,
    `descripcion`  TEXT         NULL,
    `tecnico_id`   INT          NULL,
    `estado`       ENUM('pendiente','aprobado','rechazado','entregado') DEFAULT 'pendiente',
    `prioridad`    ENUM('baja','media','alta','urgente') DEFAULT 'media',
    `observacion`  TEXT         NULL,
    `usuario`      VARCHAR(100) DEFAULT '',
    `created_at`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_tecnico` (`tecnico_id`),
    INDEX `idx_estado`  (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: reingresos
-- ============================================================
CREATE TABLE IF NOT EXISTS `reingresos` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `material_id`    INT          NOT NULL,
    `tecnico_id`     INT          NULL,
    `cantidad`       INT          NOT NULL,
    `motivo`         VARCHAR(255) NOT NULL,
    `observacion`    TEXT         NULL,
    `registrado_por` VARCHAR(100) NOT NULL,
    `fecha`          DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`material_id`) REFERENCES `materiales`(`id`) ON DELETE CASCADE,
    INDEX `idx_material` (`material_id`),
    INDEX `idx_tecnico`  (`tecnico_id`),
    INDEX `idx_fecha`    (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: permisos_temp
-- ============================================================
CREATE TABLE IF NOT EXISTS `permisos_temp` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `codigo`      VARCHAR(10)  NOT NULL,
    `solicitante` VARCHAR(100) NOT NULL,
    `accion`      VARCHAR(100) NOT NULL,
    `recurso_id`  INT          NULL,
    `expira_en`   DATETIME     NOT NULL,
    `usado`       TINYINT(1)   NOT NULL DEFAULT 0,
    `creado_en`   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_codigo` (`codigo`),
    INDEX `idx_expira` (`expira_en`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLA: audit_log
-- ============================================================
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `usuario`     VARCHAR(100) NOT NULL,
    `rol`         VARCHAR(50)  NOT NULL,
    `accion`      VARCHAR(100) NOT NULL,
    `descripcion` TEXT         NULL,
    `ip`          VARCHAR(45)  NOT NULL,
    `fecha`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_usuario` (`usuario`),
    INDEX `idx_fecha`   (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- USUARIO ADMINISTRADOR INICIAL
-- Usuario: admin  |  Contraseña: admin123
-- CAMBIA LA CONTRASEÑA DESPUÉS DE INGRESAR
-- ============================================================
INSERT INTO `usuarios` (`usuario`, `nombre_completo`, `password`, `rol`, `estado`)
VALUES (
    'admin',
    'Administrador del Sistema',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    1
) ON DUPLICATE KEY UPDATE `id` = `id`;
