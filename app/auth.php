<?php
declare(strict_types=1);

/** True when a subscriber session is present. */
function fhd_is_logged_in(): bool
{
    return isset($_SESSION['loggedinusr'], $_SESSION['subID']);
}

/** Lower-cased role from the session. */
function fhd_role(): string
{
    return strtolower((string) ($_SESSION['sub_role'] ?? ''));
}

/** Current subscriber ID as an integer (0 when not logged in). */
function fhd_user_id(): int
{
    return (int) ($_SESSION['subID'] ?? 0);
}

/**
 * URL of signin.php relative to the current entry script, so the helper works
 * from the project root and from every role subdirectory.
 */
function fhd_login_url(): string
{
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $realScript = $script !== '' ? (realpath($script) ?: $script) : '';
    $root = realpath(FHD_ROOT) ?: FHD_ROOT;

    if ($realScript !== '' && strncmp($realScript, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) === 0) {
        $relative = substr($realScript, strlen($root) + 1);
        $depth = substr_count($relative, DIRECTORY_SEPARATOR);
        return str_repeat('../', $depth) . 'signin.php';
    }

    return 'signin.php';
}

/** Redirect and stop execution. */
function fhd_redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Require an authenticated session. */
function fhd_require_login(): void
{
    if (!fhd_is_logged_in()) {
        fhd_redirect(fhd_login_url());
    }
}

/**
 * Require one of the given roles. Returns 403 rather than redirecting so an
 * authenticated user cannot silently fall through to another portal.
 */
function fhd_require_role($roles): void
{
    fhd_require_login();

    $allowed = array_map(
        static fn ($role): string => strtolower((string) $role),
        (array) $roles
    );

    if (!in_array(fhd_role(), $allowed, true)) {
        http_response_code(403);
        if (fhd_wants_json()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'Forbidden']);
        } else {
            header('Content-Type: text/plain; charset=UTF-8');
            echo '403 Forbidden';
        }
        exit;
    }
}

/** Reject non-POST requests (use for actions that change state). */
function fhd_require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('405 Method Not Allowed');
    }
}

/** IDs of the signed-in patient and their dependents. */
function fhd_patient_ids(mysqli $conn): array
{
    $userID = fhd_user_id();
    $ids = [$userID];

    $stmt = $conn->prepare('SELECT subID FROM subscribers WHERE guardianID = ?');
    $stmt->bind_param('i', $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int) $row['subID'];
    }
    $stmt->close();

    return array_values(array_unique(array_map('intval', $ids)));
}

/** True when the doctor has a visit or appointment with the patient. */
function fhd_doctor_can_access_patient(mysqli $conn, int $patientID): bool
{
    $doctorID = fhd_user_id();
    if ($doctorID <= 0 || $patientID <= 0) {
        return false;
    }

    $stmt = $conn->prepare(
        'SELECT 1 FROM health_visits WHERE doctorID_FK = ? AND patientID_FK = ?
         UNION
         SELECT 1 FROM appointments WHERE doctorID_FK = ? AND patientID_FK = ?
         LIMIT 1'
    );
    $stmt->bind_param('iiii', $doctorID, $patientID, $doctorID, $patientID);
    $stmt->execute();
    $allowed = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();

    return $allowed;
}

/**
 * Stop the request unless the target patient is the signed-in user or one of
 * their dependents.
 */
function fhd_require_patient_access(mysqli $conn, int $patientID): void
{
    $userID = fhd_user_id();
    if ($patientID <= 0) {
        fhd_abort(403, 'Forbidden');
    }

    $stmt = $conn->prepare(
        'SELECT 1 FROM subscribers
         WHERE subID = ? AND (subID = ? OR guardianID = ?) LIMIT 1'
    );
    $stmt->bind_param('iii', $patientID, $userID, $userID);
    $stmt->execute();
    $allowed = (bool) $stmt->get_result()->fetch_row();
    $stmt->close();

    if (!$allowed) {
        fhd_abort(403, 'Forbidden');
    }
}

/** Abort the request with a generic error. */
function fhd_abort(int $status, string $message): void
{
    http_response_code($status);
    if (fhd_wants_json()) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => $message]);
    } else {
        header('Content-Type: text/plain; charset=UTF-8');
        echo $message;
    }
    exit;
}
