# Roadmap de HelpDesk Pro

## Estado actual

Completado:

* Proyecto creado en Laragon.
* Base de datos `helpdesk_pro`.
* Composer configurado.
* Autoload PSR-4 configurado.
* Clase `App\Core\Database`.
* Conexión PDO funcionando.
* Tablas iniciales de usuarios y roles.
* Seed del administrador.
* Usuario administrador inicial creado.
* Variables de entorno y exclusiones de Git configuradas.
* Núcleo HTTP con `Request`, `Response` y `Router`.
* Pipeline de middleware disponible.
* Controlador base y renderizado de vistas con layout.
* Front Controller y reescritura de URLs configurados.
* Página inicial, respuesta 404 y respuesta 405.
* Suite PHPUnit para el núcleo HTTP y las rutas web.

---

# Hito 0 — Baseline reproducible

## Objetivo

Permitir que el proyecto pueda versionarse, instalarse y verificarse desde una
base limpia antes de continuar con funcionalidades de negocio.

### Tareas

* [x] Configurar Composer y autoload PSR-4.
* [x] Configurar `.env.example` y `.gitignore`.
* [x] Versionar `database/schema.sql`.
* [x] Separar estructura, datos maestros, administrador y datos demo.
* [x] Crear seeds idempotentes.
* [x] Verificar la instalación contra una base aislada.
* [x] Crear documentación técnica inicial.
* [x] Completar un README de instalación.
* [x] Inicializar el repositorio Git en la rama `main`.

### Resultado esperado

Una persona puede instalar dependencias, crear una base vacía, aplicar el
esquema, ejecutar los seeds y comprobar el proyecto utilizando únicamente los
archivos versionados.

---

# Fase 1 — Base del proyecto

## Objetivo

Construir el núcleo mínimo para recibir peticiones y generar respuestas.

### Tareas

* [x] Crear `.env`.
* [x] Crear `.env.example`.
* [x] Cargar variables de entorno.
* [x] Configurar `.gitignore`.
* [x] Crear `Request`.
* [x] Crear `Response`.
* [x] Crear `Router`.
* [x] Crear clase base `Controller`.
* [x] Crear renderizado de vistas.
* [x] Crear página 404.
* [x] Configurar `.htaccess`.
* [x] Confirmar que todas las peticiones ingresan por `public/index.php`.

### Resultado esperado

La aplicación puede registrar rutas como:

```php
$router->get('/', [HomeController::class, 'index']);
```

y mostrar una vista mediante el controlador.

### Verificación realizada

* Sintaxis válida en todos los archivos PHP.
* Suite PHPUnit ejecutable mediante `composer test`.
* 33 pruebas y 87 aserciones para los componentes de la Fase 1.
* `GET /` responde con estado 200 y renderiza una vista.
* Una ruta inexistente responde con estado 404.
* Un método no permitido responde con estado 405 y el header `Allow`.
* Apache/Laragon delega las peticiones en `public/index.php`.
* Los archivos ubicados fuera de `public/` no son servidos directamente.

---

# Fase 2 — Autenticación

## Objetivo

Permitir el acceso seguro de usuarios registrados.

### Tareas

* [ ] Crear `Usuario` o `UsuarioModel`.
* [ ] Buscar usuario por email.
* [ ] Crear formulario de login.
* [ ] Validar credenciales.
* [ ] Utilizar `password_verify`.
* [ ] Regenerar el ID de sesión.
* [ ] Registrar `ultimo_acceso_at`.
* [ ] Crear logout.
* [ ] Crear `AuthMiddleware`.
* [ ] Crear `GuestMiddleware`.
* [ ] Proteger rutas privadas.
* [ ] Crear manejo de mensajes flash.
* [ ] Incorporar protección CSRF.

### Resultado esperado

El administrador inicial puede iniciar sesión y acceder a un dashboard protegido.

---

# Fase 3 — Tablas maestras

## Objetivo

Gestionar las configuraciones básicas utilizadas por los tickets.

### Entidades

* [ ] Categorías.
* [ ] Prioridades.
* [ ] Estados de ticket.

### Tareas

* [ ] Confirmar esquema.
* [ ] Crear seeds.
* [ ] Crear modelos.
* [ ] Crear listados.
* [ ] Crear formularios.
* [ ] Validar nombres únicos.
* [ ] Implementar activación y desactivación.
* [ ] Restringir acceso a administradores.

### Resultado esperado

El administrador puede configurar las opciones utilizadas al crear un ticket.

---

# Fase 4 — Tickets

## Objetivo

Implementar el flujo principal del sistema.

### Tareas

* [ ] Crear tabla `tickets`.
* [ ] Crear modelo.
* [ ] Crear servicio si la lógica lo justifica.
* [ ] Crear ticket.
* [ ] Listar tickets.
* [ ] Ver detalle.
* [ ] Editar datos permitidos.
* [ ] Asignar técnico.
* [ ] Cambiar estado.
* [ ] Cambiar prioridad.
* [ ] Filtrar por estado.
* [ ] Filtrar por prioridad.
* [ ] Filtrar por técnico.
* [ ] Buscar por código o asunto.
* [ ] Implementar paginación.
* [ ] Verificar permisos según rol.

### Reglas iniciales

* El cliente solamente puede ver sus tickets.
* El técnico solamente puede operar sobre tickets permitidos.
* El administrador puede ver todos los tickets.
* Todo cambio relevante debe auditarse.

### Resultado esperado

Existe un flujo completo desde la creación hasta la resolución de un ticket.

---

# Fase 5 — Comentarios y notas internas

## Objetivo

Permitir la comunicación dentro del ticket.

### Tareas

* [ ] Crear tabla `ticket_comentarios`.
* [ ] Crear comentarios públicos.
* [ ] Crear notas internas.
* [ ] Restringir notas internas a técnicos y administradores.
* [ ] Mostrar autor y fecha.
* [ ] Validar contenido.
* [ ] Evitar comentarios vacíos.
* [ ] Escapar la salida HTML.

### Resultado esperado

Clientes y técnicos pueden comunicarse, y el equipo interno puede registrar notas privadas.

---

# Fase 6 — Adjuntos

## Objetivo

Permitir agregar archivos de forma segura.

### Tareas

* [ ] Crear tabla `ticket_adjuntos`.
* [ ] Definir extensiones permitidas.
* [ ] Definir tamaño máximo.
* [ ] Validar MIME real.
* [ ] Generar nombre interno aleatorio.
* [ ] Evitar ejecución de archivos subidos.
* [ ] Asociar archivos con tickets o comentarios.
* [ ] Controlar permisos de descarga.
* [ ] Eliminar o invalidar archivos cuando corresponda.

### Resultado esperado

Un usuario autorizado puede subir y consultar archivos vinculados a un ticket.

---

# Fase 7 — Historial y auditoría

## Objetivo

Registrar cambios relevantes del ciclo de vida de los tickets.

### Tareas

* [ ] Crear tabla `ticket_historial`.
* [ ] Registrar creación.
* [ ] Registrar asignaciones.
* [ ] Registrar cambios de estado.
* [ ] Registrar cambios de prioridad.
* [ ] Registrar resolución.
* [ ] Registrar cierre.
* [ ] Registrar reapertura.
* [ ] Mostrar línea de tiempo.

### Resultado esperado

El detalle del ticket muestra una trazabilidad clara de sus cambios.

---

# Fase 8 — Gestión de usuarios

## Objetivo

Permitir que el administrador gestione usuarios.

### Tareas

* [ ] Listar usuarios.
* [ ] Crear usuarios.
* [ ] Editar usuarios.
* [ ] Asignar rol.
* [ ] Activar y desactivar.
* [ ] Restablecer contraseña de forma administrativa.
* [ ] Impedir desactivar accidentalmente al único administrador.
* [ ] Filtrar por rol y estado.

### Resultado esperado

El sistema puede ser administrado sin insertar usuarios manualmente en la base de datos.

---

# Fase 9 — Dashboard

## Objetivo

Mostrar información resumida y útil.

### Indicadores iniciales

* [ ] Tickets abiertos.
* [ ] Tickets en proceso.
* [ ] Tickets resueltos.
* [ ] Tickets cerrados.
* [ ] Tickets urgentes.
* [ ] Tickets sin asignar.
* [ ] Tickets por categoría.
* [ ] Tickets por técnico.

No incorporar gráficos complejos hasta completar los indicadores básicos.

### Resultado esperado

Cada rol visualiza un resumen relevante de su actividad.

---

# Fase 10 — Calidad y documentación

## Objetivo

Preparar el proyecto para GitHub y entrevistas.

### Tareas

* [ ] Completar `README.md`.
* [ ] Documentar instalación.
* [ ] Documentar variables de entorno.
* [ ] Incluir script de base de datos.
* [ ] Incluir seeds.
* [ ] Incorporar capturas.
* [ ] Crear diagrama entidad-relación.
* [ ] Documentar decisiones técnicas.
* [ ] Documentar medidas de seguridad.
* [ ] Revisar nombres y convenciones.
* [ ] Eliminar código temporal.
* [ ] Revisar mensajes de error.
* [ ] Preparar datos demostrativos.
* [ ] Verificar instalación desde cero.
* [ ] Agregar pruebas principales.
* [ ] Crear una versión o release estable.

---

# Futuras versiones

Estas funcionalidades podrán evaluarse después de terminar la primera versión:

* API REST.
* Cliente React.
* Versión Laravel.
* Versión Symfony.
* Recuperación de contraseña mediante correo.
* Notificaciones por correo.
* Acuerdos de nivel de servicio.
* Etiquetas.
* Respuestas rápidas.
* Exportación de reportes.
* Docker.
* Sistema de permisos granular.
* Tests de integración más amplios.

No deben implementarse antes de completar el alcance principal.

---

# Regla de avance

Cada fase debe completarse y probarse antes de avanzar a la siguiente.

Una fase puede dividirse en tareas menores, pero no se deben mantener demasiados módulos incompletos en paralelo.

El orden recomendado inmediato es:

```text
Variables de entorno
    ↓
Request
    ↓
Response
    ↓
Router
    ↓
Controlador y vistas
    ↓
Login
```

El próximo corte de desarrollo debe ser pequeño y verificable.
