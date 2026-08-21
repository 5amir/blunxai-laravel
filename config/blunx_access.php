<?php

/**
 * Access rules configuration for Blunx AI
 *
 * This file defines access restrictions by ROLE.
 * NEGATIVE approach: you define what a user is NOT allowed to see.
 *
 * Three restriction levels:
 * - forbidden_tables: tables the role cannot access
 * - forbidden_columns: specific columns per table the role cannot access
 * - row_level: automatic row filtering (e.g. WHERE user_id = X)
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Access rules per role
    |--------------------------------------------------------------------------
    |
    | Add your roles here. Each role can have:
    | - forbidden_tables: array of forbidden tables
    | - forbidden_columns: array [table => [forbidden columns]]
    | - row_level: array [table => filter] to filter rows
    |
    | Examples:
    |
    | 'vendor' => [
    |     'forbidden_tables' => ['salaries', 'accounting'],
    |     'forbidden_columns' => [
    |         'users' => ['salary', 'social_security_number'],
    |         'orders' => ['margin'],
    |     ],
    |     'row_level' => [
    |         'orders' => ['column' => 'user_id', 'value' => 'USER_ID'],  // USER_ID is replaced with the user's ID
    |     ],
    | ],
    |
    */

    'vendor' => [
        // Completely forbidden tables
        'forbidden_tables' => [
            // 'salaries',
            // 'accounting',
        ],
        
        // Forbidden columns per table (use '*' for all tables)
        'forbidden_columns' => [
            // 'users' => ['salary', 'social_security_number', 'full_address'],
            // '*' => ['password', 'api_token'],
        ],
        
        // Automatic row filtering
        // Use 'USER_ID' as a placeholder, replaced with the authenticated user's ID
        'row_level' => [
            // 'orders' => ['column' => 'user_id', 'value' => 'USER_ID'],
            // 'products' => ['column' => 'vendor_id', 'value' => 'USER_ID'],
        ],
    ],

    'manager' => [
        'forbidden_tables' => [],
        'forbidden_columns' => [
            // 'users' => ['salary'],
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
    | Global configuration
    |--------------------------------------------------------------------------
    */
    
    // By default, when no role matches, apply these rules
    'default' => [
        'forbidden_tables' => [
            'users',
        ],
        'forbidden_columns' => [],
        'row_level' => [],
    ],

    // Enable/disable the permission system
    'enabled' => true,
];
