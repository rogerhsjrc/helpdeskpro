# Requisitos de HelpDesk Pro

## 1. Objetivo

HelpDesk Pro permitirá registrar, asignar, atender y auditar incidencias entre
clientes y un equipo de soporte.

La primera versión prioriza un flujo completo y demostrable sobre la cantidad
de funcionalidades.

## 2. Actores

### Administrador

* Gestiona usuarios y roles.
* Configura categorías, prioridades y estados.
* Consulta y administra todos los tickets.
* Asigna técnicos.
* Consulta el historial completo.

### Técnico

* Consulta los tickets que puede atender.
* Cambia estado y registra avances.
* Publica comentarios y notas internas.
* Adjunta archivos autorizados.

### Cliente

* Crea tickets.
* Consulta únicamente sus tickets.
* Publica comentarios públicos.
* Adjunta archivos autorizados.

## 3. Requisitos funcionales iniciales

### Autenticación

* Iniciar y cerrar sesión.
* Rechazar credenciales inválidas y usuarios inactivos.
* Proteger rutas privadas.
* Restringir acciones según el rol.

### Tickets

* Crear, listar y consultar tickets.
* Asignar un técnico.
* Cambiar estado y prioridad.
* Buscar, filtrar y paginar.
* Registrar cambios relevantes en el historial.

Reglas acordadas para la Fase 4:

* El cliente crea tickets exclusivamente para sí mismo.
* El cliente sólo lista y consulta sus propios tickets.
* El técnico sólo lista, consulta y opera tickets asignados a su usuario.
* El administrador consulta todos los tickets y realiza asignaciones.
* El contenido original sólo puede editarse por su cliente mientras el ticket
  permanezca abierto y sin asignar, o por un administrador.
* Los cambios de estado y prioridad corresponden al administrador o al técnico
  asignado.
* Categorías, prioridades o estados inactivos permanecen visibles en tickets
  existentes, pero no pueden seleccionarse para nuevas operaciones.
* La creación, edición, asignación y los cambios de estado o prioridad deben
  auditarse desde el comienzo, aunque la línea de tiempo se incorpore después.

La lectura implementada aplica estas reglas también al conteo de paginación y
responde 404 para un detalle inexistente o fuera del ámbito autorizado.

La creación implementada toma siempre al cliente desde la sesión, ofrece sólo
categorías y prioridades activas, inicia el ticket en `ABIERTO` y registra el
evento de auditoría `CREACION` dentro de la misma transacción.

La edición de categoría, asunto y descripción queda disponible para el
administrador y para el cliente propietario sólo mientras el ticket permanezca
`ABIERTO` y sin asignar. Cada cambio efectivo se audita por campo.

La asignación y reasignación corresponde al administrador y acepta únicamente
usuarios activos con rol Técnico. Actualiza la fecha de asignación y registra el
evento correspondiente sin cambiar el estado automáticamente.

Los cambios de estado y prioridad corresponden al administrador o al técnico
asignado. Las transiciones usan códigos estables, exigen técnico en estados
operativos y mantienen fechas de resolución y cierre coherentes.

El listado permite combinar búsqueda por código o asunto con filtros de estado,
prioridad y técnico. Estos criterios nunca amplían el ámbito del rol: el cliente
continúa viendo sólo tickets propios, el técnico sólo sus asignados y el
administrador todos los registros coincidentes.

El dashboard y cada fila del listado ofrecen navegación contextual según el
rol. Ocultar una acción no sustituye las comprobaciones del middleware ni las
reglas de autorización aplicadas al ticket en el backend.

El estado inicial se identifica mediante el código estable `ABIERTO`. Los
efectos de resolución, cierre, cancelación y reapertura utilizan igualmente los
códigos internos, nunca los nombres configurables. El estado `ABIERTO` no puede
desactivarse desde la administración.

### Comunicación

* Agregar comentarios públicos.
* Agregar notas internas visibles sólo para técnicos y administradores.
* Adjuntar y descargar archivos con control de acceso.

### Administración

* Gestionar usuarios.
* Activar y desactivar registros maestros.
* Consultar indicadores básicos en el dashboard.

## 4. Requisitos no funcionales

* Compatibilidad con PHP 8.3 o superior.
* Persistencia mediante MariaDB, InnoDB y `utf8mb4`.
* Consultas variables mediante sentencias preparadas.
* Validación y autorización en el servidor.
* Salida HTML dinámica escapada.
* Operaciones relacionadas protegidas mediante transacciones.
* Suite PHPUnit para comportamientos automatizables.
* Instalación reproducible desde los archivos versionados.

## 5. Fuera del alcance inicial

* SPA o aplicación móvil.
* API REST completa.
* WebSockets.
* Microservicios.
* Notificaciones push.
* Multiempresa.
* Permisos granulares.

El detalle y orden de implementación se mantiene en
[roadmap.md](roadmap.md).
