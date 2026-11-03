<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);



$userID =$_SESSION['subID'];
$dependents = [];

//fetch dependents 
$depstmt = $conn->prepare("SELECT subID, fname, lname FROM subscribers WHERE guardianID = ?");
$depstmt->bind_param("i", $userID);
$depstmt->execute();
$result = $depstmt->get_result();

while ($row = $result->fetch_assoc()) {
    $dependents[] = $row;
}

// Fetch doctors
$docstmt = $conn->prepare("
  SELECT d.doctorID, s.fname, s.lname, d.specialty, d.hospital
  FROM doctor_profiles d
  JOIN subscribers s ON d.docID_FK = s.subID
  ORDER BY s.fname, s.lname
");
$docstmt->execute();
$docresult = $docstmt->get_result();
$doctors = [];
while ($row = $docresult->fetch_assoc()) {
    $doctors[] = $row;
}

// Filters
$allowedIDs = array_merge([$userID], array_map('intval', array_column($dependents, 'subID')));
$selectedID = intval($_GET['subID'] ?? $userID);
if (!in_array($selectedID, $allowedIDs, true)) {
    fhd_abort(403, 'Forbidden');
}

$filters = ["p.patientID_FK = ?"];
$params = [$selectedID];
$types = "i";

if (!empty($_GET['doctorID'])) {
    $filters[] = "p.doctorID_FK = ?";
    $params[] = intval($_GET['doctorID']);
    $types .= "i";
}

if (!empty($_GET['period']) && $_GET['period'] !== 'all') {
    switch ($_GET['period']) {
        case 'week':
            $filters[] = "p.prescribe_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $filters[] = "p.prescribe_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            break;
        case 'year':
            $filters[] = "p.prescribe_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            break;
    }
}

$whereClause = implode(" AND ", $filters);

$sql = "
  SELECT 
    p.presID, p.prescribe_date, p.doctorID_FK,
    pi.drug_name, pi.strength, pi.dosage_amt, pi.dosage_frequency, pi.dosage_duration
  FROM prescriptions p
  LEFT JOIN prescription_items pi ON pi.presID_FK = p.presID
  WHERE $whereClause
  ORDER BY p.prescribe_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$results = $stmt->get_result();

// Group prescriptions by prescription id
$prescriptions = [];
while ($row = $results->fetch_assoc()) {
    $presID = $row['presID'];
    if (!isset($prescriptions[$presID])) {
        $prescriptions[$presID] = [
            'prescribe_date' => $row['prescribe_date'],
            'doctorID_FK' => $row['doctorID_FK'],
            'items' => []
        ];
    }

    if (!empty($row['drug_name'])) {
        $prescriptions[$presID]['items'][] = [
            'drug_name' => $row['drug_name'],
            'strength' => $row['strength'],
            'dosage_amt' => $row['dosage_amt'],
            'dosage_frequency' => $row['dosage_frequency'],
            'dosage_duration' => $row['dosage_duration']
        ];
    }
}

// Summary
$totalPrescriptions = count($prescriptions); //get total prescriptions
$drugCounts = []; //counts of prescriptions
$latestDate = null; //to get lastest prescription date

//loop through prescriptions to get latest date
foreach ($prescriptions as $pres) {
    if ($latestDate === null || $pres['prescribe_date'] > $latestDate) {
        $latestDate = $pres['prescribe_date'];
    }
    //loop through prescription items to get counts for each
    foreach ($pres['items'] as $item) {
        $drug = $item['drug_name'];
        if (!empty($drug)) {
            $drugCounts[$drug] = ($drugCounts[$drug] ?? 0) + 1;
        }
    }
}

arsort($drugCounts); //sort in descending order
$mostPrescribed = key($drugCounts); //get the key of the sorted assoc array e.g. "benadryl" => 10
$selectedName = "Me";
if ($_GET['subID'] ?? '' && $_GET['subID'] != $_SESSION['subID']) {
    foreach ($dependents as $dep) {
        if ($dep['subID'] == $_GET['subID']) {
            $selectedName = htmlspecialchars($dep['fname'] . ' ' . $dep['lname']);
            break;
        }
    }
}




?>

<!DOCTYPE html>
<html>
    <head>
      <title>Prescriptions</title>
      <meta http-equiv="content-type" content="text/html; charset=UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{
            font-family: 'Segoe UI', sans-serif; 
            background-color: #f9f9f9;
            margin: 0 0;
        }
        header {
            background-color: #17a2b8;
            margin: 0 0;
            padding: 0;
            width: 100%;
            border: 1px solid #17a2b8;
            color: white;
        }
        .menu {
            display: flex;
        }
        .navleft {
            display: flex;
            text-decoration: none;
            list-style-type: none;
            justify-content: flex-start;
            flex-grow: 1;
        }
        .navright {
            display: flex;
            justify-content: flex-end;
            text-decoration: none;
            list-style-type: none;
        }
        .menu li {
            float: left;
            text-decoration: none;
        }
        .menu a {
            text-align: center;
            display: inline-block;
            color: white;
            padding: 16px 16px;
            text-decoration: none;
        }
        .menu a:hover {
            text-decoration: underline; 
        }
        .menu a.active {
            background-color: white;
            color: #17a2b8;
        }

        body{
            font-family: 'Segoe UI', sans-serif; 
            background-color: #f9f9f9;
        }
        .intro {
            text-align: center;
        }
        form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            max-width:600px;
            margin: auto;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        label {
            display: block;
            margin-top: 1rem;
        }
        input, select {
            width: 100%;
            padding: 0.6rem;
            margin-top: 0.3rem;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        button {
            cursor: pointer;
            margin-top: 0.6rem;
        }
        details {
            background: #fff;
            padding: 1rem;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            margin-top: 1em;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.5rem;
            border: 1px solid #ccc;
        }
    </style>



    <script>
    

    </script>

</head>

<body>
    <header>
      <nav class="menu">
        <ul class="navleft">
            <li><a href="myhome.php">Home</a></li>
            <li><a href="allergies_and_conditions.php">Allergies and Conditions</a></li>
            <li><a href="vitals.php">Vitals</a></li>
            <li><a href="appointments.php">Appointments</a></li>
            <li><a href="health_visits.php">Health Visit</a></li>
            <li><a href="labs.php">Labs</a></li>
            <li><a class="active" href="prescriptions.php">Prescriptions</a></li>
            <li><a href="dependents.php">Dependents</a></li>
            <li><a href="my_bills.php">My Bills</a></li>

        </ul>
        <ul class="navright">
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </header>
    <main>
        <section class="intro">
            <h1>My Prescriptions</h1>

        </section>
         <form method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
            <label for="subID">Patient:</label>
            <select name="subID">
                <option value="<?= $userID ?>" <?= ($_GET['subID'] ?? '') == $userID ? 'selected' : '' ?>>Me</option>
                <?php foreach ($dependents as $dep): ?>
                <option value="<?= $dep['subID'] ?>" <?= ($_GET['subID'] ?? '') == $dep['subID'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dep['fname'] . ' ' . $dep['lname']) ?>
                </option>
                <?php endforeach; ?>
            </select>

            <label for="doctorID">Doctor:</label>
            <select name="doctorID">
            <option value="">All Doctors</option>
            <?php foreach ($doctors as $doc): ?>
                <option value="<?= $doc['doctorID'] ?>" <?= ($_GET['doctorID'] ?? '') == $doc['doctorID'] ? 'selected' : '' ?>>
                Dr. <?= htmlspecialchars($doc['fname'] . ' ' . $doc['lname']) ?> (<?= htmlspecialchars($doc['specialty']) ?>)
                </option>
            <?php endforeach; ?>
            </select>

            <label for="period">Period:</label>
            <select name="period">
                <option value="all" <?= ($_GET['period'] ?? '') == 'all' ? 'selected' : '' ?>>All</option>
                <option value="week" <?= ($_GET['period'] ?? '') == 'week' ? 'selected' : '' ?>>Past Week</option>
                <option value="month" <?= ($_GET['period'] ?? '') == 'month' ? 'selected' : '' ?>>Past Month</option>
                <option value="year" <?= ($_GET['period'] ?? '') == 'year' ? 'selected' : '' ?>>Past Year</option>
            </select>

            <button type="submit">Apply Filters</button>
            </form>

            <section style="max-width: 600px; margin: 2rem auto; background: #fff; padding: 1rem; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.1);">
            <h3>Viewing prescriptions for: <strong><?= $selectedName ?></strong></h3>
            <p>Total prescriptions: <strong><?= $totalPrescriptions ?></strong></p>
            <p>Most prescribed drug: <strong><?= $mostPrescribed ?: 'N/A' ?></strong></p>
            <p>Last prescription date: <strong><?= $latestDate ? date('d-M-Y', strtotime($latestDate)) : 'N/A' ?></strong></p>
            </section>

            <?php if ($totalPrescriptions == 0): ?>
            <p style="text-align:center; margin-top:2rem;">No prescriptions found for the selected filters.</p>
            <?php else: ?>
            <section style="max-width: 800px; margin: 2rem auto;">
                <?php foreach ($prescriptions as $presID => $pres): ?>
                <details style="margin-bottom: 1em;">
                    <summary>
                     <?= htmlspecialchars(date('d-M-Y', strtotime($pres['prescribe_date']))) ?> — Prescription #<?= $presID ?>
                    </summary>

                    <table>
                    <tr>
                        <th>Drug Name</th>
                        <th>Strength</th>
                        <th>Amount</th>
                        <th>Frequency</th>
                        <th>Duration</th>
                    </tr>
                    <?php foreach ($pres['items'] as $item): ?>
                        <tr>
                        <td><?= htmlspecialchars($item['drug_name']) ?></td>
                        <td><?= htmlspecialchars($item['strength']) ?></td>
                        <td><?= htmlspecialchars($item['dosage_amt']) ?></td>
                        <td><?= htmlspecialchars($item['dosage_frequency']) ?></td>
                        <td><?= htmlspecialchars($item['dosage_duration']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </table>
                </details>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>
        
    </main>
    

</body>
</html>