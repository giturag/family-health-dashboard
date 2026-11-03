<?php
require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);
fhd_require_post();
fhd_verify_csrf();

$userID = fhd_user_id();
$allergyID = intval($_POST['id'] ?? 0);

if ($allergyID <= 0) {
    fhd_abort(400, 'Invalid allergy ID.');
}

// Only allow deleting a record that belongs to the user or a dependent.
$stmt = $conn->prepare(
    'DELETE a FROM allergy_records a
     JOIN subscribers s ON a.patientID_FK = s.subID
     WHERE a.allergyID = ? AND (s.subID = ? OR s.guardianID = ?)'
);
$stmt->bind_param('iii', $allergyID, $userID, $userID);
$stmt->execute();

if ($stmt->affected_rows < 1) {
    $stmt->close();
    fhd_abort(403, 'Forbidden');
}
$stmt->close();

fhd_redirect('allergies_and_conditions.php?deleted=1');
