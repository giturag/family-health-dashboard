<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['doctor', 'specialtydr']);

$doctorID = fhd_user_id();
$requestID = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch request info; the request must belong to the signed-in doctor.
$reqStmt = $conn->prepare(
    'SELECT lt.labtestID, lt.test_centre, s.fname, s.lname
     FROM lab_requests lt
     JOIN subscribers s ON s.subID = lt.patientID_FK
     WHERE lt.labtestID = ? AND lt.doctorID_FK = ?
     LIMIT 1'
);
$reqStmt->bind_param('ii', $requestID, $doctorID);
$reqStmt->execute();
$req = $reqStmt->get_result()->fetch_assoc();
$reqStmt->close();

if (!$req) {
    fhd_abort(403, 'Forbidden');
}

// Fetch test items
$itemsStmt = $conn->prepare(
    'SELECT lti.lbitemID, ltc.test_name, ltc.test_type,
            lti.test_date, lti.result_date, lti.test_result, lti.test_verdict
     FROM lab_test_items lti
     JOIN lab_tests_catalog ltc ON lti.testID_FK = ltc.testID
     WHERE lti.requestID_FK = ?
     ORDER BY lti.test_date'
);
$itemsStmt->bind_param('i', $requestID);
$itemsStmt->execute();
$items = $itemsStmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Lab Request Details</title>
  <meta charset="UTF-8">
  <style>
    body { font-family: 'Segoe UI', sans-serif; padding: 20px; background: #f9f9f9; }
    .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 800px; margin: auto; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #ccc; padding: 8px; }
    th { background-color: #007bff; color: white; }
  </style>
</head>
<body>

<div class="card">
  <h2>Lab Request #<?= $req['labtestID'] ?></h2>
  <p><strong>Patient:</strong> <?= htmlspecialchars($req['fname'] . ' ' . $req['lname']) ?></p>
  <p><strong>Test Centre:</strong> <?= htmlspecialchars($req['test_centre']) ?></p>

  <h3>Test Items</h3>
  <table>
    <tr>
      <th>Test Name</th>
      <th>Type</th>
      <th>Requested</th>
      <th>Result Date</th>
      <th>Result</th>
      <th>Verdict</th>
    </tr>
    <?php while ($t = $items->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($t['test_name']) ?></td>
        <td><?= htmlspecialchars($t['test_type']) ?></td>
        <td><?= $t['test_date'] ? date("d M Y", strtotime($t['test_date'])) : '—' ?></td>
        <td><?= $t['result_date'] ? date("d M Y", strtotime($t['result_date'])) : 'Pending' ?></td>
        <td><?= htmlspecialchars($t['test_result'] ?? '—') ?></td>
        <td><?= htmlspecialchars($t['test_verdict'] ?? '—') ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
          <a class="back-link" href="labs.php">← Back to Lab Requests</a>

</div>

</body>
</html>