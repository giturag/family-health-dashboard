<?php
declare(strict_types=1);

if (!defined('FHD_BOOTSTRAPPED')) {
    http_response_code(404);
    exit;
}

// Clear the session data and expire the session cookie.
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

session_destroy();
fhd_redirect(fhd_login_url());
