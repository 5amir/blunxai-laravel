<?php

return [
    'enabled' => true,
    // BlunxAI SaaS server URL
    'server_url' => env('BLUNX_SERVER_URL', 'https://blunxai.com'),

    // Application API key (generated from the BlunxAI dashboard)
    'api_key'    => env('BLUNX_API_KEY'),
    'llm_api_key' => env('BLUNX_LLM_API_KEY'),
    'db_connection' => env('BLUNX_DB_CONNECTION'),

    // Database connection URL (DSN) — alternative to separate credentials.
    // Accepts mysql://, mariadb://, postgres://, postgresql://, sqlite://,
    // sqlsrv:// with or without SSL (sslmode=require|verify-ca|verify-full,
    // ssl=true, ssl-ca=…). Examples:
    //   mysql://user:pass@host:3306/db?sslmode=require
    //   postgres://user:pass@host:5432/db?sslmode=verify-full&sslrootcert=ca.pem
    //   sqlite:///var/data/blunx.sqlite
    // When set, Blunx registers a 'blunx' Laravel connection from the URL
    // (takes precedence over the app's default connection for Blunx queries).
    'db_url' => env('BLUNX_DB_URL', env('DATABASE_URL')),

    // Read-only database user used to run Blunx queries (analysis, insights…).
    // This is the primary security lock: a role that can only read.
    // ⚠️ OPTIONAL when a DSN is provided (blunx.db_url):
    //    - if the URL already carries a read-only role (created in the DBMS),
    //      leave empty;
    //    - fill it only if the URL carries the MAIN role (read+write) and you
    //      want to force read-only execution (derived from the URL, SSL kept).
    'readonly_db' => [
        'username' => env('BLUNX_READONLY_DB_USERNAME'),
        'password' => env('BLUNX_READONLY_DB_PASSWORD'),
    ],

    // PostgreSQL schema scanned by blunx:init / blunx:edit (public by default)
    'pgsql_schema' => env('BLUNX_PGSQL_SCHEMA', 'public'),
    'llm' => [
        'endpoint' => env('BLUNX_LLM_ENDPOINT'),
        'model'    => env('BLUNX_LLM_MODEL'),
        'driver'   => env('BLUNX_LLM_DRIVER', 'openai'), // openai | gemini | anthropic

        // Whether the model supports response_format: json_object
        'supports_json_format' => env('BLUNX_LLM_SUPPORTS_JSON_FORMAT', true),
    ],

    // Application users table used to link a user to its data
    'users' => [
        'user_table'  => 'users',
        'primary_key' => 'id',
    ],

    // Currency used to format amounts in prompts and reports
    'currency' => [
        'code'   => env('BLUNX_CURRENCY_CODE',   'EUR'),   // ISO 4217: EUR, USD, CAD, MAD…
        'symbol' => env('BLUNX_CURRENCY_SYMBOL', '€'),     // Symbol shown in prompts
        'locale' => env('BLUNX_CURRENCY_LOCALE', 'fr_FR'), // Locale used to format numbers
    ],

    // Tables excluded by default during blunx:init
    'excluded_tables' => [
        'migrations', 'failed_jobs', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'password_reset_tokens', 'sessions',
        'personal_access_tokens', 'blunx_conversations', 'blunx_messages',
        'blunx_dashboards', 'blunx_dashboard_widgets', 'blunx_insights',
        'blunx_widget_insight_settings', 'blunx_feedbacks', 'blunx_cache',
    ],

    // Language used by Blunx AI for messages and views.
    'locale' => env('BLUNX_LOCALE', 'fr'),
];