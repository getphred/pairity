<?php

return [
    // General CLI
    'app.version' => '{name} version {version}',
    'app.usage' => "\nUsage:\n  pairity [command] [arguments] [options]\n\n",
    'app.available_commands' => 'Available commands:',

    // Errors
    'error.command_not_found' => "Command '{command}' not found.",
    'error.uncaught_exception' => 'Uncaught Exception: {message}',
    'error.unconstrained_query' => 'Unconstrained {operation} queries are disabled by configuration.',
    'error.invalid_binding_type' => 'Invalid binding type: {type}.',
    'error.method_not_found' => 'Method [{method}] does not exist on Builder or its associated DAO.',
    'error.optimistic_lock_failed' => 'Optimistic locking failed for table [{table}] and ID [{id}].',
    'error.uow_commit_failed' => 'Failed to commit Unit of Work: {message}',
    'error.uow_no_dao' => 'Cannot commit DTO without an associated DAO.',
    'error.schema_file_not_found' => 'Schema file [{path}] not found.',
    'error.schema_parse_failed' => 'Failed to parse YAML file [{path}]: {message}',
    'error.schema_invalid_content' => 'YAML content must be an associative array.',
    'error.schema_invalid_column' => 'Invalid column definition for [{column}]. \'type\' is required.',
    'error.container_not_set' => 'Container instance not set on DatabaseManager.',
    'error.driver_not_supported' => 'Driver [{driver}] not supported.',
    'error.upsert_not_supported' => 'Upsert is not supported by this driver.',
    'error.container_class_not_found' => 'Target class [{class}] does not exist.',
    'error.container_not_instantiable' => 'Target class [{class}] is not instantiable.',
    'error.container_unresolvable' => 'Unresolvable dependency [{parameter}] in class {class}.',
    'error.relation_not_found' => 'Relationship [{relation}] not found on DAO.',
    'error.metadata_cache_failed' => 'Failed to cache metadata for [{key}].',

    // Validation
    'validation.failed' => 'Validation failed.',

    // Database Health Check
    'command.init.description' => 'Initialize the Pairity project structure.',
    'command.db_health_check.description' => 'Verify database connection health and heartbeat.',
    'command.db_health_check.checking' => 'Checking health for connection: {name}...',
    'command.db_health_check.success' => 'Connection {name} is healthy.',
    'command.db_health_check.failed' => 'Connection {name} is unhealthy.',
    'command.db_health_check.error' => 'Error checking health: {message}',

    // Metadata & Caching
    'command.schema_lint.description' => 'Lint Pairity YAML table definitions in the schema directory.',
    'command.schema_json.description' => 'Generate the JSON Schema for Pairity YAML table definitions.',
    'command.schema_snapshot.description' => 'Export the current YAML source of truth into a PHP baseline snapshot.',
    'command.schema_snapshot.starting' => 'Generating schema snapshots...',
    'command.schema_snapshot.finished' => 'Snapshot generation completed successfully.',
    'command.db_seed.description' => 'Seed the database with records.',
    'command.make_seeder.description' => 'Create a new seeder class.',
    'command.make_factory.description' => 'Create a new factory class for a model.',
    'command.make_migration.description' => 'Create a new manual migration file for custom SQL or data changes.',
    'command.migration_data.description' => 'Execute procedural PHP data migrations.',
    'command.make_yaml_fromdb.description' => 'Reverse-engineer an existing database to generate YAML schema files.',
    'command.db_check_sync.description' => 'Verify synchronization of manual migration files and seed files.',
    'command.cache_clear.description' => 'Clear the Pairity metadata cache.',
    'command.cache_clear.starting' => 'Clearing metadata cache...',
    'command.cache_clear.success' => 'Metadata cache cleared successfully.',
    'command.cache_clear.error' => 'Failed to clear metadata cache.',

    // Code Generation
    'command.make_model.description' => 'Generate DTO and DAO classes from YAML schema definitions.',
    'command.make_model.starting' => 'Generating DTO and DAO classes...',
    'command.make_model.finished' => 'Code generation completed successfully.',
];
