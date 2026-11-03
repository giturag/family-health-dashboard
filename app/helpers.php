<?php
declare(strict_types=1);

/** Escape a value for HTML output. */
function fhd_e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** True when the current request looks like it expects JSON. */
function fhd_wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return stripos($accept, 'application/json') !== false
        || strtolower($requestedWith) === 'xmlhttprequest';
}
