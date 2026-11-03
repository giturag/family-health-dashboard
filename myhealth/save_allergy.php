<?php
require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);
fhd_require_post();
fhd_verify_csrf();

$userID = fhd_user_id();
$id = intval($_POST['id'] ?? 0);
$allergy_name = trim((string) ($_POST['allergy_name'] ?? ''));
$severity = trim((string) ($_POST['severity'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));

if ($notes === '') {
    $notes = 'N/A';
}

if ($id <= 0 || $allergy_name === ''
    || !in_array($severity, ['Mild', 'Moderate', 'Severe', 'Critical', 'Unknown'], true)) {
    fhd_abort(422, 'Invalid allergy data.');
}

// Verify ownership before updating.
$check = $conn->prepare(
    'SELECT 1 FROM allergy_records a
     JOIN subscribers s ON a.patientID_FK = s.subID
     WHERE a.allergyID = ? AND (s.subID = ? OR s.guardianID = ?)
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
    'UPDATE allergy_records SET allergen = ?, severity = ?, notes = ? WHERE allergyID = ?'
);
$stmt->bind_param('sssi', $allergy_name, $severity, $notes, $id);
$stmt->execute();
$stmt->close();

echo 'Allergy updated successfully.';
