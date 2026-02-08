<?php

return [
    // CLI Général
    'app.version' => '{name} version {version}',
    'app.usage' => "\nUtilisation:\n  pairity [commande] [arguments] [options]\n\n",
    'app.available_commands' => 'Commandes disponibles:',

    // Erreurs
    'error.command_not_found' => "Commande '{command}' non trouvée.",
    'error.uncaught_exception' => 'Exception non capturée: {message}',
    'error.unconstrained_query' => 'Les requêtes {operation} sans contraintes sont désactivées par configuration.',
    'error.invalid_binding_type' => 'Type de liaison invalide: {type}.',
    'error.method_not_found' => 'La méthode [{method}] n\'existe pas sur Builder ou son DAO associé.',
    'error.optimistic_lock_failed' => 'Échec du verrouillage optimiste pour la table [{table}] et l\'ID [{id}].',
    'error.uow_commit_failed' => 'Échec de la validation de l\'Unité de Travail: {message}',
    'error.uow_no_dao' => 'Impossible de valider le DTO sans un DAO associé.',
    'error.schema_file_not_found' => 'Fichier de schéma [{path}] non trouvé.',
    'error.schema_parse_failed' => 'Échec de l\'analyse du fichier YAML [{path}]: {message}',
    'error.schema_invalid_content' => 'Le contenu YAML doit être un tableau associatif.',
    'error.schema_invalid_column' => 'Définition de colonne invalide pour [{column}]. \'type\' est requis.',
    'error.container_not_set' => 'Instance du conteneur non définie sur DatabaseManager.',
    'error.driver_not_supported' => 'Pilote [{driver}] non supporté.',
    'error.upsert_not_supported' => 'Upsert n\'est pas supporté par ce pilote.',
    'error.container_class_not_found' => 'La classe cible [{class}] n\'existe pas.',
    'error.container_not_instantiable' => 'La classe cible [{class}] n\'est pas instanciable.',
    'error.container_unresolvable' => 'Dépendance non résoluble [{parameter}] dans la classe {class}.',
    'error.relation_not_found' => 'Relation [{relation}] non trouvée sur le DAO.',
    'error.metadata_cache_failed' => 'Échec de la mise en cache des métadonnées pour [{key}].',

    // Validation
    'validation.failed' => 'Échec de la validation.',

    // Vérification de l'état de la base de données
    'command.init.description' => 'Initialiser la structure du projet Pairity.',
    'command.db_health_check.description' => 'Vérifier la santé de la connexion à la base de données.',
    'command.db_health_check.checking' => 'Vérification de la santé pour la connexion: {name}...',
    'command.db_health_check.success' => 'La connexion {name} est saine.',
    'command.db_health_check.failed' => 'La connexion {name} est malsaine.',
    'command.db_health_check.error' => 'Erreur lors de la vérification de la santé: {message}',

    // Métadonnées et Cache
    'command.schema_lint.description' => 'Analyser les définitions de table YAML de Pairity.',
    'command.schema_json.description' => 'Générer le schéma JSON pour les définitions YAML de Pairity.',
    'command.schema_snapshot.description' => 'Exporter la source de vérité YAML actuelle vers un instantané PHP.',
    'command.schema_snapshot.starting' => 'Génération des instantanés de schéma...',
    'command.schema_snapshot.finished' => 'Génération d\'instantané terminée avec succès.',
    'command.db_seed.description' => 'Semer la base de données avec des enregistrements.',
    'command.make_seeder.description' => 'Créer une nouvelle classe de semeur.',
    'command.make_factory.description' => 'Créer une nouvelle classe d\'usine pour un modèle.',
    'command.make_migration.description' => 'Créer un nouveau fichier de migration manuel.',
    'command.migration_data.description' => 'Exécuter des migrations de données PHP procédurales.',
    'command.make_yaml_fromdb.description' => 'Inverser l\'ingénierie d\'une base de données pour générer des schémas YAML.',
    'command.db_check_sync.description' => 'Vérifier la synchronisation des fichiers de migration et des semeurs.',
    'command.cache_clear.description' => 'Effacer le cache des métadonnées de Pairity.',
    'command.cache_clear.starting' => 'Effacement du cache des métadonnées...',
    'command.cache_clear.success' => 'Cache des métadonnées effacé avec succès.',
    'command.cache_clear.error' => 'Échec de l\'effacement du cache des métadonnées.',

    // Génération de Code
    'command.make_model.description' => 'Générer des classes DTO et DAO à partir des définitions YAML.',
    'command.make_model.starting' => 'Génération des classes DTO et DAO...',
    'command.make_model.finished' => 'Génération de code terminée avec succès.',
];
