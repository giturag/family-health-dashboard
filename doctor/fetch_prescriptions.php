<?php

require_once __DIR__ . '/../app/bootstrap.php';
//echo '<pre>'; print_r($_SESSION); echo '</pre>';


//check if user is doctor
fhd_require_role(['doctor', 'specialtydr']);

$doctorID = $_SESSION['subID'];
//fetch prescritions
$prescriptionsQuery = "
SELECT 
    pr.presID,
    pr.prescribe_date,
    CONCAT(p.fname, ' ', p.lname) AS patient_name,
    hv.visit_date
FROM 
    prescriptions pr
JOIN 
    subscribers p ON p.subID = pr.patientID_FK
JOIN 
    health_visits hv ON hv.hvisitID = pr.hvisitID_FK
WHERE 
    pr.doctorID_FK = ?
ORDER BY 
    pr.prescribe_date DESC
";
//fetch prescription items
$itemsQuery = "
SELECT 
    drug_name, strength, dosage_amt, dosage_frequency, dosage_duration
FROM 
    prescription_items
WHERE 
    presID_FK = ?
";

$stmtPres = $conn->prepare($prescriptionsQuery);
$stmtPres->bind_param("i", $doctorID);
$stmtPres->execute();
$presResults = $stmtPres->get_result();


function dateFormat($dateString) {
    $date = new DateTime($dateString);
    return $date->format('d-M-Y');
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Prescriptions History</title>
    <style>
        body { 
            font-family: 'Segoe UI'; 
            background: #f4f4f4;            
        }
        h2 { 
            color: #007bff; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
            background: #fff; 
        }
        th, td { 
            border: 1px solid #ddd; 
            padding: 8px; 
        }
        th { 
            background-color: #007bff; 
            color: white; 
        }
        tr:nth-child(even) { 
            background-color: #f9f9f9; 
        }
        .pres-header { 
            background-color: #e0f7fa; 
            font-weight: bold; 
        }
    </style>
</head>
<body>
   <h2>Prescriptions HIstory Grouped by Visit</h2>
    <table>
        <thead>
            <tr>
                <th>Drug Name</th>
                <th>Strength</th>
                <th>Dosage</th>
                <th>Frequency</th>
                <th>Duration</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($pres = $presResults->fetch_assoc()): ?>
                <tr class="pres-header">
                    <td colspan="5">
                        Patient: <?= htmlspecialchars($pres['patient_name']) ?> |
                        Visit Date: <?= dateFormat($pres['visit_date']) ?> |
                        Prescribed On: <?= dateFormat($pres['prescribe_date']) ?>
                    </td>
                </tr>
                <?php
                    $stmtItems = $conn->prepare($itemsQuery);
                    $stmtItems->bind_param("i", $pres['presID']);
                    $stmtItems->execute();
                    $itemResults = $stmtItems->get_result();
                ?>
                <?php while ($item = $itemResults->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['drug_name']) ?></td>
                        <td><?= htmlspecialchars($item['strength']) ?></td>
                        <td><?= htmlspecialchars($item['dosage_amt']) ?></td>
                        <td><?= htmlspecialchars($item['dosage_frequency']) ?></td>
                        <td><?= htmlspecialchars($item['dosage_duration']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php endwhile; ?>
        </tbody>
    </table>

</body>
</html>
