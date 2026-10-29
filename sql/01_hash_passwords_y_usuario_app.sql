-- Ejecutar conectado como ADMIN (SQL Developer Web / Database Actions).
-- Orden: 1) ampliar columnas, 2) crear el usuario de la aplicación.

------------------------------------------------------------
-- 1) Las contraseñas pasarán a guardarse como hash (bcrypt = 60 caracteres)
------------------------------------------------------------
ALTER TABLE ADMINISTRADOR MODIFY ("PASSWORD" VARCHAR2(255));
ALTER TABLE MECANICO      MODIFY ("PASSWORD" VARCHAR2(255));
ALTER TABLE CLIENTE       MODIFY ("PASSWORD" VARCHAR2(255));

-- No hace falta convertir las contraseñas actuales a mano:
-- login.php acepta la contraseña antigua UNA vez y la reemplaza por su hash.

------------------------------------------------------------
-- 2) Usuario propio de la aplicación (sin privilegios de administrador)
--    CAMBIA la clave: 12-30 caracteres, con mayúscula, minúscula y número.
------------------------------------------------------------
CREATE USER APP_TALLER IDENTIFIED BY "CambiaEstaClave2026Taller";
GRANT CREATE SESSION TO APP_TALLER;

GRANT SELECT ON ADMIN.ADMINISTRADOR TO APP_TALLER;
GRANT SELECT ON ADMIN.MECANICO      TO APP_TALLER;
GRANT SELECT ON ADMIN.CLIENTE       TO APP_TALLER;
GRANT SELECT ON ADMIN.VEHICULO      TO APP_TALLER;
GRANT SELECT ON ADMIN.ORDEN         TO APP_TALLER;
GRANT SELECT ON ADMIN.REPUESTO      TO APP_TALLER;

-- Solo puede modificar la columna de contraseña (para guardar el hash)
GRANT UPDATE ("PASSWORD") ON ADMIN.ADMINISTRADOR TO APP_TALLER;
GRANT UPDATE ("PASSWORD") ON ADMIN.MECANICO      TO APP_TALLER;
GRANT UPDATE ("PASSWORD") ON ADMIN.CLIENTE       TO APP_TALLER;

-- Cuando agregues pantallas para crear/editar órdenes, repuestos, etc.,
-- otorga solo lo necesario, por ejemplo:
--   GRANT INSERT, UPDATE ON ADMIN.ORDEN TO APP_TALLER;
