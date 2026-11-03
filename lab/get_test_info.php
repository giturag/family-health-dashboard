<?php
require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role('labtech');

$labtechID = fhd_user_id();
$labtestID = intval($_GET['labtest_id'] ?? 0);

// Requests are visible when assigned to this lab tech or when the patient is
// in the lab tech's region.
$accessStmt = $conn->prepare(
    'SELECT 1
     FROM lab_requests lr
     JOIN subscribers p ON p.subID = lr.patientID_FK
     JOIN subscribers me ON me.subID = ?
     WHERE lr.labtestID = ? AND (lr.labtechID_FK = ? OR p.region = me.region)
     LIMIT 1'
);
$accessStmt->bind_param('iii', $labtechID, $labtestID, $labtechID);
$accessStmt->execute();
$allowed = (bool) $accessStmt->get_result()->fetch_row();
$accessStmt->close();

if (!$allowed) {
    fhd_abort(403, 'Forbidden');
}

$sql = "
    SELECT ti.test_name, ti.test_type
    FROM lab_test_items li
    JOIN lab_tests_catalog ti ON ti.testID = li.testID_FK
    WHERE li.requestID_FK = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $labtestID);
$stmt->execute();
$results = $stmt->get_result();

echo "<h3>Test Items</h3><ul>";
while ($row = $results->fetch_assoc()) {
    echo "<li><strong>" . fhd_e($row['test_name']) . "</strong>: " . fhd_e($row['test_type']) . "</li>";
}
echo "</ul>";
$stmt->close();
