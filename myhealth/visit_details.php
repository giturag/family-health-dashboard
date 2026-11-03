<?php

require_once __DIR__ . '/../app/bootstrap.php';
// visit_details.php
//check if user is logged in
fhd_require_role(['patient', 'dependent']);

if (!isset($_GET['visit_id'])) {
    echo "Visit ID is required.";
    exit();
}

$visit_id = intval($_GET['visit_id']);
$userID = fhd_user_id();

// Get visit information, restricted to the user or their dependents.
$visit_sql = "SELECT hv.*, d.specialty, CONCAT(s.fname, ' ', s.lname) AS patient_name,
                    CONCAT(doc.fname, ' ', doc.lname) AS doctor_name
             FROM health_visits hv
             LEFT JOIN subscribers s ON hv.patientID_FK = s.subID
             LEFT JOIN subscribers doc ON hv.doctorID_FK = doc.subID
             LEFT JOIN doctor_profiles d ON hv.doctorID_FK = d.docID_FK
             WHERE hv.hvisitID = ?
               AND (hv.patientID_FK = ? OR hv.patientID_FK IN (
                   SELECT subID FROM subscribers WHERE guardianID = ?
               ))";
$visit_stmt = $conn->prepare($visit_sql);
$visit_stmt->bind_param("iii", $visit_id, $userID, $userID);
$visit_stmt->execute();
$visit_result = $visit_stmt->get_result();
$visit = $visit_result->fetch_assoc();
$visit_stmt->close();

if (!$visit) {
    fhd_abort(403, 'Forbidden');
}

// Fetch vitals for the visit date
$vitals_sql = "SELECT * FROM vitals 
               WHERE subID_FK = ? AND date_of_reading = ?";
$vitals_stmt = $conn->prepare($vitals_sql);
$vitals_stmt->bind_param("is", $visit['patientID_FK'], $visit['visit_date']);
$vitals_stmt->execute();
$vitals_result = $vitals_stmt->get_result();
$vitals = $vitals_result->fetch_assoc();

// Fetch prescriptions
$pres_sql = "SELECT * FROM prescription_items pi
             JOIN prescriptions p ON pi.presID_FK = p.presID
             WHERE p.hvisitID_FK = ?";
$pres_stmt = $conn->prepare($pres_sql);
$pres_stmt->bind_param("i", $visit_id);
$pres_stmt->execute();
$pres_result = $pres_stmt->get_result();

// Fetch lab tests
$lab_sql = "SELECT lt.*, lti.test_result, lti.test_verdict, cat.test_name
            FROM lab_requests lt
            LEFT JOIN lab_test_items lti ON lt.labtestID = lti.requestID_FK
            LEFT JOIN lab_tests_catalog cat ON lti.testID_FK = cat.testID
            WHERE lt.hvisitID_FK = ?";
$lab_stmt = $conn->prepare($lab_sql);
$lab_stmt->bind_param("i", $visit_id);
$lab_stmt->execute();
$lab_result = $lab_stmt->get_result();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Visit Details</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
       body{
            font-family: 'Segoe UI', sans-serif; 
            background-color: #f9f9f9;
            margin: 0 0;
        }
        .container {
            max-width: 900px;
            margin: auto;
            background-color: #fff;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h2, h3 {
            color: #444;
        }
        section {
            margin-bottom: 2rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.6rem;
            border-bottom: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #0077cc;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .back-button {
            display: inline-block;
            margin-bottom: 1rem;
            padding: 0.4rem 0.8rem;
            background-color: #eee;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
        }
        .back-button:hover {
            background-color: #ddd;
        }

        @media (max-width: 768px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            thead { display: none; }
            td {
                position: relative;
                padding-left: 50%;
            }
            td::before {
                position: absolute;
                top: 0;
                left: 0;
                padding-left: 1rem;
                font-weight: bold;
                content: attr(data-label);
            }
        }
    </style>
</head>
<body>
<div class="container">
    <a href="health_visits.php" class="back-button">← Back to Visits</a>

    <h2>Visit Details</h2>
    <section>
        <p><strong>Patient:</strong> <?= htmlspecialchars($visit['patient_name']) ?></p>
        <p><strong>Doctor:</strong> <?= htmlspecialchars($visit['doctor_name']) ?> (<?= htmlspecialchars($visit['specialty']) ?>)</p>
        <p><strong>Date:</strong> <?= date('d-M-Y', strtotime($visit['visit_date'])) ?></p>
        <p><strong>Diagnosis:</strong> <?= $visit['diagnosis'] ?></p>
        <p><strong>Visit Notes:</strong> <?= $visit['visit_notes'] ?></p>
        <p><strong>Consultation Fee:</strong> <?= $visit['hv_consultation_fee'] ?> KES</p>
        <p><strong>Next Appointment:</strong> <?= $visit['next_appointment'] ?: 'Not scheduled' ?></p>
    </section>

    <section>
        <h3>Vitals</h3>
        <?php if ($vitals): ?>
            <ul>
                <li>BMI: <?= $vitals['user_BMI'] ?></li>
                <li>Weight: <?= $vitals['user_weight'] ?> kg</li>
                <li>Height: <?= $vitals['user_height'] ?> m</li>
                <li>Temperature: <?= $vitals['temperature'] ?> °C</li>
                <li>Blood Pressure: <?= $vitals['blood_pressure_systolic'] ?>/<?= $vitals['blood_pressure_diastolic'] ?></li>
                <li>Heart Rate: <?= $vitals['heart_rate'] ?> bpm</li>
                <li>Blood Glucose: <?= $vitals['blood_glucose'] ?> mg/dL</li>
                <li>Symptoms: <?= htmlspecialchars($vitals['symptoms']) ?></li>
            </ul>
        <?php else: ?>
            <p>No vitals recorded.</p>
        <?php endif; ?>
    </section>

    <section>
        <h3>Prescriptions</h3>
        <?php if ($pres_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr><th>Drug</th><th>Strength</th><th>Dosage</th><th>Frequency</th><th>Duration</th></tr>
                </thead>
                <tbody>
                <?php while ($row = $pres_result->fetch_assoc()): ?>
                    <tr>
                        <td data-label="Drug"><?= htmlspecialchars($row['drug_name']) ?></td>
                        <td data-label="Strength"><?= $row['strength'] ?></td>
                        <td data-label="Dosage"><?= $row['dosage_amt'] ?></td>
                        <td data-label="Frequency"><?= $row['dosage_frequency'] ?></td>
                        <td data-label="Duration"><?= $row['dosage_duration'] ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No prescriptions for this visit.</p>
        <?php endif; ?>
    </section>

    <section>
        <h3>Lab Tests</h3>
        <?php if ($lab_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr><th>Test</th><th>Result</th><th>Verdict</th></tr>
                </thead>
                <tbody>
                <?php while ($lab = $lab_result->fetch_assoc()): ?>
                    <tr>
                        <td data-label="Test"><?= htmlspecialchars($lab['test_name']) ?></td>
                        <td data-label="Result"><?= $lab['test_result'] ?></td>
                        <td data-label="Verdict"><?= $lab['test_verdict'] ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No lab results available.</p>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
