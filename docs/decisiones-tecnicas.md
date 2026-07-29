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
