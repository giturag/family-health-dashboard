<?php
require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);
fhd_require_post();
fhd_verify_csrf();

$userID = fhd_user_id();
$id = intval($_POST['id'] ?? 0);
$conditionName = trim((string) ($_POST['condition_name'] ?? ''));
$status = trim((string) ($_POST['status'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));

if ($notes === '') {
    $notes = 'N/A';
}

if ($id <= 0 || $conditionName === '' || !in_array($status, ['Active', 'Resolved'], true)) {
    fhd_abort(422, 'Invalid condition data.');
}

// Verify ownership before updating.
$check = $conn->prepare(
    'SELECT 1 FROM chronic_condition_records c
     JOIN subscribers s ON c.patientID_FK = s.subID
     WHERE c.conditionID = ? AND (s.subID = ? OR s.guardianID = ?)
     LIMIT 1'
);
$check->bind_param('iii', $id, $userID, $userID);
$check->execute();
$allowed = (bool) $check->get_result()->fetch_row();
$check->close();

if (!$allowed) {
    fhd_abort(403, 'Forbidden');
}

$stmt = $conn->prepare(
    'UPDATE chronic_condition_records SET condition_name = ?, status = ?, notes = ? WHERE conditionID = ?'
);
$stmt->bind_param('sssi', $conditionName, $status, $notes, $id);
$stmt->execute();
$stmt->close();

echo 'Condition updated successfully.';
