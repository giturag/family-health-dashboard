<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);

$search = $_GET['search'] ?? '';
$period = $_GET['period'] ?? '';
$flag = $_GET['flag'] ?? '';
$person = $_GET['person'] ?? 'self';


$userID =$_SESSION['subID'];
$patient_ids = [$userID];

//fetch dependents 
if ($person === 'dependents') {
    $stmt = $conn->prepare("SELECT subID FROM subscribers WHERE guardianID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $res = $stmt->get_result();
    $patient_ids = [];
    while ($row = $res->fetch_assoc()) {
        $patient_ids[] = (int) $row['subID'];
    }
}

if (count($patient_ids) === 0) {
    $patient_ids = [$userID];
}

$placeholders = implode(',', array_fill(0, count($patient_ids), '?'));
$params = $patient_ids;
$types = str_repeat('i', count($patient_ids));


$sql = "
    SELECT 
        i.lbitemID,
        i.test_date,
        i.result_date,
        i.test_result,
        i.test_verdict,
        c.test_name,
        c.test_type,
        l.labtestID,
        s.fname,
        s.lname
    FROM lab_test_items i
    JOIN lab_tests_catalog c ON i.testID_FK = c.testID
    JOIN lab_requests l ON i.requestID_FK = l.labtestID
    JOIN subscribers s ON l.patientID_FK = s.subID
    WHERE l.patientID_FK IN ($placeholders)
";

if ($search) {
    $sql .= " AND c.test_name LIKE ?";
    $params[] = "%$search%";
    $types .= 's';
}

if ($flag) {
    $sql .= " AND i.test_verdict = ?";
    $params[] = $flag;
    $types .= 's';
}

if ($period) {
    if ($period === 'today') {
        $sql .= " AND DATE(i.test_date) = CURDATE()";
    } elseif ($period === 'week') {
        $sql .= " AND YEARWEEK(i.test_date, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($period === 'month') {
        $sql .= " AND MONTH(i.test_date) = MONTH(CURDATE()) AND YEAR(i.test_date) = YEAR(CURDATE())";
    } elseif ($period === 'year') {
        $sql .= " AND YEAR(i.test_date) = YEAR(CURDATE())";
    }
}

$sql .= " ORDER BY i.result_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$results = $stmt->get_result();



?>

<!DOCTYPE html>
<html>
    <head>
      <title>Labs</title>
      <meta http-equiv="content-type" content="text/html; charset=UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
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
        .intro {
            text-align: center;
        }
        h2 {
            text-align: center;
        }
        form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            max-width: 600px;
            margin: auto;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            font-family: 'Segoe UI', sans-serif;
        }

        .form-row {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .form-row label {
            flex: 0 0 120px;
            margin-right: 1rem;
            font-weight: bold;
        }

        .form-row input,
        .form-row select {
            flex: 1;
            padding: 0.6rem;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .form-row.center {
            justify-content: center;
            margin-top: 1.5rem;
        }

        button {
            background-color: #0078D4;
            color: white;
            padding: 0.7rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }

        button:hover {
            background-color: #005a9e;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        th{
            background-color: #17a2b8;
        }
        th, td { 
            padding: 10px; 
            border: 1px solid #ccc; 
            text-align: left; 
        }
        tr:nth-child(even) {
            background-color: #ccc;
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
            <li><a class="active" href="labs.php">Labs</a></li>
            <li><a href="prescriptions.php">Prescriptions</a></li>
            <li><a href="dependents.php">Dependents</a></li>
            <li><a href="my_bills.php">My Bills</a></li>

        </ul>
        <ul class="navright">
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </header>
    <main class="content">
       <h2>Lab Results</h2>

        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="get">
            <div class="form-row">
                <label for="person">Patient</label>
                <select name="person" id="person">
                    <option value="self" <?= $person === 'self' ? 'selected' : '' ?>>Myself</option>
                    <option value="dependents" <?= $person === 'dependents' ? 'selected' : '' ?>>Dependents</option>
                </select>
            </div>

            <div class="form-row">
                <label for="search">Test Name</label>
                <input type="text" name="search" id="search" placeholder="Test name" value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="form-row">
                <label for="period">Period</label>
                <select name="period" id="period">
                    <option value="">All Time</option>
                    <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
                    <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>This Week</option>
                    <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>This Month</option>
                    <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>This Year</option>
                </select>
            </div>

            <div class="form-row">
                <label for="flag">Result Flag</label>
                <select name="flag" id="flag">
                    <option value="">All Flags</option>
                    <option value="Critical" <?= $flag === 'Critical' ? 'selected' : '' ?>>Critical</option>
                    <option value="High" <?= $flag === 'High' ? 'selected' : '' ?>>High</option>
                    <option value="Normal" <?= $flag === 'Normal' ? 'selected' : '' ?>>Normal</option>
                    <option value="Low" <?= $flag === 'Low' ? 'selected' : '' ?>>Low</option>
                </select>
            </div>

            <div class="form-row center">
                <button type="submit">Filter Results</button>
            </div>
        </form>

        <table>
        <tr>
            <th>Test Date</th>
            <th>Patient</th>
            <th>Test Name</th>
            <th>Result Date</th>
            <th>Result</th>
            <th>Flag</th>
            
        </tr>
        <?php while ($row = $results->fetch_assoc()): ?>
            <tr class="flag-<?= strtolower($row['test_verdict'] ?? 'unknown') ?>">
                <td><?= $row['test_date'] ? htmlspecialchars(date('d-M-Y', strtotime($row['test_date']))) : 'Pending' ?></td>
                <td><?= htmlspecialchars(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')) ?></td>
                <td><?= htmlspecialchars($row['test_name'] ?? 'Unknown Test') ?></td>
                <td><?= $row['result_date'] ? htmlspecialchars(date('d-M-Y', strtotime($row['result_date']))) : 'Pending' ?></td>
                <td><?= htmlspecialchars($row['test_result'] ?? 'Pending') ?></td>
                <td><?= htmlspecialchars($row['test_verdict'] ?? 'Pending') ?></td>
                
            </tr>

        <?php endwhile; ?>
    </table>
        
        
    </main>
    

</body>
</html>