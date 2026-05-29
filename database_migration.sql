-- ============================================================
-- MIGRACIÓN DE BASE DE DATOS - Sistema ERP Almacén
-- Ejecutar en phpMyAdmin sobre la BD: almacen_sistema
-- ============================================================

-- Ampliar tabla usuarios con campos completos
ALTER TABLE `usuarios`
    ADD COLUMN IF NOT EXISTS `nombre_completo` VARCHAR(100) DEFAULT '' AFTER `usuario`,
    ADD COLUMN IF NOT EXISTS `dni`             VARCHAR(20)  DEFAULT '' AFTER `nombre_completo`,
    ADD COLUMN IF NOT EXISTS `fecha_nacimiento` DATE        NULL        AFTER `dni`,
    ADD COLUMN IF NOT EXISTS `celular`         VARCHAR(20)  DEFAULT '' AFTER `fecha_nacimiento`,
    ADD COLUMN IF NOT EXISTS `estado`          TINYINT(1)   NOT NULL DEFAULT 1 AFTER `celular`,
    ADD COLUMN IF NOT EXISTS `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP AFTER `estado`;

-- Índice único en usuario (si no existe)
ALTER TABLE `usuarios` ADD UNIQUE INDEX IF NOT EXISTS `idx_usuario` (`usuario`);

-- ============================================================
-- TABLA: permisos_temp
-- Códigos de autorización temporal para almaceneros
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
-- Registro de auditoría de todas las acciones críticas
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
-- TABLA: reingresos (mejorada si ya existe)
-- ============================================================
CREATE TABLE IF NOT EXISTS `reingresos` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `material_id` INT          NOT NULL,
    `tecnico_id`  INT          NULL,
    `cantidad`    INT          NOT NULL,
    `motivo`      VARCHAR(255) NOT NULL,
    `observacion` TEXT         NULL,
    `registrado_por` VARCHAR(100) NOT NULL,
    `fecha`       DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`material_id`) REFERENCES `materiales`(`id`) ON DELETE CASCADE,
    INDEX `idx_material`  (`material_id`),
    INDEX `idx_tecnico`   (`tecnico_id`),
    INDEX `idx_fecha`     (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Asegurar que entradas y salidas tienen columna usuario
-- ============================================================
ALTER TABLE `entradas`
    ADD COLUMN IF NOT EXISTS `usuario` VARCHAR(100) DEFAULT '' AFTER `observacion`;

ALTER TABLE `salidas`
    ADD COLUMN IF NOT EXISTS `usuario` VARCHAR(100) DEFAULT '' AFTER `observacion`;
