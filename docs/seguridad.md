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
* Escape de vistas mediante `View::escape`.
* Directorios internos fuera del acceso web.
* Respuestas de error diferenciadas según `APP_DEBUG`.

## 3. Controles obligatorios para autenticación

* Verificar contraseñas mediante `password_verify`.
* Rechazar usuarios inactivos.
* Regenerar el ID de sesión después del login.
* Guardar en sesión solamente identidad y rol.
* Invalidar la sesión durante logout.
* Proteger rutas con middleware de autenticación e invitados.

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
