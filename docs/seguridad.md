# Seguridad de HelpDesk Pro

## 1. Principios

* No confiar en datos enviados por el navegador.
* Validar y autorizar cada operación en el servidor.
* Exponer al usuario mensajes genéricos y registrar internamente los detalles.
* Mantener credenciales y configuración sensible fuera del repositorio.
* Aplicar el menor privilegio posible según el rol.

## 2. Controles ya incorporados

* PDO en modo excepción.
* Consultas preparadas reales, sin emulación.
* `.env` excluido de Git.
* Contraseña administrativa configurada fuera del código.
* Hashes generados mediante `password_hash`.
* Cookies de sesión `HttpOnly` y `SameSite=Lax`.
* Cookie `Secure` cuando el entorno productivo utiliza HTTPS.
* Sesiones configuradas en modo estricto y limitadas al uso de cookies.
* Token CSRF aleatorio asociado a la sesión y comparación mediante
  `hash_equals`.
* Middleware reutilizable para rechazar solicitudes modificadoras sin un token
  CSRF válido.
* Búsqueda de autenticación limitada a usuarios activos.
* Respuesta de credenciales independiente de si el usuario existe o está
  inactivo.
* Verificación ficticia de contraseña para reducir diferencias temporales
  cuando el correo no corresponde a un usuario activo.
* Escape de vistas mediante `View::escape`.
* Directorios internos fuera del acceso web.
* Respuestas de error diferenciadas según `APP_DEBUG`.

## 3. Controles obligatorios para autenticación

La fase de autenticación incorpora:

* Verificación de contraseñas mediante `password_verify`.
* Rechazo de usuarios inactivos.
* Regeneración del ID de sesión después del login.
* Almacenamiento exclusivo de identidad y rol en sesión.
* Renovación del token CSRF después del login.
* Invalidación de datos, cookie y sesión durante logout.
* Protección de rutas con middleware de autenticación e invitados.
* Logout disponible únicamente mediante una solicitud `POST` protegida.

## 4. CSRF

Todo formulario que modifique datos deberá:

* Incluir un token aleatorio asociado a la sesión.
* Validarlo en el servidor mediante comparación segura.
* Rechazar solicitudes sin token o con token inválido.

## 5. Autorización

Ocultar controles en la vista no constituye autorización.

Cada controlador, middleware o servicio deberá confirmar que el usuario puede
operar sobre el recurso solicitado. Los clientes sólo accederán a sus tickets y
las notas internas serán exclusivas del personal autorizado.

## 6. Adjuntos

Antes de habilitar uploads se deberá:

* Limitar tamaño y extensiones.
* Verificar el MIME real mediante `finfo`.
* Generar nombres internos aleatorios.
* Impedir ejecución dentro del directorio de almacenamiento.
* Descargar archivos a través de un controlador autorizado.

## 7. Producción

* `APP_DEBUG` debe estar desactivado.
* Las credenciales deben ser distintas a las del entorno local.
* El servidor debe utilizar HTTPS.
* Los detalles de excepciones deben almacenarse en logs no públicos.
* Los datos demo no deben cargarse.
