<?php

return [
    // CLI General
    'app.version' => '{name} versión {version}',
    'app.usage' => "\nUso:\n  pairity [comando] [argumentos] [opciones]\n\n",
    'app.available_commands' => 'Comandos disponibles:',

    // Errores
    'error.command_not_found' => "Comando '{command}' no encontrado.",
    'error.uncaught_exception' => 'Excepción no capturada: {message}',
    'error.unconstrained_query' => 'Las consultas de {operation} sin restricciones están deshabilitadas por configuración.',
    'error.invalid_binding_type' => 'Tipo de vinculación inválido: {type}.',
    'error.method_not_found' => 'El método [{method}] no existe en el Builder o su DAO asociado.',
    'error.optimistic_lock_failed' => 'Error de bloqueo optimista para la tabla [{table}] e ID [{id}].',
    'error.uow_commit_failed' => 'Error al confirmar la Unidad de Trabajo: {message}',
    'error.uow_no_dao' => 'No se puede confirmar el DTO sin un DAO asociado.',
    'error.schema_file_not_found' => 'Archivo de esquema [{path}] no encontrado.',
    'error.schema_parse_failed' => 'Error al analizar el archivo YAML [{path}]: {message}',
    'error.schema_invalid_content' => 'El contenido YAML debe ser un array asociativo.',
    'error.schema_invalid_column' => 'Definición de columna inválida para [{column}]. Se requiere \'type\'.',
    'error.container_not_set' => 'Instancia del contenedor no establecida en DatabaseManager.',
    'error.driver_not_supported' => 'Controlador [{driver}] no soportado.',
    'error.upsert_not_supported' => 'Upsert no es soportado por este controlador.',
    'error.container_class_not_found' => 'La clase destino [{class}] no existe.',
    'error.container_not_instantiable' => 'La clase destino [{class}] no es instanciable.',
    'error.container_unresolvable' => 'Dependencia no resoluble [{parameter}] en la clase {class}.',
    'error.relation_not_found' => 'Relación [{relation}] no encontrada en el DAO.',
    'error.metadata_cache_failed' => 'Error al cachear metadatos para [{key}].',

    // Validación
    'validation.failed' => 'Validación fallida.',

    // Verificación de Salud de la Base de Datos
    'command.init.description' => 'Inicializar la estructura del proyecto Pairity.',
    'command.db_health_check.description' => 'Verificar la salud de la conexión a la base de datos.',
    'command.db_health_check.checking' => 'Verificando salud para la conexión: {name}...',
    'command.db_health_check.success' => 'La conexión {name} está sana.',
    'command.db_health_check.failed' => 'La conexión {name} no está sana.',
    'command.db_health_check.error' => 'Error al verificar la salud: {message}',

    // Metadatos y Caché
    'command.schema_lint.description' => 'Analizar definiciones de tabla YAML de Pairity.',
    'command.schema_json.description' => 'Generar el esquema JSON para definiciones YAML de Pairity.',
    'command.schema_snapshot.description' => 'Exportar la fuente de verdad YAML actual a una instantánea PHP.',
    'command.schema_snapshot.starting' => 'Generando instantáneas de esquema...',
    'command.schema_snapshot.finished' => 'Generación de instantánea completada con éxito.',
    'command.db_seed.description' => 'Sembrar la base de datos con registros.',
    'command.make_seeder.description' => 'Crear una nueva clase de sembrador.',
    'command.make_factory.description' => 'Crear una nueva clase de factoría para un modelo.',
    'command.make_migration.description' => 'Crear un nuevo archivo de migración manual.',
    'command.migration_data.description' => 'Ejecutar migraciones de datos PHP procedimentales.',
    'command.make_yaml_fromdb.description' => 'Ingeniería inversa de una base de datos para generar esquemas YAML.',
    'command.db_check_sync.description' => 'Verificar la sincronización de archivos de migración y semillas.',
    'command.cache_clear.description' => 'Limpiar el caché de metadatos de Pairity.',
    'command.cache_clear.starting' => 'Limpiando caché de metadatos...',
    'command.cache_clear.success' => 'Caché de metadatos limpiado con éxito.',
    'command.cache_clear.error' => 'Error al limpiar el caché de metadatos.',

    // Generación de Código
    'command.make_model.description' => 'Generar clases DTO y DAO a partir de definiciones YAML.',
    'command.make_model.starting' => 'Generando clases DTO y DAO...',
    'command.make_model.finished' => 'Generación de código completada con éxito.',
];
