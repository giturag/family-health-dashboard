<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['doctor', 'specialtydr']);

// Filters
$periods = ['all' => 'All time', 'week' => 'This week', 'month' => 'This month', 'year' => 'This year'];
$types = ['all' => 'All', 'consultation' => 'Consultations', 'lab' => 'Lab Tests', 'prescription' => 'Prescriptions'];

$period = $_GET['period'] ?? 'all';
$type = $_GET['type'] ?? 'all';
$patientID = intval($_GET['patient'] ?? 0);

if ($patientID <= 0) {
    fhd_abort(400, 'Patient ID is required.');
}

if (!fhd_doctor_can_access_patient($conn, $patientID)) {
    fhd_abort(403, 'Forbidden');
}

// Base condition
$where = "WHERE s.subID = ?";
$params = [$patientID];
$typestr = "i";

// Date filter
switch ($period) {
    case 'week': $where .= " AND v.visit_date >= CURDATE() - INTERVAL 7 DAY"; break;
    case 'month': $where .= " AND v.visit_date >= CURDATE() - INTERVAL 1 MONTH"; break;
    case 'year': $where .= " AND v.visit_date >= CURDATE() - INTERVAL 1 YEAR"; break;
}

// Fetch patient name
$pstmt = $conn->prepare("SELECT fname, lname FROM subscribers WHERE subID = ?");
$pstmt->bind_param("i", $patientID);
$pstmt->execute();
$pstmt->bind_result($fname, $lname);
$pstmt->fetch();
$pstmt->close();

$fullName = htmlspecialchars("$fname $lname");

// Consultations
$consultations = [];
if ($type === 'all' || $type === 'consultation') {
    $stmt = $conn->prepare("SELECT v.visit_date, c.notes 
        FROM consultations c 
        JOIN visits v ON c.visitID = v.visitID 
        JOIN subscribers s ON v.subID_FK = s.subID 
        $where ORDER BY v.visit_date DESC");
    $stmt->bind_param($typestr, ...$params);
    $stmt->execute();
    $consultations = $stmt->get_result();
}

// Labs
$labs = [];
if ($type === 'all' || $type === 'lab') {
    $stmt = $conn->prepare("SELECT requestID_FK, request_date, test_type, result, result_flag 
        FROM lab_test_items 
        WHERE subID_FK = ? ORDER BY requestID_FK DESC, request_date DESC");
    $stmt->bind_param("i", $patientID);
    $stmt->execute();
    $labs = $stmt->get_result();
}

// Prescriptions
$prescriptions = [];
if ($type === 'all' || $type === 'prescription') {
    $stmt = $conn->prepare("SELECT prescribed_date, medication_name, dosage 
        FROM prescriptions 
        WHERE subID_FK = ? ORDER BY prescribed_date DESC");
    $stmt->bind_param("i", $patientID);
    $stmt->execute();
    $prescriptions = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $fullName ?> - Medical History</title>
    <style>
        body { 
            font-family: Arial; 
            margin: 0; 
            display: flex; 
        }
        .sidebar {
            width: 220px;
            background: #2f4f4f;
            color: white;
            padding: 20px;
            height: 100vh;
        }
        .sidebar a {
            color: white;
            display: block;
            margin-bottom: 10px;
            text-decoration: none;
        }
        .content {
            padding: 20px;
            flex-grow: 1;
        }
        h2 { margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 8px; border: 1px solid #ccc; }
        th { background: #f5f5f5; }
        tbody tr:nth-child(even) td {
            background-color: #f1f7ff; /* soft blue */
        }

        tbody tr:nth-child(odd) td {
            background-color: #ffffff; /* pure white */
        }

        tbody tr:hover td {
            background-color: #e0ecff; /* highlight on hover */
            cursor: pointer;
        }
        .filter { margin-bottom: 20px; }
        .group-title { background: #ddd; font-weight: bold; padding: 8px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h3>Doctor Panel</h3>
        <a href="dashboard.php">Dashboard</a>
        <a href="consultations.php">My Consultations</a>
        <a href="patients.php">Patient List</a>
        <a href="logout.php">Logout</a>
    </div>
    <div class="content">
        <h1>Medical History: <?= $fullName ?></h1>

        <form method="get" class="filter">
            <input type="hidden" name="patient" value="<?= $patientID ?>">
            <label>Period:
                <select name="period">
                    <?php foreach ($periods as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $key === $period ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Type:
                <select name="type">
                    <?php foreach ($types as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $key === $type ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">Apply</button>
        </form>

        <?php if ($consultations): ?>
            <h2>Consultations</h2>
            <table>
                <tr><th>Date</th><th>Notes</th></tr>
                <?php while($row = $consultations->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('d-M-Y', strtotime($row['visit_date'])) ?></td>
                        <td><?= nl2br(htmlspecialchars($row['notes'])) ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php endif; ?>

        <?php if ($labs): ?>
            <h2>Lab Results</h2>
            <?php
            $groupedLabs = [];
            while ($row = $labs->fetch_assoc()) {
                $groupedLabs[$row['requestID_FK']][] = $row;
            }
            ?>
            <?php foreach ($groupedLabs as $requestID => $tests): ?>
                <div class="group-title">Request ID: <?= $requestID ?></div>
                <table>
                    <tr><th>Date</th><th>Test</th><th>Result</th><th>Flag</th></tr>
                    <?php foreach ($tests as $test): ?>
                        <tr>
                            <td><?= date('d-M-Y', strtotime($test['request_date'])) ?></td>
                            <td><?= htmlspecialchars($test['test_type']) ?></td>
                            <td><?= htmlspecialchars($test['result']) ?></td>
                            <td><?= htmlspecialchars($test['result_flag']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($prescriptions): ?>
            <h2>Prescriptions</h2>
            <table>
                <tr><th>Date</th><th>Medication</th><th>Dosage</th></tr>
                <?php while($row = $prescriptions->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('d-M-Y', strtotime($row['prescribed_date'])) ?></td>
                        <td><?= htmlspecialchars($row['medication_name']) ?></td>
                        <td><?= htmlspecialchars($row['dosage']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
