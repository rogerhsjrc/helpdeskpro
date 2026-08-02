-- HelpDesk Pro
-- Actualiza una instalación existente para utilizar códigos estables de estado.
-- El script es idempotente para instalaciones que conservan los siete estados
-- maestros originales. Debe ejecutarse antes del seed actualizado de la Fase 4.

SET NAMES utf8mb4;

SET @codigo_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'estados_ticket'
      AND column_name = 'codigo'
);
SET @add_codigo_column_sql = IF(
    @codigo_column_exists = 0,
    'ALTER TABLE estados_ticket ADD COLUMN codigo VARCHAR(40) NULL AFTER id',
    'DO 0'
);
PREPARE add_codigo_column_statement FROM @add_codigo_column_sql;
EXECUTE add_codigo_column_statement;
DEALLOCATE PREPARE add_codigo_column_statement;

UPDATE estados_ticket
SET codigo = CASE nombre
    WHEN 'Abierto' THEN 'ABIERTO'
    WHEN 'Asignado' THEN 'ASIGNADO'
    WHEN 'En proceso' THEN 'EN_PROCESO'
    WHEN 'Pendiente del cliente' THEN 'PENDIENTE_CLIENTE'
    WHEN 'Resuelto' THEN 'RESUELTO'
    WHEN 'Cerrado' THEN 'CERRADO'
    WHEN 'Cancelado' THEN 'CANCELADO'
    ELSE codigo
END
WHERE codigo IS NULL;

ALTER TABLE estados_ticket
    MODIFY codigo VARCHAR(40) NOT NULL;

SET @codigo_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'estados_ticket'
      AND index_name = 'uq_estados_ticket_codigo'
);
SET @add_codigo_index_sql = IF(
    @codigo_index_exists = 0,
    'ALTER TABLE estados_ticket ADD UNIQUE INDEX uq_estados_ticket_codigo (codigo)',
    'DO 0'
);
PREPARE add_codigo_index_statement FROM @add_codigo_index_sql;
EXECUTE add_codigo_index_statement;
DEALLOCATE PREPARE add_codigo_index_statement;
