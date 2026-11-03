<?php
declare(strict_types=1);

/** Return (and lazily create) the CSRF token for this session. */
function fhd_csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Hidden form input carrying the CSRF token. */
function fhd_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . fhd_e(fhd_csrf_token()) . '">';
}

/**
 * Verify the CSRF token on a state-changing request. Accepts either the
 * csrf_token POST field or the X-CSRF-Token header (for fetch/AJAX calls).
 */
function fhd_verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (!is_string($submitted) || $submitted === ''
        || !hash_equals(fhd_csrf_token(), $submitted)) {
        http_response_code(403);
        if (fhd_wants_json()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'Invalid CSRF token']);
        } else {
            header('Content-Type: text/plain; charset=UTF-8');
            echo '403 Invalid or missing CSRF token.';
        }
        exit;
    }
}
