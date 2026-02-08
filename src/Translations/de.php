<?php

return [
    // CLI Allgemein
    'app.version' => '{name} Version {version}',
    'app.usage' => "\nVerwendung:\n  pairity [Befehl] [Argumente] [Optionen]\n\n",
    'app.available_commands' => 'Verfügbare Befehle:',

    // Fehler
    'error.command_not_found' => "Befehl '{command}' nicht gefunden.",
    'error.uncaught_exception' => 'Unbehandelte Ausnahme: {message}',
    'error.unconstrained_query' => 'Eingeschränkte {operation}-Abfragen sind per Konfiguration deaktiviert.',
    'error.invalid_binding_type' => 'Ungültiger Bindungstyp: {type}.',
    'error.method_not_found' => 'Methode [{method}] existiert nicht im Builder oder dem zugehörigen DAO.',
    'error.optimistic_lock_failed' => 'Optimistisches Sperren fehlgeschlagen für Tabelle [{table}] und ID [{id}].',
    'error.uow_commit_failed' => 'Fehler beim Bestätigen der Arbeitseinheit: {message}',
    'error.uow_no_dao' => 'Arbeitseinheit kann nicht ohne zugehöriges DAO bestätigt werden.',
    'error.schema_file_not_found' => 'Schemadatei [{path}] nicht gefunden.',
    'error.schema_parse_failed' => 'Fehler beim Parsen der YAML-Datei [{path}]: {message}',
    'error.schema_invalid_content' => 'YAML-Inhalt muss ein assoziatives Array sein.',
    'error.schema_invalid_column' => 'Ungültige Spaltendefinition für [{column}]. \'type\' ist erforderlich.',
    'error.container_not_set' => 'Container-Instanz im DatabaseManager nicht gesetzt.',
    'error.driver_not_supported' => 'Treiber [{driver}] nicht unterstützt.',
    'error.upsert_not_supported' => 'Upsert wird von diesem Treiber nicht unterstützt.',
    'error.container_class_not_found' => 'Zielklasse [{class}] existiert nicht.',
    'error.container_not_instantiable' => 'Zielklasse [{class}] ist nicht instanziierbar.',
    'error.container_unresolvable' => 'Nicht auflösbare Abhängigkeit [{parameter}] in Klasse {class}.',
    'error.relation_not_found' => 'Beziehung [{relation}] im DAO nicht gefunden.',
    'error.metadata_cache_failed' => 'Fehler beim Cachen der Metadaten für [{key}].',

    // Validierung
    'validation.failed' => 'Validierung fehlgeschlagen.',

    // Datenbank-Gesundheitscheck
    'command.init.description' => 'Pairity-Projektstruktur initialisieren.',
    'command.db_health_check.description' => 'Überprüfen der Datenbankverbindungsgesundheit.',
    'command.db_health_check.checking' => 'Überprüfe Gesundheit für Verbindung: {name}...',
    'command.db_health_check.success' => 'Verbindung {name} ist gesund.',
    'command.db_health_check.failed' => 'Verbindung {name} ist ungesund.',
    'command.db_health_check.error' => 'Fehler beim Überprüfen der Gesundheit: {message}',

    // Metadaten und Cache
    'command.schema_lint.description' => 'Pairity YAML-Tabellendefinitionen prüfen.',
    'command.schema_json.description' => 'JSON-Schema für Pairity YAML-Tabellendefinitionen generieren.',
    'command.schema_snapshot.description' => 'Aktuelle YAML-Datenquelle in einen PHP-Snapshot exportieren.',
    'command.schema_snapshot.starting' => 'Generiere Schema-Snapshots...',
    'command.schema_snapshot.finished' => 'Snapshot-Generierung erfolgreich abgeschlossen.',
    'command.db_seed.description' => 'Datenbank mit Datensätzen füllen.',
    'command.make_seeder.description' => 'Eine neue Seeder-Klasse erstellen.',
    'command.make_factory.description' => 'Eine neue Factory-Klasse für ein Modell erstellen.',
    'command.make_migration.description' => 'Eine neue manuelle Migrationsdatei erstellen.',
    'command.migration_data.description' => 'Prozedurale PHP-Datenmigrationen ausführen.',
    'command.make_yaml_fromdb.description' => 'Datenbank zurückentwickeln, um YAML-Schemas zu generieren.',
    'command.db_check_sync.description' => 'Synchronisation von Migrationsdateien und Seedern prüfen.',
    'command.cache_clear.description' => 'Pairity-Metadaten-Cache leeren.',
    'command.cache_clear.starting' => 'Leere Metadaten-Cache...',
    'command.cache_clear.success' => 'Metadaten-Cache erfolgreich geleert.',
    'command.cache_clear.error' => 'Fehler beim Leeren des Metadaten-Caches.',

    // Codegenerierung
    'command.make_model.description' => 'DTO- und DAO-Klassen aus YAML-Definitionen generieren.',
    'command.make_model.starting' => 'Generiere DTO- und DAO-Klassen...',
    'command.make_model.finished' => 'Codegenerierung erfolgreich abgeschlossen.',
];
