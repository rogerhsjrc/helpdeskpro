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
* Contrato inicial de tickets y códigos estables para sus estados.
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

* [x] Confirmar la tabla `tickets` incluida en el esquema reproducible.
* [x] Incorporar códigos estables para los estados del sistema.
* [x] Crear modelo.
* [x] Crear servicio para mutaciones transaccionales y auditoría.
* [x] Crear ticket.
* [x] Listar tickets.
* [x] Ver detalle.
* [x] Editar datos permitidos.
* [x] Asignar técnico.
* [x] Cambiar estado.
* [x] Cambiar prioridad.
* [x] Filtrar por estado.
* [x] Filtrar por prioridad.
* [x] Filtrar por técnico.
* [x] Buscar por código o asunto.
* [x] Implementar paginación.
* [x] Verificar permisos de lectura según rol y recurso.

### Reglas iniciales

* El cliente solamente puede ver sus tickets.
* El técnico solamente puede operar sobre tickets permitidos.
* El administrador puede ver todos los tickets.
* Todo cambio relevante debe auditarse.

### Cortes de implementación

1. [x] Definir contrato, permisos, código público del ticket y códigos estables
   de estado.
2. [x] Implementar lectura, listado, detalle, paginación y autorización por
   recurso.
3. [x] Implementar la creación de tickets propios para clientes.
4. [x] Implementar la edición limitada del contenido original.
5. [x] Implementar asignación y reasignación técnica.
6. [x] Implementar cambios de estado y prioridad con efectos y auditoría.
7. [x] Incorporar filtros y búsqueda por código o asunto.
8. [x] Integrar navegación, documentación y pruebas manuales con los tres roles.

### Decisiones del corte 1

* `estados_ticket.codigo` es único, obligatorio e inmutable desde la interfaz.
* El estado inicial se resuelve por `ABIERTO`, no por nombre ni identificador
  numérico.
* Los códigos del sistema se exponen como constantes de `EstadoTicket`.
* El estado `ABIERTO` no puede desactivarse porque es obligatorio para crear
  tickets.
* Los nuevos estados administrativos requieren un código de 3 a 40 caracteres,
  normalizado a mayúsculas y compuesto por letras, números o guiones bajos.
* El código público del ticket utilizará `HD-YYYYMMDD-XXXXXX` con aleatoriedad
  criptográficamente segura y reintentos acotados ante una colisión.
* `RoleMiddleware` controla roles admitidos y la capa de negocio comprobará
  propiedad o asignación sobre cada ticket.
* La auditoría comenzará con las mutaciones de la Fase 4 aunque su línea de
  tiempo visual pertenezca a la Fase 7.

### Verificación del corte 1

* El esquema reproducible declara `estados_ticket.codigo` como obligatorio y
  único.
* La actualización para instalaciones existentes se ejecutó repetidamente sin
  duplicar columna, índice ni registros.
* Los siete estados reales recibieron el código esperado y `ABIERTO` permanece
  activo.
* El seed principal se ejecutó dos veces después de la actualización sin crear
  duplicados.
* El alta administrativa valida y normaliza nuevos códigos; la edición los
  presenta como inmutables e ignora valores manipulados.
* La administración impide desactivar el estado inicial `ABIERTO`.
* El listado real de estados renderiza correctamente después de la actualización.
* Suite completa con 139 pruebas y 471 aserciones.

### Rutas incorporadas en el corte 2

| Método | Ruta | Responsabilidad |
|---|---|---|
| `GET` | `/tickets` | Listar y paginar los tickets visibles para la identidad |
| `GET` | `/tickets/{codigo}` | Mostrar un ticket visible por su código público o responder 404 |

### Decisiones del corte 2

* Las rutas admiten únicamente los roles Administrador, Técnico y Cliente
  mediante `RoleMiddleware`.
* `Ticket` agrega a cada consulta la condición de visibilidad correspondiente:
  todos los tickets para el administrador, propios para el cliente y asignados
  para el técnico.
* Un ticket inexistente y uno fuera del ámbito autorizado producen la misma
  respuesta 404, sin revelar la existencia de recursos ajenos.
* El detalle utiliza el código público en la URL; el identificador numérico se
  conserva exclusivamente como clave interna.
* El listado se ordena por fecha de creación e identificador descendentes y
  utiliza páginas de 10 registros.
* Una página inválida se normaliza a la primera; una página superior a la
  última se ajusta a la última disponible.
* Las categorías, prioridades y estados inactivos continúan visibles en
  tickets existentes y se identifican como tales en las vistas.
* La lectura afecta una única entidad principal y no requiere todavía un
  servicio; el controlador coordina el caso y el modelo encapsula SQL y
  autorización por recurso.

### Verificación del corte 2

* Consultas preparadas con restricciones de propiedad o asignación aplicadas
  tanto al conteo/listado como al detalle.
* Listado y detalle con contenido dinámico escapado.
* Respuesta 404 para identificadores inválidos, inexistentes o no visibles.
* Rutas protegidas contra invitados y roles ajenos al flujo de tickets.
* Pruebas aisladas para modelo, controlador y pipeline de rutas.
* Suite completa con 151 pruebas y 517 aserciones.
* Conexión real de solo lectura verificada para listado y detalle por código
  público.

### Rutas incorporadas en el corte 3

| Método | Ruta | Responsabilidad |
|---|---|---|
| `GET` | `/tickets/crear` | Mostrar al cliente el formulario con catálogos activos |
| `POST` | `/tickets` | Validar, crear y auditar un ticket propio |

### Decisiones del corte 3

* El alta es exclusiva del rol Cliente mediante `AuthMiddleware` y
  `RoleMiddleware`; el cliente autenticado se toma de la sesión y no de un
  campo manipulable del formulario.
* El asunto es obligatorio y admite hasta 180 caracteres. La descripción es
  obligatoria y se limita a 5000 caracteres para esta primera versión.
* El formulario lista únicamente categorías y prioridades activas. El servicio
  vuelve a comprobar su disponibilidad antes de insertar.
* El estado inicial se resuelve exclusivamente mediante el código estable
  `ABIERTO` y también debe permanecer activo.
* `TicketService` comparte una conexión entre los modelos y mantiene creación
  y auditoría dentro de una única transacción.
* El código público sigue el formato `HD-YYYYMMDD-XXXXXX`; el sufijo contiene
  seis caracteres hexadecimales generados con `random_bytes`.
* Una colisión de `uq_tickets_codigo` se reintenta hasta cinco veces. Otros
  errores de persistencia no se ocultan ni se convierten en reintentos.
* La creación registra un evento `CREACION` en `ticket_historial` antes de
  confirmar la transacción.
* Tras el alta se aplica Post/Redirect/Get hacia el detalle identificado por el
  código público.

### Verificación del corte 3

* Validación del lado servidor para estructuras manipuladas, identificadores,
  campos obligatorios y límites de longitud.
* Restricción del formulario y del `POST` al rol Cliente, con CSRF obligatorio.
* Consultas preparadas para catálogos, ticket e historial.
* Confirmación conjunta del ticket y su evento inicial.
* Rollback cuando un catálogo o el estado inicial no están disponibles.
* Reintento controlado ante una colisión de la restricción única.
* Renderizado escapado y conservación de valores después de errores 422.
* Suite completa con 168 pruebas y 581 aserciones.
* Formulario real renderizado con HTTP 200 y catálogos obtenidos desde MariaDB
  en modo de solo lectura, sin insertar datos de prueba.

### Rutas incorporadas en el corte 4

| Método | Ruta | Responsabilidad |
|---|---|---|
| `GET` | `/tickets/{codigo}/editar` | Mostrar el contenido original editable |
| `POST` | `/tickets/{codigo}/actualizar` | Revalidar, actualizar y auditar cambios |

### Decisiones del corte 4

* `RoleMiddleware` admite Administrador y Cliente; el técnico no participa en
  la edición del contenido original.
* El administrador puede editar cualquier ticket. El cliente sólo puede editar
  uno propio mientras permanezca `ABIERTO` y sin técnico asignado.
* Los campos de este corte son categoría, asunto y descripción. Prioridad,
  estado y asignación conservan sus acciones específicas en cortes posteriores.
* El `POST` vuelve a cargar y bloquear el ticket mediante `FOR UPDATE` antes de
  evaluar estado, asignación y propiedad, evitando decisiones sobre datos
  concurrentemente obsoletos.
* Sólo puede elegirse una categoría activa. Una categoría histórica inactiva
  puede conservarse al editar otros campos, pero no seleccionarse como cambio.
* Cada campo efectivamente modificado registra un evento `EDICION` con valor
  anterior y nuevo. Si no existen cambios, no se actualiza ni audita.
* Actualización y eventos se confirman dentro de una única transacción.

### Verificación del corte 4

* Formulario y mutación restringidos por rol, recurso, estado y asignación.
* CSRF obligatorio en la actualización.
* Validación de identificadores, campos obligatorios y límites de longitud.
* Consultas preparadas para bloqueo, actualización y auditoría.
* Pruebas de cliente editable, cliente asignado, administrador y técnico.
* Auditoría limitada a los campos con cambios efectivos.
* Suite completa con 178 pruebas y 608 aserciones.
* Formulario administrativo renderizado contra MariaDB con HTTP 200 usando un
  ticket existente, sin modificar datos de desarrollo.

### Rutas incorporadas en el corte 5

| Método | Ruta | Responsabilidad |
|---|---|---|
| `GET` | `/tickets/{codigo}/asignar` | Mostrar técnico actual y candidatos activos |
| `POST` | `/tickets/{codigo}/asignacion` | Asignar o reasignar y auditar |

### Decisiones del corte 5

* La asignación es exclusiva del administrador mediante `RoleMiddleware` y se
  vuelve a comprobar dentro de `TicketService`.
* Sólo pueden seleccionarse usuarios activos cuyo rol actual sea `Técnico`.
* La mutación bloquea el ticket con `FOR UPDATE`, actualiza `tecnico_id` y
  reemplaza `fecha_asignacion_at` con el momento de la asignación vigente.
* La primera asignación registra `ASIGNACION`; una reasignación registra
  `CAMBIO_TECNICO`, conservando los nombres anterior y nuevo.
* Seleccionar al técnico ya asignado no escribe ni genera historial.
* La asignación no cambia automáticamente el estado; las transiciones y sus
  efectos pertenecen al corte 6.
* Ticket y evento se confirman dentro de una misma transacción.

### Verificación del corte 5

* Listado y búsqueda parametrizada de técnicos activos.
* Acceso exclusivo del administrador y CSRF obligatorio en la mutación.
* Rechazo de clientes y de candidatos inactivos o con otro rol.
* Diferenciación entre primera asignación y reasignación en auditoría.
* Actualización de fecha sin modificación implícita del estado.
* Suite completa con 189 pruebas y 639 aserciones.
* Formulario real de asignación renderizado contra MariaDB con HTTP 200, sin
  modificar datos de desarrollo.

### Rutas incorporadas en el corte 6

| Método | Ruta | Responsabilidad |
|---|---|---|
| `GET` | `/tickets/{codigo}/gestionar` | Mostrar estados y prioridades disponibles |
| `POST` | `/tickets/{codigo}/flujo` | Validar, actualizar efectos y auditar |

### Decisiones del corte 6

* El administrador gestiona cualquier ticket y el técnico únicamente uno
  asignado a su usuario. `RoleMiddleware` realiza el control grueso y el
  servicio revalida el recurso.
* Sólo pueden seleccionarse estados o prioridades activos; el valor histórico
  inactivo puede conservarse si no se cambia.
* Matriz inicial: `ABIERTO → ASIGNADO|CANCELADO`, `ASIGNADO → EN_PROCESO|CANCELADO`,
  `EN_PROCESO → PENDIENTE_CLIENTE|RESUELTO|CANCELADO`,
  `PENDIENTE_CLIENTE → EN_PROCESO|RESUELTO|CANCELADO`,
  `RESUELTO → CERRADO|EN_PROCESO`, `CERRADO → EN_PROCESO` y
  `CANCELADO → ABIERTO`.
* `ASIGNADO`, `EN_PROCESO`, `PENDIENTE_CLIENTE` y `RESUELTO` requieren técnico.
* Entrar en `RESUELTO` completa `fecha_resolucion_at`; entrar en `CERRADO`
  conserva la resolución y completa `fecha_cierre_at`; reabrir limpia ambas.
* Estado y prioridad generan respectivamente `CAMBIO_ESTADO` y
  `CAMBIO_PRIORIDAD`, con valores anterior y nuevo.
* No se escriben filas ni eventos cuando ambos valores permanecen iguales.
* La fila se bloquea con `FOR UPDATE` y todos los efectos se confirman en una
  transacción.

### Verificación del corte 6

* Autorización para administrador y técnico asignado; rechazo del cliente.
* CSRF obligatorio y validación de identificadores manipulados.
* Pruebas de transición válida e inválida, efectos temporales y auditoría.
* Cambio de prioridad independiente del estado.
* Suite completa con 198 pruebas y 666 aserciones.
* La verificación manual contra MariaDB se completó al cerrar la fase: el
  formulario de flujo respondió HTTP 200 para Administrador y Técnico asignado.

### Decisiones del corte 7

* Estado, prioridad, técnico y texto pueden combinarse en una misma consulta.
* Los filtros se agregan después de la condición de visibilidad del rol, tanto
  en el conteo como en el listado paginado.
* La búsqueda por código o asunto utiliza `LOCATE` con parámetros separados;
  `%` y `_` se interpretan como texto literal y no como comodines SQL.
* El formulario admite estados y prioridades históricos inactivos para poder
  localizar tickets existentes, pero sólo lista técnicos actualmente activos.
* Los enlaces anterior y siguiente conservan todos los filtros mediante query
  string codificada.

### Verificación del corte 7

* Pruebas del modelo para búsqueda literal y combinación de filtros.
* Pruebas del controlador para normalización, selección y paginación persistente.
* Suite completa con 200 pruebas y 678 aserciones.

### Decisiones del corte 8

* El dashboard ofrece accesos con lenguaje contextual: gestión completa para
  Administrador, asignados para Técnico y tickets propios/alta para Cliente.
* El listado incorpora acciones directas por ticket para editar, asignar o
  gestionar el flujo únicamente cuando corresponden a la identidad mostrada.
* La visibilidad de enlaces mejora la navegación, pero no reemplaza la
  autorización de `RoleMiddleware` ni la validación por recurso del servicio.
* Los formularios conservan el retorno al detalle y el detalle vuelve al
  listado, completando un recorrido navegable sin URLs manuales.

### Verificación del corte 8

* Navegación automatizada para Administrador, Técnico y Cliente.
* Recorrido real de sólo lectura contra MariaDB por listado, detalle, creación,
  asignación y gestión de flujo.
* Las rutas permitidas respondieron HTTP 200; asignación para Técnico y gestión
  para Cliente respondieron HTTP 403 mediante el pipeline real.
* No se ejecutaron mutaciones ni se modificaron datos durante la prueba manual.
* Suite completa con 202 pruebas y 695 aserciones.

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
