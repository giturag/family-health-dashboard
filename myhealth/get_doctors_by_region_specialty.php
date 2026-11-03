<?php
require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);

$patientID = intval($_GET['patient'] ?? 0);
$specialty = trim((string) ($_GET['specialty'] ?? ''));
$response = [];

if ($patientID > 0 && $specialty !== '') {
    // The patient must be the signed-in user or one of their dependents.
    fhd_require_patient_access($conn, $patientID);

    $regionStmt = $conn->prepare('SELECT region FROM subscribers WHERE subID = ? LIMIT 1');
    $regionStmt->bind_param('i', $patientID);
    $regionStmt->execute();
    $region = $regionStmt->get_result()->fetch_assoc()['region'] ?? '';
    $regionStmt->close();

    if ($region !== '') {
        $docStmt = $conn->prepare(
            "SELECT s.subID, CONCAT(s.fname, ' ', s.lname) AS doctor_name
             FROM subscribers s
             JOIN doctor_profiles d ON s.subID = d.docID_FK
             WHERE s.region = ? AND d.specialty = ?
             ORDER BY s.fname, s.lname"
        );
        $docStmt->bind_param('ss', $region, $specialty);
        $docStmt->execute();
        $result = $docStmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $response[] = $row;
        }
        $docStmt->close();
    }
}

header('Content-Type: application/json; charset=UTF-8');
echo json_encode($response);
