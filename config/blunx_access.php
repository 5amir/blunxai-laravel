<?php

/**
 * Configuration des droits d'accès pour Blunx AI
 * 
 * Ce fichier définit les restrictions d'accès par RÔLE.
 * Approche NÉGATIVE : vous définissez ce que le user N'A PAS le droit de voir.
 * 
 * Trois niveaux de restriction :
 * - forbidden_tables : Tables auxquelles le rôle n'a pas accès
 * - forbidden_columns : Colonnes spécifiques par table auxquelles le rôle n'a pas accès
 * - row_level : Filtrage automatique des lignes (ex: WHERE user_id = X)
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Règles d'accès par rôle
    |--------------------------------------------------------------------------
    |
    | Ajoutez vos rôles ici. Chaque rôle peut avoir :
    | - forbidden_tables : tableau de tables interdites
    | - forbidden_columns : tableau [table => [colonnes interdites]]
    | - row_level : tableau [table => filtre] pour filtrer les lignes
    |
    | Exemples :
    |
    | 'vendeur' => [
    |     'forbidden_tables' => ['salaires', 'comptabilite'],
    |     'forbidden_columns' => [
    |         'users' => ['salaire', 'numero_secu'],
    |         'orders' => ['marge'],
    |     ],
    |     'row_level' => [
    |         'orders' => ['column' => 'user_id', 'value' => 'USER_ID'],  // USER_ID sera remplacé par l'ID de l'utilisateur
    |     ],
    | ],
    |
    */

    'vendor' => [
        // Tables complètement interdites
        'forbidden_tables' => [
            // 'salaires',
            // 'comptabilite',
        ],
        
        // Colonnes interdites par table (mettre '*' pour toutes les tables)
        'forbidden_columns' => [
            // 'users' => ['salaire', 'numero_secu', 'adresse_complete'],
            // '*' => ['mot_de_passe', 'token_api'],
        ],
        
        // Filtrage automatique des lignes
        // Utiliser 'USER_ID' comme placeholder qui sera remplacé par l'ID de l'utilisateur connecté
        'row_level' => [
            // 'orders' => ['column' => 'user_id', 'value' => 'USER_ID'],
            // 'products' => ['column' => 'vendor_id', 'value' => 'USER_ID'],
        ],
    ],

    'manager' => [
        'forbidden_tables' => [],
        'forbidden_columns' => [
            // 'users' => ['salaire'],
        ],
        'row_level' => [],
    ],

    'admin' => [
        'forbidden_tables' => [],
        'forbidden_columns' => [],
        'row_level' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration globale
    |--------------------------------------------------------------------------
    */
    
    // Par défaut, si pas de rôle défini, appliquer ces règles
    'default' => [
        'forbidden_tables' => [
            'users',
        ],
        'forbidden_columns' => [],
        'row_level' => [],
    ],

    // Activer/désactiver le système de permissions
    'enabled' => true,
];
