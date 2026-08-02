# Decisiones técnicas de HelpDesk Pro

## 1. PHP sin framework

La primera versión utiliza PHP puro para demostrar el ciclo HTTP, MVC,
autenticación, autorización y acceso a datos de manera explícita.

No se intenta construir un framework general ni replicar Laravel o Symfony.

## 2. MVC sencillo

Los controladores coordinan casos de uso, los modelos acceden a datos, los
servicios encapsulan lógica transversal y las vistas presentan información.

Las abstracciones se incorporan únicamente cuando existe una necesidad real.

## 3. Front Controller y Router propio

`public/index.php` centraliza el arranque. El Router soporta los métodos y
parámetros necesarios para el producto, middleware y respuestas 404/405.

No se implementa resolución automática de dependencias ni un contenedor.

## 4. PDO sin ORM

PDO permite demostrar SQL, sentencias preparadas, transacciones y modelado
relacional sin ocultarlos detrás de un ORM.

La conexión se obtiene exclusivamente desde `App\Core\Database`.

## 5. Configuración de entorno

`App\Core\Env` carga el subconjunto de formato `.env` requerido por el proyecto.
Se evitó incorporar una dependencia para una necesidad acotada.

## 6. PHPUnit

PHPUnit es la dependencia de desarrollo aprobada. Cada comportamiento
automatizable debe incluir casos de prueba y la suite completa debe pasar antes
de cerrar una tarea.

## 7. Renderizado del lado del servidor

La primera versión utiliza vistas PHP, HTML, CSS y JavaScript vanilla para
mantener el foco en el backend y completar un producto estable.

Vue puede evaluarse después de publicar la primera versión. Su adopción deberá
resolver interacciones concretas y no reemplazar validaciones, permisos o reglas
de negocio del servidor.

## 8. Seeds separados por finalidad

El esquema contiene sólo estructura. Los catálogos y el administrador se crean
mediante seeds idempotentes, mientras que los datos demostrativos son optativos.

Esto evita mezclar instalación, credenciales y contenido de muestra.

## 9. Licencia MIT

El proyecto se publica bajo licencia MIT para permitir que su código sea
consultado, utilizado y adaptado, siempre que se conserve el aviso de copyright
y la licencia original.

Esta decisión favorece su finalidad educativa y de portfolio sin imponer
restricciones copyleft a quienes estudien o reutilicen partes del proyecto.

## 10. Códigos estables para estados de ticket

Los nombres de los estados pueden cambiar desde la administración y, por lo
tanto, no son identificadores seguros para reglas de negocio. Cada estado posee
un código único que se define al crearlo y luego se presenta como inmutable.

Los servicios de tickets utilizarán constantes de `EstadoTicket` para resolver
el estado inicial y los efectos de resolución, cierre, cancelación y reapertura.

## 11. Código público de tickets

Los tickets utilizarán un código visible con el formato
`HD-YYYYMMDD-XXXXXX`, donde el último segmento se generará mediante aleatoriedad
criptográficamente segura. La inserción deberá detectar una colisión de la
restricción única y reintentar un número acotado de veces.

El identificador numérico continuará siendo la clave interna y no se expondrá
como código de seguimiento.

## 12. Autorización de lectura de tickets en la consulta

`RoleMiddleware` valida que la ruta admita el rol autenticado, mientras que
`Ticket` agrega la regla concreta de propiedad o asignación a cada consulta.
Esta defensa en profundidad impide que el controlador recupere un ticket ajeno
y mantiene el mismo resultado 404 para recursos inexistentes o no visibles.

El listado utiliza paginación mediante conteo y `LIMIT`/`OFFSET` enlazados como
enteros. Se escogieron páginas de 10 registros y un orden descendente estable
por fecha de creación e identificador.

## 13. Creación transaccional y código público

La creación involucra catálogos, tickets e historial, por lo que a partir de
este caso de uso se justifica `TicketService`. El servicio comparte una conexión
con los modelos y controla una transacción que sólo se confirma después de
registrar el evento inicial.

El código público combina la fecha con tres bytes aleatorios representados como
seis caracteres hexadecimales. Si MariaDB informa una colisión específica de
`uq_tickets_codigo`, el servicio genera otro valor hasta un máximo de cinco
intentos; cualquier otro error se propaga y revierte la operación.

## 14. Edición limitada del contenido original

Categoría, asunto y descripción forman el contenido original editable. La
prioridad, el estado y el técnico quedan fuera para conservar sus reglas y
eventos específicos. El administrador puede corregir estos campos en cualquier
ticket; el cliente propietario sólo mientras el ticket esté `ABIERTO` y sin
asignación.

La mutación bloquea la fila con `FOR UPDATE`, compara valores y registra un
evento `EDICION` por cada campo cambiado. Esto evita auditoría artificial cuando
el formulario se envía sin modificaciones.

## 15. Asignación independiente del estado

Asignar o reasignar actualiza `tecnico_id` y `fecha_asignacion_at`, pero no
ejecuta una transición implícita. Así, el cambio de estado conserva reglas y
auditoría propias en el corte siguiente. Los eventos `ASIGNACION` y
`CAMBIO_TECNICO` permiten distinguir la primera selección de un reemplazo.

## 16. Matriz explícita de estados

Las transiciones se definen mediante los códigos estables y no por nombres u
orden configurables. Resolver y cerrar actualizan sus fechas dentro del mismo
`UPDATE`; una reapertura limpia fechas que ya no representan el estado actual.
La prioridad permanece independiente y conserva su evento propio.

## 17. Filtros acumulables dentro del ámbito visible

El listado construye una única condición a partir de la autorización por rol y
los filtros opcionales de estado, prioridad, técnico y texto. La misma condición
se reutiliza para el conteo y la página, evitando totales inconsistentes.

La búsqueda usa `LOCATE` con parámetros distintos para código y asunto. Así se
mantiene literal el texto recibido, incluidos `%` y `_`, sin concatenarlo al SQL.
La paginación reconstruye su query string mediante `http_build_query`.

## 18. Navegación contextual sin autorización visual

El dashboard y el listado muestran accesos acordes al rol y al ticket para
reducir recorridos innecesarios. El controlador calcula las acciones de cada
fila y la vista se limita a presentarlas.

Esta decisión no convierte la interfaz en una barrera de seguridad: acceder a
una URL directa continúa pasando por `AuthMiddleware`, `RoleMiddleware` y, en
las mutaciones, por la autorización del recurso dentro de `TicketService`.
