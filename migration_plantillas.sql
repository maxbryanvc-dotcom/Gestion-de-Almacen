-- ============================================================
-- MIGRACIÓN: Módulo de Plantillas Dinámicas
-- Ejecutar en phpMyAdmin sobre almacen_sistema
-- ============================================================

CREATE TABLE IF NOT EXISTS `plantillas` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `nombre`              VARCHAR(150) NOT NULL,
    `descripcion`         TEXT         DEFAULT NULL,
    `tipo`                ENUM('word','excel','pdf') NOT NULL,
    `modulo`              VARCHAR(50)  NOT NULL DEFAULT 'general',
    `archivo`             VARCHAR(255) NOT NULL,
    `variables_detectadas` TEXT        DEFAULT NULL,
    `activo`              TINYINT(1)   NOT NULL DEFAULT 1,
    `creado_por`          VARCHAR(100) NOT NULL,
    `created_at`          DATETIME     DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tipo`   (`tipo`),
    INDEX `idx_modulo` (`modulo`),
    INDEX `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
