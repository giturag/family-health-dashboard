<?php

require_once __DIR__ . '/../app/bootstrap.php';
// Ensure doctor is logged in
fhd_require_role(['doctor', 'specialtydr']);

$doctorID = fhd_user_id();
$visitID = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($visitID <= 0) {
    echo "Invalid visit ID.";
    exit;
}

// DB connection

// Fetch consultation details
$stmt = $conn->prepare("
    SELECT v.visit_date, v.visit_notes, p.fname, p.lname, p.gender, p.dob
    FROM health_visits v
    JOIN subscribers p ON v.patientID_FK = p.subID
    WHERE v.hvisitID = ? AND v.doctorID_FK = ?
");
$stmt->bind_param("ii", $visitID, $doctorID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    fhd_abort(403, 'Forbidden');
}

$data = $result->fetch_assoc();

// Fetch lab results
$labStmt = $conn->prepare("
    SELECT tc.test_name, i.test_result, i.test_verdict, i.result_date
    FROM lab_test_items i
    JOIN lab_requests lt ON i.requestID_FK = lt.labtestID
    JOIN lab_tests_catalog tc ON tc.testID = i.testID_FK
    WHERE lt.hvisitID_FK = ?
");
$labStmt->bind_param("i", $visitID);
$labStmt->execute();
$labResults = $labStmt->get_result();

// Fetch prescriptions
$rxStmt = $conn->prepare("
    SELECT pi.drug_name, pi.dosage_amt, pi.dosage_frequency, pi.dosage_duration, p.presID, p.hvisitID_FK
    FROM prescriptions p
    JOIN prescription_items pi ON pi.presID_FK = p.presID 
    WHERE p.hvisitID_FK = ?
");
$rxStmt->bind_param("i", $visitID);
$rxStmt->execute();
$prescriptions = $rxStmt->get_result();

$patientName = $data['fname'] . ' ' . $data['lname'];
$dob = date("d M Y", strtotime($data['dob']));
$age = date_diff(date_create($data['dob']), date_create('today'))->y;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Consultation Details</title>
    <meta charset="UTF-8">
    <style>
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background: #f9f9f9; 
            padding: 20px; 
        }
        .card { 
            background: #fff; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            max-width: 700px; margin: auto; 
        }
        h2 { 
            color: #007bff; 
        }
        .label { 
            font-weight: bold; 
            margin-top: 10px; 
        }
        .back-link { 
            display: inline-block; 
            margin-top: 20px; 
            color: #007bff; 
            text-decoration: none; 
        }
        .back-link:hover { 
            text-decoration: underline; 
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
        }
        th {
            background-color: #007bff;
            color: white;
        }
    </style>
</head>
<body>

<div class="card">
    <h2>Consultation Details</h2>

    <p><span class="label">Patient:</span> <?= htmlspecialchars($patientName) ?> (<?= $age ?> yrs, <?= $data['gender'] ?>)</p>
    <p><span class="label">Date:</span> <?= date("d M Y", strtotime($data['visit_date'])) ?></p>
    <p><span class="label">Notes:</span><br><?= nl2br(htmlspecialchars($data['visit_notes'])) ?></p>

    <?php if ($labResults->num_rows > 0): ?>
        <h3>Lab Results</h3>
        <table>
            <tr>
                <th>Test</th>
                <th>Result</th>
                <th>Verdict</th>
                <th>Date</th>
            </tr>
            <?php while ($lab = $labResults->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($lab['test_name']) ?></td>
                <td><?= htmlspecialchars($lab['test_result']) ?></td>
                <td><?= htmlspecialchars($lab['test_verdict']) ?></td>
                <td><?= $lab['result_date'] ? htmlspecialchars(date("d M Y", strtotime($lab['result_date']))) : 'Pending' ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No lab results linked to this visit.</p>
    <?php endif; ?>

    <?php if ($prescriptions->num_rows > 0): ?>
        <h3>Prescriptions</h3>
        <table>
            <tr>
                <th>Drug</th>
                <th>Dosage</th>
                <th>Frequency</th>
                <th>Duration</th>
            </tr>
            <?php while ($rx = $prescriptions->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($rx['drug_name']) ?></td>
                <td><?= htmlspecialchars($rx['dosage_amt']) ?></td>
                <td><?= htmlspecialchars($rx['dosage_frequency']) ?></td>
                <td><?= htmlspecialchars($rx['dosage_duration']) ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No prescriptions issued during this visit.</p>
    <?php endif; ?>

        <a class="back-link" href="consultations.php">← Back to Consultations</a>
    </div>

</body>
</html>