<?php
/**
 * Shared application bootstrap.
 *
 * Every web entry point should require this file instead of creating its own
 * session and database connection. It is safe to require more than once.
 */
declare(strict_types=1);

if (defined('FHD_BOOTSTRAPPED')) {
    return;
}
define('FHD_BOOTSTRAPPED', true);
define('FHD_ROOT', dirname(__DIR__));

$localConfig = FHD_ROOT . '/config/config.local.php';
$config = require (is_file($localConfig) ? $localConfig : FHD_ROOT . '/config/config.example.php');

$env = static function (string $key, ?string $default = null): ?string {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
};

$debug = filter_var($env('FHD_DEBUG', (string) ($config['debug'] ?? false)), FILTER_VALIDATE_BOOLEAN);

// Never show errors to users unless explicitly debugging. Log them instead.
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$dbConfig = $config['db'] ?? [];
$conn = new mysqli(
    (string) $env('FHD_DB_HOST', $dbConfig['host'] ?? 'localhost'),
    (string) $env('FHD_DB_USER', $dbConfig['user'] ?? ''),
    (string) $env('FHD_DB_PASS', $dbConfig['pass'] ?? ''),
    (string) $env('FHD_DB_NAME', $dbConfig['name'] ?? 'fhddb')
);

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    http_response_code(503);
    exit('Service temporarily unavailable.');
}
$conn->set_charset('utf8mb4');

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
