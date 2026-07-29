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
