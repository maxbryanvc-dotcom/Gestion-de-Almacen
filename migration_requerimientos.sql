-- ============================================================
-- MIGRACIÓN: Módulo Requerimientos v2
-- Ejecutar en phpMyAdmin sobre almacen_sistema
-- ============================================================

-- Ampliar tabla tecnicos con nuevos campos
ALTER TABLE `tecnicos`
    ADD COLUMN IF NOT EXISTS `dni`     VARCHAR(20)  DEFAULT '' AFTER `nombre`,
    ADD COLUMN IF NOT EXISTS `celular` VARCHAR(20)  DEFAULT '' AFTER `dni`,
    ADD COLUMN IF NOT EXISTS `cargo`   VARCHAR(100) DEFAULT '' AFTER `celular`;

-- Asegurar campo aprobado_por en requerimientos
ALTER TABLE `requerimientos`
    ADD COLUMN IF NOT EXISTS `observacion` TEXT NULL AFTER `aprobado_por`;

-- Índice para búsqueda rápida de materiales por nombre
ALTER TABLE `materiales`
    ADD INDEX IF NOT EXISTS `idx_nombre` (`nombre`);
