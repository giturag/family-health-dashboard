<?php
/**
 * Copy this file to config/config.local.php and fill in the real values.
 * config/config.local.php is gitignored and must never be committed.
 *
 * Environment variables (FHD_DB_HOST, FHD_DB_NAME, FHD_DB_USER,
 * FHD_DB_PASS, FHD_DEBUG) override the values below.
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'fhddb',
        'user' => 'fhdadmin',
        'pass' => '',
    ],
    'debug' => false,
];
