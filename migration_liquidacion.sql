-- ============================================================
-- MIGRACIÓN: Módulo Liquidación Mensual
-- Ejecutar en phpMyAdmin sobre almacen_sistema
-- ============================================================

-- Agregar tipo de liquidación a requerimientos
ALTER TABLE `requerimientos`
    ADD COLUMN IF NOT EXISTS `tipo_liq`
        ENUM('Instalaciones','Mantenimiento') NOT NULL DEFAULT 'Instalaciones'
        AFTER `codigo_req`;

-- Tabla para guardar las fechas de cada pedido por mes
-- (cada mes puede tener hasta 3 pedidos hacia ElectroSur)
CREATE TABLE IF NOT EXISTS `pedidos_mes` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `mes`           CHAR(7)  NOT NULL,          -- 'YYYY-MM'
    `tipo_liq`      ENUM('Instalaciones','Mantenimiento') NOT NULL,
    `numero_pedido` TINYINT  NOT NULL DEFAULT 1, -- 1, 2, 3
    `fecha_pedido`  DATE     NOT NULL,
    `req_id`        INT      NULL,              -- requerimiento asociado
    `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_pedido_mes` (`mes`,`tipo_liq`,`numero_pedido`),
    FOREIGN KEY (`req_id`) REFERENCES `requerimientos`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
