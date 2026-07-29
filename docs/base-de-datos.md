# Base de datos de HelpDesk Pro

## 1. Configuración

* Base: `helpdesk_pro`.
* Motor: InnoDB.
* Codificación: `utf8mb4`.
* Colación de referencia: `utf8mb4_unicode_ci`.
* Claves primarias numéricas llamadas `id`.
* Claves foráneas con el formato `<entidad>_id`.

El esquema estructural se encuentra en
[`database/schema.sql`](../database/schema.sql).

## 2. Tablas

| Tabla | Responsabilidad |
|---|---|
| `roles` | Roles disponibles para autorización |
| `usuarios` | Identidad, credenciales y estado de usuarios |
| `categorias` | Clasificación funcional de tickets |
| `prioridades` | Nivel de impacto de una incidencia |
| `estados_ticket` | Etapas del ciclo de vida |
| `tickets` | Incidencias y sus relaciones principales |
| `ticket_comentarios` | Comentarios públicos y notas internas |
| `ticket_adjuntos` | Metadatos de archivos asociados |
| `ticket_historial` | Eventos auditables de un ticket |

## 3. Relaciones principales

```text
roles 1 ─── N usuarios

usuarios (cliente) 1 ─── N tickets
usuarios (técnico) 1 ─── N tickets
categorias         1 ─── N tickets
prioridades        1 ─── N tickets
estados_ticket     1 ─── N tickets

tickets  1 ─── N ticket_comentarios
tickets  1 ─── N ticket_adjuntos
tickets  1 ─── N ticket_historial
```

Las eliminaciones de usuarios y tablas maestras están restringidas cuando
existen tickets relacionados. Los comentarios, adjuntos e historial dependen
del ticket y se eliminan en cascada con él.

## 4. Instalación

La base debe crearse vacía antes de aplicar el esquema:

```sql
CREATE DATABASE helpdesk_pro
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

Desde PowerShell:

```powershell
mysql -u root -p helpdesk_pro `
    --execute="source database/schema.sql"
```

Después se cargan los datos indispensables:

```powershell
php .\scripts\seed.php
```

Los datos demostrativos son optativos:

```powershell
php .\scripts\seed.php --demo
```

## 5. Seeds

`MasterDataSeeder` crea de manera idempotente:

* 3 roles.
* 5 categorías.
* 4 prioridades.
* 7 estados.

`AdminSeeder` crea el administrador configurado en `.env`.

`DemoSeeder` agrega dos usuarios ficticios y un ticket de ejemplo. No se
ejecuta durante una instalación normal.

## 6. Seguridad

* Los hashes se generan mediante `password_hash`.
* El esquema no contiene credenciales.
* Los archivos adjuntos no se almacenan como binarios.
* Los valores variables deben consultarse mediante sentencias preparadas.
* Las operaciones que afecten varias tablas deben utilizar transacciones.
