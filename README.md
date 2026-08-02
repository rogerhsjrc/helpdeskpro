# HelpDesk Pro

Sistema de gestión de incidencias desarrollado en PHP puro como proyecto de
portfolio profesional.

El objetivo es demostrar una arquitectura MVC explícita, autenticación y
autorización mediante sesiones, acceso seguro con PDO, modelado relacional,
testing automatizado y documentación técnica sin depender de un framework PHP.

## Estado

El baseline reproducible, el núcleo HTTP, la autenticación, los catálogos y el
flujo principal de tickets están completos. El próximo hito es incorporar
comentarios públicos y notas internas.

Actualmente el proyecto incluye:

* Configuración mediante variables de entorno.
* Front Controller.
* `Request`, `Response` y Router con parámetros y middleware.
* Controladores y vistas con layouts.
* Respuestas 404 y 405.
* Login y logout mediante sesiones.
* Protección CSRF en formularios de autenticación.
* Middleware para rutas públicas y privadas.
* Dashboard provisional protegido.
* Administración de categorías, prioridades y estados de ticket.
* Creación, lectura y edición autorizada de tickets.
* Asignación de técnicos y cambios auditados de estado y prioridad.
* Filtros combinables, búsqueda y paginación de tickets.
* Navegación contextual para Administrador, Técnico y Cliente.
* Esquema relacional reproducible.
* Seeds idempotentes y datos demo optativos.
* Suite PHPUnit.

El alcance completo puede consultarse en [docs/roadmap.md](docs/roadmap.md).

## Requisitos

* PHP 8.3 o superior.
* MariaDB con InnoDB y `utf8mb4`.
* Apache con `mod_rewrite`.
* Composer.

El esquema también fue verificado con MySQL 8.4.3, incluido en el entorno
Laragon utilizado durante el desarrollo.

## Instalación

1. Instalar las dependencias:

   ```powershell
   composer install
   ```

2. Crear la configuración local:

   ```powershell
   Copy-Item .env.example .env
   ```

3. Completar las credenciales de base de datos y definir una contraseña segura
   en `ADMIN_PASSWORD`.

4. Crear la base:

   ```sql
   CREATE DATABASE helpdesk_pro
       CHARACTER SET utf8mb4
       COLLATE utf8mb4_unicode_ci;
   ```

5. Aplicar el esquema desde PowerShell:

   ```powershell
   mysql -u root -p helpdesk_pro `
       --execute="source database/schema.sql"
   ```

6. Cargar catálogos y administrador:

   ```powershell
   php .\scripts\seed.php
   ```

7. Opcionalmente, cargar usuarios y un ticket demostrativo:

   ```powershell
   php .\scripts\seed.php --demo
   ```

8. Configurar el dominio local para que Apache sirva el proyecto. En Laragon,
   la URL utilizada es:

   ```text
   http://helpdeskpro.test
   ```

Los seeds pueden ejecutarse varias veces sin duplicar los registros iniciales.

## Testing

La suite completa se ejecuta mediante:

```powershell
$env:XDEBUG_MODE = "off"
composer test
```

PHPUnit utiliza la configuración de `phpunit.xml`.

## Arquitectura

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
Service / Model
    ↓
Database
    ↓
View / Response
```

Documentación relacionada:

* [Arquitectura](docs/arquitectura.md)
* [Requisitos](docs/requisitos.md)
* [Base de datos](docs/base-de-datos.md)
* [Seguridad](docs/seguridad.md)
* [Decisiones técnicas](docs/decisiones-tecnicas.md)
* [Roadmap](docs/roadmap.md)

## Datos sensibles

El archivo `.env`, las sesiones, logs, uploads, dependencias y cachés están
excluidos del repositorio. `.env.example` contiene únicamente nombres y valores
de referencia; la contraseña administrativa debe definirse localmente.

## Licencia

Este proyecto se distribuye bajo la [licencia MIT](LICENSE).

Copyright © 2026 Rogelio Sanchez.
