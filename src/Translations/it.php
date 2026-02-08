<?php

return [
    // CLI Generale
    'app.version' => '{name} versione {version}',
    'app.usage' => "\nUtilizzo:\n  pairity [comando] [argomenti] [opzioni]\n\n",
    'app.available_commands' => 'Comandi disponibili:',

    // Errori
    'error.command_not_found' => "Comando '{command}' non trovato.",
    'error.uncaught_exception' => 'Eccezione non gestita: {message}',
    'error.unconstrained_query' => 'Le query {operation} senza restrizioni sono disabilitate dalla configurazione.',
    'error.invalid_binding_type' => 'Tipo di binding non valido: {type}.',
    'error.method_not_found' => 'Il metodo [{method}] non esiste nel Builder o nel suo DAO associato.',
    'error.optimistic_lock_failed' => 'Blocco ottimistico fallito per la tabella [{table}] e ID [{id}].',
    'error.uow_commit_failed' => 'Salvataggio dell\'Unità di Lavoro fallito: {message}',
    'error.uow_no_dao' => 'Impossibile salvare il DTO senza un DAO associato.',
    'error.schema_file_not_found' => 'File di schema [{path}] non trovato.',
    'error.schema_parse_failed' => 'Analisi del file YAML [{path}] fallita: {message}',
    'error.schema_invalid_content' => 'Il contenuto YAML deve essere un array associativo.',
    'error.schema_invalid_column' => 'Definizione di colonna non valida per [{column}]. \'type\' è richiesto.',
    'error.container_not_set' => 'Istanza del container non impostata in DatabaseManager.',
    'error.driver_not_supported' => 'Driver [{driver}] non supportato.',
    'error.upsert_not_supported' => 'Upsert non è supportato da questo driver.',
    'error.container_class_not_found' => 'Classe di destinazione [{class}] non trovata.',
    'error.container_not_instantiable' => 'Classe di destinazione [{class}] non istanziabile.',
    'error.container_unresolvable' => 'Dipendenza non risolvibile [{parameter}] nella classe {class}.',
    'error.relation_not_found' => 'Relazione [{relation}] non trovata nel DAO.',
    'error.metadata_cache_failed' => 'Salvataggio in cache dei metadati fallito per [{key}].',

    // Validazione
    'validation.failed' => 'Validazione fallita.',

    // Controllo Salute Database
    'command.init.description' => 'Inizializzare la struttura del progetto Pairity.',
    'command.db_health_check.description' => 'Verifica la salute della connessione al database.',
    'command.db_health_check.checking' => 'Verifica salute per la connessione: {name}...',
    'command.db_health_check.success' => 'La connessione {name} è sana.',
    'command.db_health_check.failed' => 'La connessione {name} non è sana.',
    'command.db_health_check.error' => 'Errore durante la verifica della salute: {message}',

    // Metadati e Cache
    'command.schema_lint.description' => 'Analizza definizioni di tabella YAML di Pairity.',
    'command.schema_json.description' => 'Genera lo schema JSON per le definizioni YAML di Pairity.',
    'command.schema_snapshot.description' => 'Esporta la sorgente YAML attuale in uno snapshot PHP.',
    'command.schema_snapshot.starting' => 'Generazione snapshot dello schema...',
    'command.schema_snapshot.finished' => 'Generazione snapshot completata con successo.',
    'command.db_seed.description' => 'Popola il database con record.',
    'command.make_seeder.description' => 'Crea una nuova classe seeder.',
    'command.make_factory.description' => 'Crea una nuova classe factory per un modello.',
    'command.make_migration.description' => 'Crea un nuovo file di migrazione manuale.',
    'command.migration_data.description' => 'Esegue migrazioni di dati PHP procedurali.',
    'command.make_yaml_fromdb.description' => 'Ingegneria inversa di un database per generare schemi YAML.',
    'command.db_check_sync.description' => 'Verifica la sincronizzazione di file di migrazione e seeder.',
    'command.cache_clear.description' => 'Pulisce la cache dei metadati di Pairity.',
    'command.cache_clear.starting' => 'Pulizia cache dei metadati...',
    'command.cache_clear.success' => 'Cache dei metadati pulita con successo.',
    'command.cache_clear.error' => 'Pulizia cache dei metadati fallita.',

    // Generazione Codice
    'command.make_model.description' => 'Genera classi DTO e DAO dalle definizioni YAML.',
    'command.make_model.starting' => 'Generazione classi DTO e DAO...',
    'command.make_model.finished' => 'Generazione codice completata con successo.',
];
