-- ============================================================
-- SEGURIDAD DE BASE DE DATOS — SIGMA ERP
-- Ejecutar en phpMyAdmin como root
-- ============================================================

-- 1. Crear usuario dedicado con contraseña fuerte
--    (solo puede conectarse desde localhost)
CREATE USER IF NOT EXISTS 'sigma_app'@'localhost'
    IDENTIFIED BY 'S1gm@ERP_2026#Secure!';

-- Para Docker (conexión desde contenedor):
CREATE USER IF NOT EXISTS 'sigma_app'@'%'
    IDENTIFIED BY 'S1gm@ERP_2026#Secure!';

-- 2. Dar SOLO los permisos necesarios (principio de mínimo privilegio)
--    El usuario puede: leer, insertar, actualizar, eliminar
--    El usuario NO puede: crear tablas, eliminar BD, cambiar estructura
GRANT SELECT, INSERT, UPDATE, DELETE
    ON almacen_sistema.*
    TO 'sigma_app'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE
    ON almacen_sistema.*
    TO 'sigma_app'@'%';

-- 3. Aplicar cambios
FLUSH PRIVILEGES;

-- ============================================================
-- VERIFICAR: ¿qué puede hacer el usuario?
-- SELECT * FROM information_schema.USER_PRIVILEGES
-- WHERE GRANTEE = "'sigma_app'@'localhost'";
-- ============================================================
