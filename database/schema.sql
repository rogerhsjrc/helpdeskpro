-- HelpDesk Pro
-- Esquema estructural para una base de datos vacía.
-- La base de datos debe crearse previamente con codificación utf8mb4.

SET NAMES utf8mb4;

CREATE TABLE roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_roles_nombre UNIQUE (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rol_id TINYINT UNSIGNED NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    apellido VARCHAR(80) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password VARCHAR(255) NOT NULL,
    telefono VARCHAR(30) NULL,
    avatar VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    email_verificado_at DATETIME NULL,
    ultimo_acceso_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_usuarios_email UNIQUE (email),
    CONSTRAINT fk_usuarios_roles
        FOREIGN KEY (rol_id)
        REFERENCES roles(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    INDEX idx_usuarios_rol_id (rol_id),
    INDEX idx_usuarios_activo (activo),
    INDEX idx_usuarios_nombre_apellido (apellido, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categorias (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_categorias_nombre UNIQUE (nombre),
    INDEX idx_categorias_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE prioridades (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    nivel TINYINT UNSIGNED NOT NULL,
    descripcion VARCHAR(255) NULL,
    color VARCHAR(20) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_prioridades_nombre UNIQUE (nombre),
    CONSTRAINT uq_prioridades_nivel UNIQUE (nivel),
    INDEX idx_prioridades_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE estados_ticket (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(40) NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    descripcion VARCHAR(255) NULL,
    orden TINYINT UNSIGNED NOT NULL,
    es_final BOOLEAN NOT NULL DEFAULT FALSE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_estados_ticket_codigo UNIQUE (codigo),
    CONSTRAINT uq_estados_ticket_nombre UNIQUE (nombre),
    CONSTRAINT uq_estados_ticket_orden UNIQUE (orden),
    INDEX idx_estados_ticket_activo (activo),
    INDEX idx_estados_ticket_es_final (es_final)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NOT NULL,
    cliente_id INT UNSIGNED NOT NULL,
    tecnico_id INT UNSIGNED NULL,
    categoria_id SMALLINT UNSIGNED NOT NULL,
    prioridad_id TINYINT UNSIGNED NOT NULL,
    estado_id TINYINT UNSIGNED NOT NULL,
    asunto VARCHAR(180) NOT NULL,
    descripcion TEXT NOT NULL,
    fecha_asignacion_at DATETIME NULL,
    fecha_resolucion_at DATETIME NULL,
    fecha_cierre_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_tickets_codigo UNIQUE (codigo),
    CONSTRAINT fk_tickets_cliente
        FOREIGN KEY (cliente_id)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_tecnico
        FOREIGN KEY (tecnico_id)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_categoria
        FOREIGN KEY (categoria_id)
        REFERENCES categorias(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_prioridad
        FOREIGN KEY (prioridad_id)
        REFERENCES prioridades(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_tickets_estado
        FOREIGN KEY (estado_id)
        REFERENCES estados_ticket(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    INDEX idx_tickets_cliente_id (cliente_id),
    INDEX idx_tickets_tecnico_id (tecnico_id),
    INDEX idx_tickets_categoria_id (categoria_id),
    INDEX idx_tickets_prioridad_id (prioridad_id),
    INDEX idx_tickets_estado_id (estado_id),
    INDEX idx_tickets_created_at (created_at),
    INDEX idx_tickets_estado_prioridad (estado_id, prioridad_id),
    FULLTEXT INDEX ft_tickets_asunto_descripcion (asunto, descripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_comentarios (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    comentario TEXT NOT NULL,
    es_interno BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_comentarios_ticket
        FOREIGN KEY (ticket_id)
        REFERENCES tickets(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_ticket_comentarios_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    INDEX idx_ticket_comentarios_ticket_id (ticket_id),
    INDEX idx_ticket_comentarios_usuario_id (usuario_id),
    INDEX idx_ticket_comentarios_created_at (created_at),
    INDEX idx_ticket_comentarios_ticket_fecha (ticket_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_adjuntos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    comentario_id BIGINT UNSIGNED NULL,
    usuario_id INT UNSIGNED NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_interno VARCHAR(255) NOT NULL,
    ruta VARCHAR(500) NOT NULL,
    mime_type VARCHAR(150) NOT NULL,
    extension VARCHAR(20) NULL,
    tamanio_bytes BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_ticket_adjuntos_nombre_interno UNIQUE (nombre_interno),
    CONSTRAINT fk_ticket_adjuntos_ticket
        FOREIGN KEY (ticket_id)
        REFERENCES tickets(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_ticket_adjuntos_comentario
        FOREIGN KEY (comentario_id)
        REFERENCES ticket_comentarios(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_ticket_adjuntos_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    INDEX idx_ticket_adjuntos_ticket_id (ticket_id),
    INDEX idx_ticket_adjuntos_comentario_id (comentario_id),
    INDEX idx_ticket_adjuntos_usuario_id (usuario_id),
    INDEX idx_ticket_adjuntos_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ticket_historial (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,
    tipo_evento VARCHAR(60) NOT NULL,
    campo_modificado VARCHAR(80) NULL,
    valor_anterior TEXT NULL,
    valor_nuevo TEXT NULL,
    descripcion VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_historial_ticket
        FOREIGN KEY (ticket_id)
        REFERENCES tickets(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_ticket_historial_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    INDEX idx_ticket_historial_ticket_id (ticket_id),
    INDEX idx_ticket_historial_usuario_id (usuario_id),
    INDEX idx_ticket_historial_tipo_evento (tipo_evento),
    INDEX idx_ticket_historial_created_at (created_at),
    INDEX idx_ticket_historial_ticket_fecha (ticket_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
