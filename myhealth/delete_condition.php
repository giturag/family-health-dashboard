<?php
require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);
fhd_require_post();
fhd_verify_csrf();

$userID = fhd_user_id();
$conditionID = intval($_POST['id'] ?? 0);

if ($conditionID <= 0) {
    fhd_abort(400, 'Invalid condition ID.');
}

// Only allow deleting a record that belongs to the user or a dependent.
$stmt = $conn->prepare(
    'DELETE c FROM chronic_condition_records c
     JOIN subscribers s ON c.patientID_FK = s.subID
     WHERE c.conditionID = ? AND (s.subID = ? OR s.guardianID = ?)'
);
$stmt->bind_param('iii', $conditionID, $userID, $userID);
$stmt->execute();

if ($stmt->affected_rows < 1) {
    $stmt->close();
    fhd_abort(403, 'Forbidden');
}
$stmt->close();

fhd_redirect('allergies_and_conditions.php?deleted=1');
