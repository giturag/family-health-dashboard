<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);



$subID = fhd_user_id();
$dependents = [];

//fetch dependents
$depstmt = $conn->prepare("SELECT subID, fname, lname FROM subscribers WHERE guardianID = ?");
$depstmt->bind_param("i", $subID);
$depstmt->execute();
$result = $depstmt->get_result();

while ($row = $result->fetch_assoc()) {
    $dependents[] = $row;
}

//handle filters
$selectedUser = $_GET['user'] ?? 'self';
$period = $_GET['period'] ?? '';
$filterID = ($selectedUser === 'self') ? $subID : (int)$selectedUser;
$allowedIDs = array_merge([$subID], array_map('intval', array_column($dependents, 'subID')));
if (!in_array($filterID, $allowedIDs, true)) {
    fhd_abort(403, 'Forbidden');
}
$where = "WHERE v.patientID_FK = ?";
$params = [$filterID];
$types = "i";

//period filters appended to where query string
if ($period === 'week') {
    $where.= " AND v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($period === 'month') {
    $where.= " AND v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
} elseif ($period === 'year') {
    $where.= " AND v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
}

$query = $conn->prepare("SELECT v.hvisitID, v.visit_date, d.fname AS doc_fname, d.lname AS doc_lname, dr.specialty
                        FROM health_visits v
                        JOIN subscribers d ON v.doctorID_FK = d.subID
                        JOIN doctor_profiles dr ON dr.docID_FK = d.subID
                        $where 
                        ORDER BY v.visit_date DESC");
$query->bind_param($types, ...$params);
$query->execute();
$visits = $query->get_result();

?>

<!DOCTYPE html>
<html>
    <head>
      <title>Health Visits</title>
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
        select, button { 
            margin-right: 10px; 
            padding: 5px; 
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
            <li><a class="active" href="health_visits.php">Health Visit</a></li>
            <li><a href="labs.php">Labs</a></li>
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
    <main>
        <section class="intro">
            <h1>My Health Visits</h1>

        </section>
        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="get">
            <label for="user">Patient: </label>
            <select name="user" id="user" onchange="this.form.submit()">
                <option value="self" <?= $selectedUser == 'self' ? 'selected' : '' ?>>Myself</option>
                <?php foreach ($dependents as $dep): ?>
                    <option value="<?= $dep['subID'] ?>" <?= $selectedUser == $dep['subID'] ? 'selected' : '' ?>>">
                        <?= $dep['fname'] . ' ' . $dep['lname'] ?>                    
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Period:</label>
            <select name="period" onchange="this.form.submit()">
                <option value="">All Time</option>
                <option value="week" <?= $period == 'week' ? 'selected' : '' ?>>Past Week</option>
                <option value="month" <?= $period == 'month' ? 'selected' : '' ?>>Past Month</option>
                <option value="year" <?= $period == 'year' ? 'selected' : '' ?>>Past Year</option>
            </select>

        </form>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Doctor</th>
                    <th>Specialty</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($visits->num_rows > 0): ?>
                <?php while ($v = $visits->fetch_assoc()): ?>
                    <tr>
                        <td><?= date("d-M-Y", strtotime($v['visit_date'])) ?></td>
                        <td>Dr. <?= $v['doc_fname'] . ' ' . $v['doc_lname'] ?></td>
                        <td><?= htmlspecialchars($v['specialty'])?></td>
                        <td><a href="visit_details.php?visit_id=<?= $v['hvisitID'] ?>">View Details</a></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="3">No visits found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
                
    </main>
    

</body>
</html>