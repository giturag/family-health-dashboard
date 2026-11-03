<?php
require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);

$billID = intval($_GET['billID'] ?? 0);
$userID = fhd_user_id();

$sql = "
    SELECT b.total_amount, hv.hvisitID, hv.visit_date, ltc.test_name
    FROM bill_headers b
    JOIN health_visits hv ON b.hvisitID_FK = hv.hvisitID
    LEFT JOIN lab_requests lt ON lt.hvisitID_FK = hv.hvisitID
    LEFT JOIN lab_test_items lti ON lti.requestID_FK = lt.labtestID
    LEFT JOIN lab_tests_catalog ltc ON ltc.testID = lti.testID_FK
    WHERE b.billID = ?
      AND (hv.patientID_FK = ? OR hv.patientID_FK IN (
          SELECT subID FROM subscribers WHERE guardianID = ?
      ))
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('iii', $billID, $userID, $userID);
$stmt->execute();
$res = $stmt->get_result();

$tests = [];
$visitDate = '';
$amount = 0;
$found = false;

while ($row = $res->fetch_assoc()) {
    $found = true;
    $amount = $row['total_amount'];
    $visitDate = $row['visit_date'];
    if (!empty($row['test_name'])) {
        $tests[] = $row['test_name'];
    }
}
$stmt->close();

if (!$found) {
    fhd_abort(403, 'Forbidden');
}

$tests = array_values(array_unique($tests));
?>
<p><strong>Visit Date:</strong> <?= fhd_e($visitDate) ?></p>
<p><strong>Consultation Fee:</strong> Ksh 1000</p>
<?php if ($tests): ?>
  <p><strong>Lab Tests:</strong></p>
  <ul>
    <?php foreach ($tests as $t): ?>
      <li><?= fhd_e($t) ?> - Ksh 500</li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
<p><strong>Total:</strong> Ksh <?= number_format((float) $amount) ?></p>
