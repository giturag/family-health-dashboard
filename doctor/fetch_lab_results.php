<?php

require_once __DIR__ . '/../app/bootstrap.php';
// doctor_view_results.php
fhd_require_role(['doctor', 'specialtydr']);

$doctorID = $_SESSION['subID'];

//query to select lab requests and results
$query = "
SELECT DISTINCT
    lt.labtestID,    
    CONCAT(p.fname, ' ', p.lname) AS patient_name,
    hv.visit_date,
    lti.lbitemID,
    tc.test_name,
    tc.test_type,
    lti.test_result,
    lti.test_verdict,
    lti.result_date,
    lts.lab_name,
    CONCAT(labtech.fname, ' ', labtech.lname) AS labtech_name
FROM
    lab_requests lt
JOIN
    subscribers p ON p.subID = lt.patientID_FK
JOIN 
    subscribers labtech ON labtech.subID = lt.labtechID_FK
JOIN
    lab_technicians lts ON lts.labtechID_FK = lt.labtechID_FK
JOIN
    health_visits hv ON hv.hvisitID = lt.hvisitID_FK
JOIN
    lab_test_items lti ON lti.requestID_FK = lt.labtestID
JOIN
    lab_tests_catalog tc ON tc.testID = lti.testID_FK
WHERE
    lt.doctorID_FK = ?
ORDER BY
    lti.result_date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $doctorID);
$stmt->execute();
$results = $stmt->get_result();

//function to format date
function dateFormat($dateString) {
    $date = new DateTime($dateString);
    echo $date->format('d-m-Y');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lab Results</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even){background-color: #f2f2f2;}
    </style>
</head>
<body>
    <h2>Lab Results for Your Patients</h2>
    <table>
        <thead>
            <tr>
                <th>Patient</th>
                <th>Visit Date</th>
                <th>Test Name</th>
                <th>Test Type</th>
                <th>Result</th>
                <th>Verdict</th>
                <th>Result Date</th>
                <th>Lab Name</th>
                <th>Report By</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($results->num_rows > 0): ?>
                <?php while ($row = $results->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['patient_name']) ?></td>
                    <td><?= dateFormat(htmlspecialchars($row['visit_date'])) ?></td>
                    <td><?= htmlspecialchars($row['test_name']) ?></td>
                    <td><?= htmlspecialchars($row['test_type']) ?></td>
                    <td><?= htmlspecialchars($row['test_result']) ?></td>
                    <td><?= htmlspecialchars($row['test_verdict']) ?></td>
                    <td><?= dateFormat(htmlspecialchars($row['result_date'])) ?></td>
                    <td><?= htmlspecialchars($row['lab_name'])?></td>
                    <td>Dr. <?= htmlspecialchars($row['labtech_name']) ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7">No results found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
