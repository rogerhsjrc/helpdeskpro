# Arquitectura de HelpDesk Pro

## 1. Contexto

HelpDesk Pro es una aplicación web monolítica desarrollada en PHP puro.

Su arquitectura está inspirada en MVC, pero adaptada al alcance de un proyecto de portafolio. No se busca replicar todas las capacidades de un framework profesional.

El diseño debe permitir comprender claramente cómo una petición HTTP llega a la aplicación, es procesada y genera una respuesta.

---

## 2. Flujo principal

```text
Navegador
    ↓
Apache
    ↓
public/index.php
    ↓
Request
    ↓
Router
    ↓
Middleware
    ↓
Controller
    ↓
Service / Model
    ↓
PDO
    ↓
MariaDB
    ↓
Controller
    ↓
View
    ↓
Response
    ↓
Navegador
```

---

## 3. Front Controller

El archivo:

```text
public/index.php
```

será el único punto de entrada web principal.

Responsabilidades:

* Cargar `vendor/autoload.php`.
* Cargar variables de entorno.
* Configurar la sesión.
* Crear el objeto `Request`.
* Crear o recuperar el `Router`.
* Cargar las rutas.
* Despachar la petición.
* Enviar la respuesta.

Debe evitarse colocar lógica de negocio dentro de este archivo.

Cuando Apache utiliza la raíz del repositorio como `DocumentRoot`, el archivo
`.htaccess` de la raíz delega internamente las peticiones al directorio
`public/`. El archivo `public/.htaccess` envía las rutas que no corresponden a
archivos o directorios reales hacia `public/index.php`.

De esta forma, `public/index.php` continúa siendo el único punto de entrada PHP
y los directorios internos de la aplicación no quedan expuestos.

---

## 4. Componentes del núcleo

### `Database`

Ruta:

```text
app/Core/Database.php
```

Responsabilidad:

* Crear una conexión PDO.
* Configurar el modo de errores.
* Configurar el modo de obtención de resultados.
* Deshabilitar emulación de consultas preparadas.
* Reutilizar la misma conexión durante la ejecución.

### `Request`

Responsabilidad:

* Representar la petición HTTP.
* Obtener método.
* Obtener URI.
* Acceder a parámetros GET.
* Acceder a datos POST.
* Acceder a archivos.
* Consultar headers cuando sea necesario.

No debe implementar reglas de negocio.

La implementación normaliza el método y la ruta, elimina la barra final salvo
en `/` y mantiene separados los datos de query, formulario y archivos.

### `Response`

Responsabilidad:

* Representar una respuesta HTTP.
* Definir código de estado.
* Definir headers.
* Enviar contenido.
* Facilitar redirecciones.

También ofrece respuestas HTML y JSON. Los controladores deben devolver siempre
una instancia de `Response`.

### `Router`

Responsabilidad:

* Registrar rutas.
* Comparar método y URI.
* Extraer parámetros.
* Ejecutar middleware.
* Resolver controladores.
* Devolver una respuesta.
* Generar un error 404 cuando no exista una coincidencia.

El Router inicial soporta rutas estáticas, parámetros con el formato `{id}`,
middleware por ruta y respuestas 405. La resolución de controladores es
explícita y no utiliza un contenedor de dependencias.

### `Controller`

La clase base puede ofrecer:

* Renderizado de vistas.
* Redirecciones.
* Respuestas comunes.
* Acceso controlado a datos compartidos.

No debe transformarse en una clase global con demasiadas responsabilidades.

### `View`

La capa de vistas debe permitir:

* Renderizar una plantilla.
* Enviar datos desde el controlador.
* Reutilizar layouts y parciales.
* Escapar contenido dinámico.

Las vistas se almacenan bajo `app/Views`, pueden reutilizar layouts y reciben
únicamente los datos entregados por el controlador. El escape se realiza
mediante `View::escape()`.

---

## 5. Capas de aplicación

### Controladores

Ejemplos:

```text
AuthController
TicketController
UsuarioController
DashboardController
```

Los controladores coordinan el caso de uso, pero no deben contener SQL.

### Modelos

Ejemplos:

```text
Usuario
Ticket
Categoria
Prioridad
EstadoTicket
Comentario
Adjunto
```

Los modelos administran el acceso a datos relacionado con su entidad.

### Servicios

Ejemplos potenciales:

```text
AuthService
TicketService
UploadService
AuditService
```

Los servicios se crearán únicamente cuando exista lógica de negocio suficiente.

Ejemplo: crear un ticket, registrar un adjunto y generar un evento de historial dentro de una transacción puede pertenecer a `TicketService`.

### Middleware

Ejemplos:

```text
AuthMiddleware
GuestMiddleware
RoleMiddleware
CsrfMiddleware
```

Los middleware filtran la petición antes de ejecutar un controlador.

---

## 6. Dependencias entre capas

Dependencias permitidas:

```text
Controller → Service
Controller → Model
Service → Model
Model → Database
Middleware → Session / Service
View ← Controller
```

Dependencias que deben evitarse:

```text
View → Database
View → Model
Model → Controller
Model → View
Database → Controller
public/index.php → lógica específica de negocio
```

---

## 7. Estado y sesiones

La autenticación se gestionará mediante sesiones PHP.

La sesión debe inicializarse una sola vez durante el arranque de la aplicación.

La información del usuario autenticado debe mantenerse reducida.

Las operaciones comunes de sesión podrán encapsularse posteriormente en una clase como:

```text
app/Core/Session.php
```

No es obligatorio crearla antes de que exista una necesidad concreta.

---

## 8. Manejo de errores

Durante desarrollo:

* Mostrar errores útiles.
* Registrar excepciones.
* Mantener el encadenamiento de excepciones.

En producción:

* Mostrar mensajes genéricos.
* Registrar detalles en `storage/logs`.
* No exponer credenciales.
* No mostrar stack traces al usuario.

El núcleo podrá incorporar posteriormente un manejador global de excepciones.

---

## 9. Configuración

Los valores sensibles o dependientes del entorno deben estar fuera del código.

Ejemplos:

```text
APP_ENV
APP_DEBUG
APP_URL
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD
```

El archivo `.env` no debe subirse al repositorio.

Debe existir:

```text
.env.example
```

con valores de ejemplo no sensibles.

Las variables se cargan mediante `App\Core\Env`. Esta clase implementa el
formato mínimo requerido por el proyecto y respeta las variables definidas
previamente por el servidor. No se incorporó una dependencia externa para esta
responsabilidad.

---

## 10. Decisiones iniciales

### Se utilizará MVC

Motivo: separar responsabilidades y demostrar comprensión del ciclo de una aplicación PHP.

### Se utilizará PDO

Motivo: API estándar, soporte para consultas preparadas y facilidad para controlar errores.

### Se utilizará un Front Controller

Motivo: centralizar el procesamiento de todas las peticiones.

### Se utilizará PSR-4

Motivo: carga automática de clases y compatibilidad con prácticas estándar de PHP.

### No se utilizará un framework

Motivo: el objetivo educativo es comprender la infraestructura básica antes de repetir el proyecto con Laravel o Symfony.

### No se implementará un ORM

Motivo: se busca demostrar conocimiento de SQL, PDO y modelado relacional.

### No se aplicará una arquitectura excesivamente compleja

Motivo: el proyecto debe terminarse, poder explicarse y conservar un alcance razonable.
