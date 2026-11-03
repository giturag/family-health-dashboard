<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['doctor', 'specialtydr']);

$patientID = intval($_GET['id'] ?? 0);

if (!fhd_doctor_can_access_patient($conn, $patientID)) {
    fhd_abort(403, 'Forbidden');
}

// Patient info
$infoStmt = $conn->prepare(
    'SELECT fname, lname, gender, dob, nok_name, nok_phone_no
     FROM subscribers WHERE subID = ? LIMIT 1'
);
$infoStmt->bind_param('i', $patientID);
$infoStmt->execute();
$info = $infoStmt->get_result()->fetch_assoc();
$infoStmt->close();

if (!$info) {
    fhd_abort(404, 'Patient not found.');
}

$age = date_diff(date_create($info['dob']), date_create('today'))->y;

// Last visit
$lastVisitStmt = $conn->prepare(
    'SELECT visit_date FROM health_visits WHERE patientID_FK = ? ORDER BY visit_date DESC LIMIT 1'
);
$lastVisitStmt->bind_param('i', $patientID);
$lastVisitStmt->execute();
$lastVisit = $lastVisitStmt->get_result()->fetch_assoc()['visit_date'] ?? 'N/A';
$lastVisitStmt->close();

// Allergies
$allergiesStmt = $conn->prepare(
    'SELECT allergen, severity FROM allergy_records WHERE patientID_FK = ?'
);
$allergiesStmt->bind_param('i', $patientID);
$allergiesStmt->execute();
$allergies = $allergiesStmt->get_result();

// Conditions
$conditionsStmt = $conn->prepare(
    'SELECT condition_name, status FROM chronic_condition_records WHERE patientID_FK = ?'
);
$conditionsStmt->bind_param('i', $patientID);
$conditionsStmt->execute();
$conditions = $conditionsStmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Patient Profile</title>
  <meta charset="UTF-8">
  <style>
    body { font-family: 'Segoe UI', sans-serif; padding: 20px; background: #f9f9f9; }
    .card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 700px; margin: auto; }
    h2 { color: #28a745; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #ccc; padding: 8px; }
    th { background-color: #28a745; color: white; }
  </style>
</head>
<body>

<div class="card">
  <h2>Patient Profile</h2>
  <p><strong>Name:</strong> <?= htmlspecialchars($info['fname'] . ' ' . $info['lname']) ?></p>
  <p><strong>Gender:</strong> <?= htmlspecialchars($info['gender']) ?></p>
  <p><strong>Age:</strong> <?= $age ?></p>
  <p><strong>Next of Kin :</strong> <?= htmlspecialchars($info['nok_name']) ?></p>
  <p><strong>Next of Kin Contact: <?= htmlspecialchars($info['nok_phone_no']) ?></strong></p>
  <p><strong>Last Visit:</strong> <?= $lastVisit !== 'N/A' ? date("d M Y", strtotime($lastVisit)) : 'N/A' ?></p>

  <h3>Allergies</h3>
  <?php if ($allergies->num_rows > 0): ?>
    <table>
      <tr><th>Allergen</th><th>Severity</th></tr>
      <?php while ($a = $allergies->fetch_assoc()): ?>
        <tr><td><?= htmlspecialchars($a['allergen']) ?></td><td><?= htmlspecialchars($a['severity']) ?></td></tr>
      <?php endwhile; ?>
    </table>
  <?php else: ?>
    <p>No allergies recorded.</p>
  <?php endif; ?>

  <h3>Chronic Conditions</h3>
  <?php if ($conditions->num_rows > 0): ?>
    <table>
      <tr><th>Condition</th><th>Status</th></tr>
      <?php while ($c = $conditions->fetch_assoc()): ?>
        <tr><td><?= htmlspecialchars($c['condition_name']) ?></td><td><?= htmlspecialchars($c['status']) ?></td></tr>
      <?php endwhile; ?>
    </table>
  <?php else: ?>
    <p>No chronic conditions recorded.</p>
  <?php endif; ?>
    <a class="back-link" href="patients.php">← Back to My Patients</a>

</div>

</body>
</html>