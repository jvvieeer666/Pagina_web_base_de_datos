# Cambios y pasos para desplegar

## 1. Antes de subir a GitHub
1. Pon el repositorio en **privado** (Settings, al final de la página).
2. Copia tu carpeta `Wallet/` dentro de `Pagina_Base_de_Datos/` (este paquete no la incluye).
   El `Dockerfile` la mueve fuera de la carpeta web al construir la imagen.
3. Si el repositorio fue público alguna vez: en Oracle Cloud usa *Database connection > Rotate wallet*
   y cambia la clave de ADMIN.

## 2. Base de datos
Ejecuta `sql/01_hash_passwords_y_usuario_app.sql` como ADMIN (cambia la clave de APP_TALLER antes).

## 3. Variables de entorno en Render
| Variable | Valor |
|---|---|
| ORACLE_USER | APP_TALLER (o ADMIN mientras pruebas) |
| ORACLE_PASSWORD | la clave de ese usuario |
| ORACLE_SCHEMA | ADMIN (solo si usas APP_TALLER) |
| APP_DEBUG | 1 solo para depurar; quítala después |

`TNS_ADMIN` ya lo fija el Dockerfile.

## 4. Pruebas
- `https://TU-APP.onrender.com/Wallet/cwallet.sso` y `.../Pagina_Base_de_Datos/Wallet/cwallet.sso` deben dar 404 o 403.
- Inicia sesión con cada rol. La primera vez la contraseña antigua funciona y queda guardada como hash.

## Qué cambió
- `conexion.php` único (antes había cuatro copias) con usuario propio, errores solo en el log y ayudantes de consulta.
- `login.php`: RUT normalizado (con o sin puntos), contraseña con `password_verify`, migración automática de claves antiguas,
  límite de 5 intentos por 15 min, `session_regenerate_id`, rol validado.
- `auth.php`: sesión con cookie `httponly`/`secure`/`samesite`, expiración por inactividad, `requerir_rol()` y `h()` para escapar HTML.
- `logout.php` nuevo; las vistas ya tienen "Cerrar sesión".
- Vistas: todo el texto de la base se escapa (sin XSS), `vista_cliente` exige rol cliente y ahora lista los vehículos.
- `Dockerfile`: wallet fuera de la carpeta web, bloqueo de `.sso/.p12/.pem/.jks/.ora` y permisos mínimos.
