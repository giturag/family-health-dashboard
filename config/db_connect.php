<?php
/**
 * Legacy shared bootstrap kept for existing includes.
 * New code should require app/bootstrap.php directly.
 */
require_once __DIR__ . '/../app/bootstrap.php';

// Any page that includes this file requires a signed-in subscriber.
fhd_require_login();
