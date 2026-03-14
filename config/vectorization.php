<?php

/**
 * Vectorization Configuration
 *
 * Clean code vectorization for semantic search and code understanding.
 * No AI service dependencies - designed to plug into Claude Code via MCP.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Storage Driver
    |--------------------------------------------------------------------------
    |
    | Where to store vectors. 'file' for local JSON storage, 'sqlite' for
    | embedded database, or 'redis' for distributed caching.
    |
    */
    'driver' => env('VECTORIZATION_DRIVER', 'file'),

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | Base path for vector storage when using file driver.
    |
    */
    'storage_path' => env('VECTORIZATION_PATH', storage_path('vectors')),

    /*
    |--------------------------------------------------------------------------
    | Chunking Strategy
    |--------------------------------------------------------------------------
    |
    | How to split code into chunks for vectorization.
    | - 'symbol': By functions/classes/methods (recommended)
    | - 'line': Fixed line counts
    | - 'semantic': Natural code blocks
    |
    */
    'chunking' => [
        'strategy' => env('VECTORIZATION_CHUNK_STRATEGY', 'symbol'),
        'max_lines' => env('VECTORIZATION_MAX_LINES', 100),
        'overlap_lines' => env('VECTORIZATION_OVERLAP', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Patterns
    |--------------------------------------------------------------------------
    |
    | Which files to include/exclude from vectorization.
    |
    */
    'patterns' => [
        'include' => [
            '*.php',
            '*.js',
            '*.ts',
            '*.tsx',
            '*.py',
            '*.go',
            '*.rs',
            '*.java',
            '*.rb',
        ],
        'exclude' => [
            'vendor/*',
            'node_modules/*',
            '*.min.js',
            '*.min.css',
            'storage/*',
            '.git/*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Index Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for the vector index.
    |
    */
    'index' => [
        'name' => env('VECTORIZATION_INDEX_NAME', 'codebase'),
        'auto_refresh' => env('VECTORIZATION_AUTO_REFRESH', true),
        'refresh_on_change' => env('VECTORIZATION_REFRESH_ON_CHANGE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Integration
    |--------------------------------------------------------------------------
    |
    | Settings for Model Context Protocol server exposure.
    | Allows Claude Code to query vectors directly.
    |
    */
    'mcp' => [
        'enabled' => env('MCP_ENABLED', true),
        'port' => env('MCP_PORT', 3100),
        'host' => env('MCP_HOST', '127.0.0.1'),
        'tools' => [
            'search_code' => true,
            'list_symbols' => true,
            'get_context' => true,
        ],
    ],
];
