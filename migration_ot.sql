-- ============================================================
-- MIGRACIÓN: Módulo Órdenes de Trabajo (OT)
-- Ejecutar en phpMyAdmin sobre almacen_sistema
-- ============================================================

-- Tabla principal de órdenes de trabajo
CREATE TABLE IF NOT EXISTS `ordenes_trabajo` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `numero_ot`       VARCHAR(20)  NOT NULL,
    `tipo`            ENUM('IN','CM','MJ','REUB','REAC') NOT NULL,
    `tecnico_id`      INT          NOT NULL,
    `estado`          ENUM('Programado','Aprobado','Ejecutado') NOT NULL DEFAULT 'Programado',
    `serie_medidor`   VARCHAR(50)  DEFAULT NULL,
    `fecha`           DATE         NOT NULL,
    `registrado_por`  VARCHAR(100) NOT NULL,
    `observacion`     TEXT         DEFAULT NULL,
    `created_at`      DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tecnico_id`) REFERENCES `tecnicos`(`id`),
    UNIQUE KEY `uq_numero_ot` (`numero_ot`),
    INDEX `idx_tecnico`  (`tecnico_id`),
    INDEX `idx_fecha`    (`fecha`),
    INDEX `idx_estado`   (`estado`),
    INDEX `idx_tipo`     (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Detalle de materiales usados por OT
CREATE TABLE IF NOT EXISTS `detalle_ot` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `ot_id`       INT NOT NULL,
    `material_id` INT NOT NULL,
    `cantidad`    DECIMAL(10,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (`ot_id`)       REFERENCES `ordenes_trabajo`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`material_id`) REFERENCES `materiales`(`id`),
    INDEX `idx_ot`       (`ot_id`),
    INDEX `idx_material` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Agregar campo codigo_electrosur a materiales (código del catálogo ElectroSur)
ALTER TABLE `materiales`
    ADD COLUMN IF NOT EXISTS `codigo_electrosur` VARCHAR(20) DEFAULT '' AFTER `codigo`;
