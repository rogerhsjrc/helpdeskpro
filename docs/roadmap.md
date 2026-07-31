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
* Autenticación mediante sesiones y credenciales almacenadas de forma segura.
* Login y logout protegidos con CSRF.
* Middleware para rutas de invitados y usuarios autenticados.
* Middleware de autorización por rol con respuesta 403.
* Administración de categorías con alta, edición y estado lógico.
* Administración de prioridades con nivel, color y estado lógico.
* Administración de estados de ticket con orden e indicador final.
* Navegación administrativa central para las tablas maestras.
* Dashboard provisional protegido.

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
* [x] Publicar el proyecto bajo licencia MIT.
* [x] Inicializar el repositorio Git en la rama `main`.
* [x] Vincular el repositorio remoto y publicar `main` en GitHub.

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

* [x] Crear `Usuario` o `UsuarioModel`.
* [x] Buscar usuario por email.
* [x] Crear formulario de login.
* [x] Validar credenciales.
* [x] Utilizar `password_verify`.
* [x] Regenerar el ID de sesión.
* [x] Registrar `ultimo_acceso_at`.
* [x] Crear logout.
* [x] Crear `AuthMiddleware`.
* [x] Crear `GuestMiddleware`.
* [x] Proteger rutas privadas.
* [x] Crear manejo de mensajes flash.
* [x] Incorporar protección CSRF.

### Resultado esperado

El administrador inicial puede iniciar sesión y acceder a un dashboard protegido.

### Cortes de implementación

1. [x] Encapsular sesión, mensajes flash y protección CSRF.
2. [x] Implementar el acceso a usuarios y la validación de credenciales.
3. [x] Incorporar controladores, vistas, rutas y middleware de autenticación.

### Decisiones de alcance

* El login sólo exige que `usuarios.activo = 1`; el estado del rol no bloquea
  la autenticación.
* La opción para mantener la sesión iniciada no forma parte de esta fase.
* El dashboard de esta fase será una página protegida provisional, sin los
  indicadores definidos para la Fase 9.

### Rutas incorporadas

| Método | Ruta | Acceso |
|---|---|---|
| `GET` | `/login` | Invitado |
| `POST` | `/login` | Invitado con token CSRF |
| `GET` | `/dashboard` | Usuario autenticado |
| `POST` | `/logout` | Usuario autenticado con token CSRF |

### Verificación automatizada

* Login válido con regeneración del identificador de sesión y del token CSRF.
* Mensaje genérico para credenciales inválidas.
* Rechazo de usuarios inexistentes o inactivos.
* Actualización de `ultimo_acceso_at` sólo con credenciales válidas.
* Redirecciones para invitados y usuarios autenticados.
* Protección CSRF de login y logout.
* Destrucción completa de una sesión activa durante logout.
* Dashboard provisional accesible únicamente con una identidad válida.
* Suite completa con 67 pruebas y 192 aserciones.

### Verificación manual realizada

* El formulario de login genera un token CSRF válido.
* El administrador inicial puede iniciar sesión con las credenciales del
  entorno.
* El login registra `ultimo_acceso_at` y permite acceder al dashboard
  protegido.
* El logout mediante `POST` y CSRF destruye la sesión y regresa al formulario.
* Después del logout, intentar acceder al dashboard redirige nuevamente al
  login.

---

# Fase 3 — Tablas maestras

## Objetivo

Gestionar las configuraciones básicas utilizadas por los tickets.

### Entidades

* [x] Categorías.
* [x] Prioridades.
* [x] Estados de ticket.

### Tareas

* [x] Confirmar esquema.
* [x] Crear seeds.
* [x] Crear modelos.
* [x] Crear listados.
* [x] Crear formularios.
* [x] Validar nombres únicos.
* [x] Implementar activación y desactivación.
* [x] Restringir acceso a administradores.

### Cortes de implementación

1. [x] Incorporar autorización administrativa mediante `RoleMiddleware` y
   respuesta 403.
2. [x] Implementar la administración completa de categorías como flujo de
   referencia.
3. [x] Implementar la administración de prioridades.
4. [x] Implementar la administración de estados de ticket.
5. [x] Integrar la navegación, completar la documentación y verificar
   manualmente el módulo.

### Verificación del corte 1

* El middleware admite una lista explícita de roles permitidos.
* El administrador puede continuar hacia el recurso protegido.
* Técnicos y clientes reciben una vista 403 sin ejecutar el controlador.
* Los invitados se redirigen al login.
* El pipeline previsto para las rutas administrativas combina autenticación y
  autorización antes del controlador.

### Rutas incorporadas en el corte 2

| Método | Ruta | Responsabilidad |
|---|---|---|
| `GET` | `/admin/categorias` | Listar categorías activas e inactivas |
| `GET` | `/admin/categorias/crear` | Mostrar el formulario de alta |
| `POST` | `/admin/categorias` | Validar y crear una categoría |
| `GET` | `/admin/categorias/{id}/editar` | Mostrar el formulario de edición |
| `POST` | `/admin/categorias/{id}/actualizar` | Validar y actualizar una categoría |
| `POST` | `/admin/categorias/{id}/estado` | Activar o desactivar sin eliminar |

### Decisiones del corte 2

* El esquema y los seeds existentes ya cubren las categorías y no requirieron
  cambios.
* No se elimina físicamente ninguna categoría.
* El nombre se valida como obligatorio, con un máximo de 80 caracteres y
  unicidad respaldada por la base de datos.
* La descripción es opcional y admite hasta 255 caracteres.
* La validación se mantiene en `CategoriaController` y la persistencia en
  `Categoria`; no se incorpora un servicio para una operación de una sola
  entidad.
* Todas las mutaciones usan `POST`, CSRF, autorización administrativa y el
  patrón Post/Redirect/Get después de completarse correctamente.

### Verificación del corte 2

* Listado con categorías activas e inactivas y salida dinámica escapada.
* Alta y edición con normalización, límites de longitud y nombre único.
* Descripción vacía persistida como `NULL`.
* Activación y desactivación lógica sin sentencias de eliminación.
* Respuestas 404 para identificadores inválidos o inexistentes.
* Rechazo de valores de estado manipulados.
* Acceso exclusivo del administrador y protección CSRF en todas las mutaciones.
* Suite completa con 98 pruebas y 291 aserciones.
* Lectura manual de los cinco registros existentes y renderizado del listado
  con estado HTTP 200, sin modificar la base de desarrollo.

### Rutas incorporadas en el corte 3

| Método | Ruta | Responsabilidad |
|---|---|---|
| `GET` | `/admin/prioridades` | Listar prioridades ordenadas por nivel |
| `GET` | `/admin/prioridades/crear` | Mostrar el formulario de alta |
| `POST` | `/admin/prioridades` | Validar y crear una prioridad |
| `GET` | `/admin/prioridades/{id}/editar` | Mostrar el formulario de edición |
| `POST` | `/admin/prioridades/{id}/actualizar` | Validar y actualizar una prioridad |
| `POST` | `/admin/prioridades/{id}/estado` | Activar o desactivar sin eliminar |

### Decisiones del corte 3

* El nivel es obligatorio, único y debe ser un entero entre 1 y 255, de acuerdo
  con el tipo `TINYINT UNSIGNED` del esquema.
* El color es opcional; cuando existe debe usar el formato hexadecimal
  `#RRGGBB` y se normaliza a minúsculas.
* El nombre es obligatorio, único y admite hasta 50 caracteres.
* La descripción es opcional y admite hasta 255 caracteres.
* El listado utiliza el nivel ascendente para representar el orden de impacto.
* No se elimina físicamente ninguna prioridad.
* Se mantienen un modelo y un controlador específicos porque las reglas de
  nivel y color no pertenecen a categorías.

### Verificación del corte 3

* Validación previa a la persistencia de nombre, nivel, descripción y color.
* Comprobaciones independientes de nombre y nivel únicos durante alta y edición.
* Sentencias preparadas para listado, búsqueda y todas las mutaciones.
* Salida dinámica escapada y formularios con errores accesibles.
* Acceso exclusivo del administrador y CSRF obligatorio en cada mutación.
* Suite completa con 116 pruebas y 366 aserciones.

### Rutas incorporadas en el corte 4

| Método | Ruta | Responsabilidad |
|---|---|---|
| `GET` | `/admin/estados-ticket` | Listar estados según su orden |
| `GET` | `/admin/estados-ticket/crear` | Mostrar el formulario de alta |
| `POST` | `/admin/estados-ticket` | Validar y crear un estado |
| `GET` | `/admin/estados-ticket/{id}/editar` | Mostrar el formulario de edición |
| `POST` | `/admin/estados-ticket/{id}/actualizar` | Validar y actualizar un estado |
| `POST` | `/admin/estados-ticket/{id}/estado` | Activar o desactivar sin eliminar |

### Decisiones del corte 4

* El orden es obligatorio, único y debe ser un entero entre 1 y 255.
* `es_final` es un indicador descriptivo y no ejecuta transiciones, cierres ni
  cambios de fechas por sí mismo.
* El nombre es obligatorio, único y admite hasta 60 caracteres.
* La descripción es opcional y admite hasta 255 caracteres.
* El listado utiliza el orden ascendente configurado.
* No se elimina físicamente ningún estado.
* La protección del estado inicial y las transiciones permitidas se definirán
  junto con el flujo real de tickets.

### Verificación del corte 4

* Validación previa de nombre, descripción, orden e indicador final.
* Rechazo de valores manipulados para `es_final`.
* Comprobaciones independientes de nombre y orden únicos.
* Acceso administrativo y CSRF en todas las mutaciones.
* Suite completa con 134 pruebas y 442 aserciones.
* Conexión real de solo lectura verificada contra MariaDB: 5 categorías, 4
  prioridades y 7 estados; listado de estados renderizado con HTTP 200.

### Cierre del corte 5

* Se incorporó `/admin/configuraciones` como acceso central a categorías,
  prioridades y estados de ticket.
* El dashboard muestra un único acceso de configuraciones al administrador y
  no lo presenta a técnicos o clientes.
* Los listados permiten regresar al centro administrativo sin pasar por el
  dashboard.
* La ruta central y cada catálogo conservan autorización independiente mediante
  `AuthMiddleware` y `RoleMiddleware`.
* Las pruebas integradas utilizan las rutas web reales y ya no dependen de una
  ruta administrativa simulada.
* El usuario confirmó manualmente los flujos de alta, edición y cambio de estado
  de los tres catálogos.
* La conexión real a MariaDB confirmó los datos maestros esperados.
* Suite completa con 135 pruebas y 448 aserciones.

### Resultado de la Fase 3

La Fase 3 queda completada. El administrador puede gestionar las tablas
maestras desde una navegación común, mientras que invitados, técnicos y
clientes no pueden acceder a sus rutas.

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
* Opción para mantener la sesión iniciada (`recordarme`).
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
Definir reglas y permisos del ticket
    ↓
Implementar el modelo de acceso a tickets
    ↓
Crear el alta de tickets
    ↓
Incorporar listado y detalle
```

El próximo corte de desarrollo debe ser pequeño y verificable.
