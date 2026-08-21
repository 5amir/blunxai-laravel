<?php

return [
    'enabled' => true,
    // URL du serveur BlunxAI SaaS
    'server_url' => env('BLUNX_SERVER_URL', 'https://blunxai.com'),

    // Clé API de l'application (générée depuis le dashboard BlunxAI)
    'api_key'    => env('BLUNX_API_KEY'),
    'llm_api_key' => env('BLUNX_LLM_API_KEY'),
    'db_connection' => env('BLUNX_DB_CONNECTION'),

    // URL de connexion BDD (DSN) — alternative aux identifiants séparés.
    // Accepte mysql://, mariadb://, postgres://, postgresql://, sqlite://,
    // sqlsrv:// avec ou sans SSL (sslmode=require|verify-ca|verify-full,
    // ssl=true, ssl-ca=…). Exemples :
    //   mysql://user:pass@host:3306/db?sslmode=require
    //   postgres://user:pass@host:5432/db?sslmode=verify-full&sslrootcert=ca.pem
    //   sqlite:///var/data/blunx.sqlite
    // Quand défini, Blunx enregistre une connexion Laravel 'blunx' depuis l'URL
    // (prioritaire sur la connexion par défaut de l'app pour les requêtes Blunx).
    'db_url' => env('BLUNX_DB_URL', env('DATABASE_URL')),

    // Utilisateur BDD en LECTURE SEULE utilisé pour exécuter les requêtes
    // Blunx (analyses, insights...). C'est le verrou de sécurité principal :
    // un rôle qui ne peut QUE lire.
    // ⚠️ OPTIONNEL quand une DSN est fournie (blunx.db_url) :
    //    - si l'URL contient déjà un rôle read-only (créé dans le SGBD) → laissez vide ;
    //    - remplissez uniquement si l'URL porte le rôle PRINCIPAL (lecture+écriture)
    //      et que vous voulez forcer l'exécution en lecture seule (dérivé de l'URL, SSL conservé).
    'readonly_db' => [
        'username' => env('BLUNX_READONLY_DB_USERNAME'),
        'password' => env('BLUNX_READONLY_DB_PASSWORD'),
    ],

    // Schéma PostgreSQL scanné par blunx:init / blunx:edit (public par défaut)
    'pgsql_schema' => env('BLUNX_PGSQL_SCHEMA', 'public'),
    'llm' => [
        'endpoint' => env('BLUNX_LLM_ENDPOINT'),
        'model'    => env('BLUNX_LLM_MODEL'),
        'driver'   => env('BLUNX_LLM_DRIVER', 'openai'), // openai | gemini | anthropic

        // Indique si le modèle supporte response_format: json_object
        'supports_json_format' => env('BLUNX_LLM_SUPPORTS_JSON_FORMAT', true),
    ],

    // Table des utilisateurs de l'application pour relier le user a ses données
    'users' => [
        'user_table'  => 'users',
        'primary_key' => 'id',
    ],

    // Devise utilisée pour formater les montants dans les prompts et rapports
    'currency' => [
        'code'   => env('BLUNX_CURRENCY_CODE',   'EUR'),   // ISO 4217 : EUR, USD, CAD, MAD...
        'symbol' => env('BLUNX_CURRENCY_SYMBOL', '€'),     // Symbole affiché dans les prompts
        'locale' => env('BLUNX_CURRENCY_LOCALE', 'fr_FR'), // Locale pour le formatage des nombres
    ],

    // Tables exclues par défaut lors de blunx:init 
    'excluded_tables' => [
        'migrations', 'failed_jobs', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'password_reset_tokens', 'sessions',
        'personal_access_tokens', 'blunx_conversations', 'blunx_messages',
        'blunx_dashboards', 'blunx_dashboard_widgets', 'blunx_insights',
        'blunx_widget_insight_settings', 'blunx_feedbacks', 'blunx_cache',
    ],

    // Langue utilisée par Blunx AI pour les messages et les vues.
    'locale' => env('BLUNX_LOCALE', 'fr'),
];