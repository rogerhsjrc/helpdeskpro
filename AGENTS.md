# AGENTS.md — HelpDesk Pro

## 1. Propósito del proyecto

HelpDesk Pro es un sistema de gestión de incidencias desarrollado como proyecto de portafolio.

El objetivo principal no es construir el sistema más grande posible, sino demostrar conocimientos sólidos de:

* PHP 8.3 o superior.
* Programación orientada a objetos.
* Arquitectura MVC.
* Enrutamiento HTTP.
* Autenticación y autorización.
* PDO y consultas preparadas.
* Modelado de bases de datos relacionales.
* Seguridad web básica.
* Separación de responsabilidades.
* Documentación técnica.
* Buenas prácticas de desarrollo sin depender de un framework.

El proyecto debe mantenerse suficientemente pequeño como para poder finalizarse, documentarse y mostrarse en entrevistas técnicas.

---

## 2. Stack tecnológico

* PHP 8.3 o superior.
* La versión mínima declarada por Composer es PHP 8.3.
* MariaDB.
* Apache mediante Laragon.
* Composer.
* Autoload PSR-4.
* PDO.
* HTML5.
* CSS3.
* JavaScript vanilla.
* Git y GitHub.

No se utilizarán frameworks PHP como Laravel, Symfony o CodeIgniter.

No se utilizará un framework frontend durante la primera versión.

Se pueden incorporar dependencias pequeñas mediante Composer cuando exista una justificación técnica clara. Toda dependencia nueva debe ser consultada antes de incorporarse.

---

## 3. Objetivo arquitectónico

La aplicación debe utilizar una arquitectura MVC sencilla y educativa.

El proyecto no debe intentar replicar completamente Laravel, Symfony u otro framework.

Se implementarán solamente los componentes necesarios para comprender y ejecutar el flujo principal de una aplicación web:

```text
Request
    ↓
public/index.php
    ↓
Router
    ↓
Middleware
    ↓
Controller
    ↓
Service o Model
    ↓
Database
    ↓
View o Response
```

La arquitectura debe mantenerse simple, explícita y fácil de explicar durante una entrevista.

---

## 4. Estructura general

```text
HelpDeskPro/
├── app/
│   ├── Config/
│   ├── Controllers/
│   ├── Core/
│   ├── Helpers/
│   ├── Middleware/
│   ├── Models/
│   ├── Services/
│   └── Views/
│
├── database/
│   ├── schema.sql
│   ├── seeds.sql
│   └── Seeds/
│
├── docs/
│   ├── arquitectura.md
│   ├── base-de-datos.md
│   ├── decisiones-tecnicas.md
│   ├── requisitos.md
│   ├── seguridad.md
│   └── roadmap.md
│
├── public/
│   ├── assets/
│   │   ├── css/
│   │   ├── img/
│   │   └── js/
│   ├── uploads/
│   ├── .htaccess
│   └── index.php
│
├── routes/
│   └── web.php
│
├── scripts/
│   └── seed.php
│
├── storage/
│   └── logs/
│
├── tests/
│
├── vendor/
├── .env
├── .env.example
├── .gitignore
├── AGENTS.md
├── composer.json
└── README.md
```

No deben crearse carpetas nuevas sin una necesidad concreta.

---

## 5. Convenciones de código

### PHP

Todo archivo PHP nuevo debe comenzar con:

```php
<?php

declare(strict_types=1);
```

Se deben utilizar:

* Namespaces.
* Tipos de parámetros.
* Tipos de retorno.
* Propiedades tipadas.
* Clases `final` cuando no esté previsto que sean extendidas.
* Excepciones para errores inesperados.
* Nombres claros y descriptivos.

### Documentación de métodos

Todos los métodos deben incluir un bloque PHPDoc inmediatamente antes de su
declaración, incluidos:

* Constructores.
* Métodos públicos.
* Métodos protegidos.
* Métodos privados.
* Métodos de clases de prueba.

El comentario debe explicar de forma breve la finalidad del método y su papel
dentro del flujo. Cuando corresponda, debe documentar:

* Parámetros mediante `@param`.
* Valor de retorno mediante `@return`.
* Excepciones esperadas mediante `@throws`.
* Estructura de arrays mediante tipos PHPDoc precisos.
* Reglas de negocio o decisiones que no resulten evidentes al leer el código.

Los comentarios deben aportar intención y contexto. No deben limitarse a
traducir literalmente cada instrucción ni utilizarse para justificar código
confuso. Si un método necesita una explicación excesiva, primero debe evaluarse
si puede simplificarse.

Ejemplo:

```php
/**
 * Busca un usuario activo por su dirección de correo para iniciar sesión.
 *
 * @return array<string, mixed>|null
 */
public function findActiveByEmail(string $email): ?array
{
}
```

### Nombres de variables

Toda variable debe tener un nombre que describa con precisión el dato o la
responsabilidad que representa.

Reglas:

* Utilizar singular para una entidad y plural para una colección.
* Incluir la entidad en los identificadores, por ejemplo `$usuarioId` en lugar
  de `$id` cuando pueda existir ambigüedad.
* Utilizar nombres como `$cantidadTickets`, `$usuariosActivos` o
  `$fechaResolucion` en lugar de abreviaturas.
* Los booleanos deben expresar claramente una condición, por ejemplo
  `$isProduction`, `$usuarioActivo` o `$puedeEditarTicket`.
* Evitar nombres genéricos como `$data`, `$info`, `$item`, `$temp`, `$value`,
  `$result` o letras aisladas cuando exista un nombre de dominio más preciso.
* Mantener consistencia terminológica dentro del módulo y con las entidades de
  la base de datos.
* Las abreviaturas solamente se permiten cuando sean estándares ampliamente
  reconocibles, como `id`, `URL`, `HTTP`, `PDO` o `CSRF`.

No se debe utilizar código PHP global, excepto en puntos de entrada como:

* `public/index.php`
* Scripts ejecutables desde consola.
* Archivos de rutas.
* Archivos de configuración estrictamente necesarios.

### Convenciones PSR-4

El namespace principal es:

```php
App\
```

y corresponde al directorio:

```text
app/
```

El namespace:

```php
Database\
```

corresponde al directorio:

```text
database/
```

Los nombres de archivo deben respetar exactamente el nombre de la clase.

Ejemplo:

```text
app/Core/Database.php
```

debe contener:

```php
namespace App\Core;

final class Database
{
}
```

Esto es obligatorio para evitar problemas al desplegar en sistemas Linux sensibles a mayúsculas y minúsculas.

---

## 6. Convenciones de base de datos

La base de datos se llama:

```text
helpdesk_pro
```

Se utilizarán:

* Motor InnoDB.
* Codificación `utf8mb4`.
* Claves primarias numéricas llamadas `id`.
* Claves foráneas con formato `<entidad>_id`.
* Restricciones de integridad referencial.
* Índices en campos consultados frecuentemente.
* Consultas preparadas.
* Transacciones cuando una operación afecte múltiples tablas relacionadas.

Las tablas se nombrarán en español y en plural.

Ejemplos:

```text
usuarios
roles
tickets
categorias
prioridades
estados_ticket
ticket_comentarios
ticket_adjuntos
ticket_historial
```

No se debe guardar:

* Contraseñas en texto plano.
* Archivos binarios directamente en la base de datos.
* Datos derivados que puedan calcularse fácilmente, salvo justificación.
* Información duplicada sin una razón documentada.

---

## 7. Acceso a la base de datos

La conexión se obtiene exclusivamente mediante:

```php
App\Core\Database::connection()
```

No se deben crear conexiones PDO directamente en:

* Controladores.
* Vistas.
* Modelos concretos.
* Scripts web.
* Helpers.

La clase `Database` administra una única conexión PDO reutilizable durante la ejecución de la petición.

Configuración mínima de PDO:

```php
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
PDO::ATTR_EMULATE_PREPARES => false,
```

Todas las consultas que incluyan datos variables deben utilizar sentencias preparadas.

---

## 8. Reglas de arquitectura MVC

### Controladores

Los controladores deben:

* Recibir la petición.
* Validar el flujo general.
* Delegar la lógica de negocio.
* Seleccionar una vista o respuesta.
* Redireccionar cuando corresponda.

Los controladores no deben:

* Contener consultas SQL.
* Gestionar directamente PDO.
* Implementar lógica de negocio extensa.
* Generar grandes bloques de HTML.
* Acceder directamente a variables globales desde muchos lugares.

### Modelos

Los modelos deben representar el acceso a datos y operaciones relacionadas con una entidad.

Los modelos pueden:

* Consultar.
* Insertar.
* Actualizar.
* Eliminar de forma lógica.
* Transformar filas de base de datos.

Los modelos no deben:

* Renderizar vistas.
* Redireccionar.
* Manipular sesiones.
* Contener HTML.

### Servicios

Los servicios se utilizarán cuando una operación represente lógica de negocio que:

* Involucre varios modelos.
* Necesite una transacción.
* Sea reutilizada por más de un controlador.
* No pertenezca claramente a una única entidad.

No se debe crear un servicio para cada operación simple.

### Vistas

Las vistas deben:

* Mostrar datos recibidos desde el controlador.
* Escapar contenido dinámico.
* Contener la menor lógica posible.

Las vistas no deben:

* Ejecutar SQL.
* Acceder directamente a PDO.
* Modificar datos.
* Implementar reglas de negocio.

---

## 9. Seguridad obligatoria

Toda funcionalidad debe considerar:

* Consultas preparadas con PDO.
* Escape de salida con `htmlspecialchars`.
* Validación del lado servidor.
* Regeneración del ID de sesión después del login.
* Protección CSRF en formularios que modifican datos.
* Verificación de permisos en el backend.
* Control del tipo y tamaño de archivos adjuntos.
* Nombres internos aleatorios para archivos subidos.
* Contraseñas con `password_hash`.
* Verificación con `password_verify`.
* Mensajes de error que no expongan credenciales ni detalles sensibles.
* Cookies de sesión con configuración segura cuando el entorno lo permita.

Nunca se debe confiar exclusivamente en validaciones de JavaScript.

Ocultar un botón en la interfaz no reemplaza una verificación de permisos en el servidor.

---

## 10. Autenticación y autorización

La autenticación utilizará sesiones PHP.

La sesión debe guardar solamente la información necesaria:

```php
$_SESSION['usuario'] = [
    'id' => $usuario['id'],
    'nombre' => $usuario['nombre'],
    'apellido' => $usuario['apellido'],
    'rol_id' => $usuario['rol_id'],
    'rol' => $usuario['rol'],
];
```

Nunca se debe guardar en sesión:

* El hash de contraseña.
* La fila completa del usuario.
* Información innecesaria o sensible.

Roles iniciales:

* Administrador.
* Técnico.
* Cliente.

Reglas generales:

* El administrador administra usuarios, configuraciones y tickets.
* El técnico trabaja con tickets asignados o habilitados.
* El cliente crea tickets y consulta sus propios tickets.
* Toda autorización debe verificarse en el backend.

---

## 11. Reglas para los tickets

Un ticket debe tener como mínimo:

* Cliente creador.
* Categoría.
* Prioridad.
* Estado.
* Asunto.
* Descripción.
* Fecha de creación.

Un técnico puede ser opcional al momento de crear el ticket.

Toda modificación relevante debe registrarse en el historial.

Eventos auditables:

* Creación.
* Asignación.
* Cambio de técnico.
* Cambio de estado.
* Cambio de prioridad.
* Resolución.
* Cierre.
* Reapertura.
* Cancelación.

Los comentarios pueden ser:

* Públicos para el cliente.
* Internos para técnicos y administradores.

---

## 12. Seeds y scripts

Los datos iniciales deben crearse mediante seeds o scripts ejecutables por consola.

No se deben insertar datos administrativos desde `public/index.php`.

Los seeds deben ser idempotentes siempre que sea razonable.

Ejemplo:

```bash
php scripts/seed.php
```

Ejecutar varias veces el mismo seed no debería duplicar:

* Roles.
* Estados.
* Prioridades.
* Usuario administrador inicial.

---

## 13. Front Controller

`public/index.php` es el único punto de entrada web principal.

Debe mantenerse pequeño.

Su responsabilidad será:

1. Cargar Composer.
2. Cargar configuración.
3. Inicializar la sesión.
4. Crear los componentes principales.
5. Cargar las rutas.
6. Despachar la petición.
7. Enviar la respuesta.

No debe contener:

* Consultas SQL.
* Inserciones temporales.
* HTML de la aplicación.
* Lógica de autenticación completa.
* Lógica específica de tickets.

---

## 14. Dependencias

Antes de agregar una dependencia se debe evaluar:

1. Qué problema resuelve.
2. Si el problema puede resolverse razonablemente con PHP estándar.
3. Cuánto código y complejidad evita.
4. Si su uso aporta valor educativo o profesional.
5. Si está mantenida.
6. Si incorpora dependencias innecesarias.

No agregar dependencias sin autorización explícita.

Dependencias potencialmente aceptables:

* Cargador de variables `.env`.
* Generador de logs.
* Librería pequeña y específica que no oculte el aprendizaje principal.

### Testing automatizado con PHPUnit

PHPUnit es la herramienta de testing aprobada para el proyecto y está instalada
como dependencia de desarrollo mediante Composer.

Reglas obligatorias:

* Todo comportamiento nuevo o modificado que pueda verificarse de forma
  automatizada debe incluir o actualizar sus casos de prueba.
* Toda corrección de un defecto debe incorporar una prueba de regresión que
  falle antes de la corrección y pase después.
* Las pruebas deben ubicarse dentro de `tests/`, utilizar el namespace `Tests\`
  y terminar con el sufijo `Test.php`.
* Los casos de prueba deben organizarse por componente o capa, con nombres que
  describan claramente el comportamiento esperado.
* Las pruebas deben ser independientes entre sí y no depender de su orden de
  ejecución.
* No deben utilizar datos reales ni modificar la base de datos de desarrollo.
  Las pruebas que requieran persistencia deberán utilizar un entorno de prueba
  aislado, fixtures controlados y limpieza reproducible.
* Deben priorizarse pruebas para reglas de negocio, validaciones, permisos,
  middleware, servicios, modelos y componentes del núcleo.
* No deben escribirse pruebas artificiales sin valor solamente para aumentar
  métricas de cobertura.
* Cuando un comportamiento no pueda automatizarse razonablemente, la
  verificación manual realizada debe quedar informada al finalizar la tarea.

La suite completa se ejecuta desde la raíz del proyecto mediante:

```bash
composer test
```

Una tarea que modifica comportamiento no se considera terminada si sus pruebas
correspondientes no existen o si la suite de PHPUnit falla.

---

## 15. Alcance de la primera versión

La primera versión debe incluir:

* Estructura MVC.
* Router.
* Request y Response.
* Conexión PDO.
* Configuración mediante variables de entorno.
* Login y logout.
* Protección de rutas.
* Roles.
* CRUD de usuarios básico.
* CRUD de tickets.
* Categorías.
* Prioridades.
* Estados.
* Asignación de técnico.
* Comentarios.
* Notas internas.
* Adjuntos.
* Historial de cambios.
* Filtros.
* Paginación.
* Dashboard básico.
* Documentación de instalación.

No forman parte obligatoria de la primera versión:

* API REST completa.
* Aplicación móvil.
* React.
* WebSockets.
* Notificaciones push.
* Microservicios.
* Redis.
* Docker.
* Arquitectura hexagonal completa.
* Sistema de permisos granular.
* Integración con servicios externos.
* Recuperación de contraseña por correo real.
* Multiempresa.
* Chat en tiempo real.

Estas funcionalidades solamente podrán incorporarse después de completar la versión inicial.

---

## 16. Reglas para agentes de programación

Antes de modificar código, el agente debe:

1. Leer `AGENTS.md`.
2. Leer los documentos relevantes dentro de `docs/`.
3. Inspeccionar la implementación existente.
4. Respetar la arquitectura actual.
5. Identificar el alcance exacto de la tarea.
6. Evitar cambios no relacionados.

El agente no debe:

* Reescribir módulos completos sin necesidad.
* Introducir un framework.
* Agregar dependencias automáticamente.
* Cambiar nombres de tablas o columnas sin autorización.
* Crear abstracciones anticipadas.
* Implementar funcionalidades fuera del alcance solicitado.
* Modificar el esquema de base de datos sin actualizar la documentación.
* Eliminar código funcional solamente por preferencia estilística.
* Mezclar refactors grandes con nuevas funcionalidades.
* Crear interfaces para clases que solamente tienen una implementación sin una justificación real.
* Aplicar patrones de diseño por moda.

El agente debe priorizar:

* Cambios pequeños.
* Código legible.
* Responsabilidades claras.
* Compatibilidad con PHP 8.3.
* Seguridad.
* Facilidad de prueba.
* Facilidad de explicación.
* Métodos documentados mediante PHPDoc.
* Variables con nombres semánticos y no ambiguos.
* Consistencia con el código existente.

---

## 17. Procedimiento para cada tarea

Cada tarea debería seguir este orden:

1. Explicar brevemente el problema.
2. Identificar archivos involucrados.
3. Proponer el cambio mínimo.
4. Indicar riesgos o decisiones importantes.
5. Implementar.
6. Documentar todos los métodos creados o modificados.
7. Revisar que las variables representen claramente su contenido o función.
8. Verificar sintaxis.
9. Crear o actualizar los casos de prueba PHPUnit correspondientes.
10. Ejecutar `composer test`.
11. Probar manualmente el flujo afectado cuando corresponda.
12. Informar archivos modificados.
13. Documentar nuevas decisiones.
14. Proponer el siguiente paso sin implementarlo automáticamente.

Cuando una tarea sea grande, debe dividirse en cortes pequeños y verificables.

---

## 18. Criterios de finalización

Una tarea no se considera terminada solamente porque el código fue escrito.

Debe cumplir:

* Sintaxis PHP válida.
* Sin errores evidentes.
* Validación del lado servidor.
* Manejo de errores.
* Verificación de permisos.
* Consultas preparadas.
* Compatibilidad con el diseño existente.
* Todos los métodos creados o modificados documentados mediante PHPDoc.
* Variables y parámetros con nombres claros, precisos y consistentes.
* Casos de prueba PHPUnit para todo comportamiento automatizable.
* Suite completa ejecutada mediante `composer test` sin errores.
* Prueba manual complementaria cuando corresponda.
* Documentación actualizada cuando corresponda.

---

## 19. Filosofía del proyecto

HelpDesk Pro debe demostrar criterio técnico.

El código más complejo no es necesariamente el mejor.

Se prioriza:

* Código que pueda explicarse.
* Decisiones justificadas.
* Seguridad.
* Simplicidad.
* Consistencia.
* Finalización del producto.
* Calidad del repositorio.

El proyecto debe evitar convertirse en un framework casero innecesariamente grande.

La pregunta principal para cada decisión debe ser:

> ¿Esta implementación ayuda a construir un sistema de HelpDesk claro, seguro, mantenible y demostrable en una entrevista?

Si la respuesta no es claramente afirmativa, la solución debe simplificarse.
