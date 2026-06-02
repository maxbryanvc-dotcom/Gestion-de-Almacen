-- ============================================================
-- SEGURIDAD DE BASE DE DATOS — SIGMA ERP
-- Ejecutar en phpMyAdmin como root
-- ============================================================

-- 1. Crear usuario dedicado con contraseña fuerte
--    (solo puede conectarse desde localhost)
CREATE USER IF NOT EXISTS 'maxBryan'@'localhost'
    IDENTIFIED BY '1996max2307';

-- Para Docker (conexión desde contenedor):
CREATE USER IF NOT EXISTS 'maxBryan'@'%'
    IDENTIFIED BY '1996max2307';

-- 2. Dar SOLO los permisos necesarios (principio de mínimo privilegio)
--    El usuario puede: leer, insertar, actualizar, eliminar
--    El usuario NO puede: crear tablas, eliminar BD, cambiar estructura
GRANT SELECT, INSERT, UPDATE, DELETE
    ON almacen_sistema.*
    TO 'maxBryan'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
    ON almacen_sistema.*
    TO 'maxBryan'@'%';

-- 3. Aplicar cambios
FLUSH PRIVILEGES;

-- ============================================================
-- VERIFICAR: ¿qué puede hacer el usuario?
-- SELECT * FROM information_schema.USER_PRIVILEGES
-- WHERE GRANTEE = "'maxBryan'@'localhost'";
-- ============================================================
