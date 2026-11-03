<?php
require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);

$userID = fhd_user_id();
$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare(
    'SELECT a.* FROM allergy_records a
     JOIN subscribers s ON a.patientID_FK = s.subID
     WHERE a.allergyID = ? AND (s.subID = ? OR s.guardianID = ?)
     LIMIT 1'
);
$stmt->bind_param('iii', $id, $userID, $userID);
$stmt->execute();
$record = $stmt->get_result()->fetch_assoc();
$stmt->close();

header('Content-Type: application/json; charset=UTF-8');
if (!$record) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

echo json_encode($record);
