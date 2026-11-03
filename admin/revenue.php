<?php
include("../config/db_connect.php");

// Only admins can access
if ($_SESSION['sub_role'] !== 'admin') {
    die("Access denied.");
}


// Filters
$filters = "";
$params = [];
$types = "";

// Date range filter
if (!empty($_GET['from']) && !empty($_GET['to'])) {
    $filters .= " AND DATE(b.created_at) BETWEEN ? AND ?";
    $params[] = $_GET['from'];
    $params[] = $_GET['to'];
    $types .= "ss";
}

// Status filter
if (!empty($_GET['status'])) {
    $filters .= " AND b.status = ?";
    $params[] = $_GET['status'];
    $types .= "s";
}

// Doctor filter
if (!empty($_GET['doctorID'])) {
    $filters .= " AND h.doctorID_FK = ?";
    $params[] = $_GET['doctorID'];
    $types .= "i";
}

// Labtech filter
if (!empty($_GET['labtechID'])) {
    $filters .= " AND lt.labtechID_FK = ?";
    $params[] = $_GET['labtechID'];
    $types .= "i";
}

// Patient filter
if (!empty($_GET['patientID'])) {
    $filters .= " AND p.subID = ?";
    $params[] = $_GET['patientID'];
    $types .= "i";
}

/* ================================
   Fetch Visit Bills
   ================================ */
$sql_visits = "SELECT b.billID, b.total_amount, b.status, b.created_at,
                      h.hvisitID, d.fname AS docfname, d.lname AS doclname,
                      p.fname AS pfname, p.lname AS plname
               FROM bill_headers b
               INNER JOIN health_visits h ON b.hvisitID_FK = h.hvisitID
               INNER JOIN subscribers p ON b.patientID_FK = p.subID
               LEFT JOIN subscribers d ON h.doctorID_FK = d.subID
               LEFT JOIN lab_requests lt ON lt.hvisitID_FK = h.hvisitID
               WHERE b.description = 'Consultation' $filters
               ORDER BY b.created_at DESC";



$stmt = $conn->prepare($sql_visits);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res_visits = $stmt->get_result();

$visits_total = 0;
$visit_rows = [];
while ($row = $res_visits->fetch_assoc()) {
    $visits_total += $row['total_amount'];
    $visit_rows[] = $row;
}

/* ================================
   Fetch Lab Bills
   ================================ */
$sql_labs = "SELECT b.billID, b.total_amount, b.status, b.created_at,
                    h.hvisitID, d.fname AS docfname, d.lname AS doclname,
                    p.fname AS pfname, p.lname AS plname
             FROM bill_headers b
             INNER JOIN health_visits h ON b.hvisitID_FK = h.hvisitID
             INNER JOIN subscribers p ON b.patientID_FK = p.subID
             LEFT JOIN subscribers d ON h.doctorID_FK = d.subID
             LEFT JOIN lab_requests lt ON lt.hvisitID_FK = h.hvisitID
             WHERE b.description = 'Lab Test' $filters
             ORDER BY b.created_at DESC";

$stmt2 = $conn->prepare($sql_labs);
if (!empty($params)) {
    $stmt2->bind_param($types, ...$params);
}
$stmt2->execute();
$res_labs = $stmt2->get_result();

$labs_total = 0;
$lab_rows = [];
while ($row = $res_labs->fetch_assoc()) {
    $labs_total += $row['total_amount'];
    $lab_rows[] = $row;
}

$total_revenue = $visits_total + $labs_total;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Revenue</title>

    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            background-color: #f4f4f4;
        }

        .sidebar {
            width: 220px;
            background-color: #007bff;
            color: white;
            height: 100vh;
            position: fixed;
            padding-top: 30px;
        }

        .sidebar h2 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 22px;
        }

        .sidebar a {
            display: block;
            color: white;
            padding: 12px 20px;
            text-decoration: none;
            transition: background 0.3s;
        }

        .sidebar a:hover {
            background-color: #0056b3;
        }
        .sidebar a.active {
            background-color: #0056b3;
            font-weight: bold;
            border-left: 4px solid #fff;
        }

        .main-content {
            margin-left: 220px;
            padding: 30px;
            flex-grow: 1;
        }        
        h2 { margin-bottom: 10px; }
        .summary { display: flex; gap: 20px; margin-bottom: 20px; }
        .card {
            flex: 1;
            background: #f9f9f9;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        .card h3 { margin: 0; font-size: 18px; color: #444; }
        .card p { font-size: 20px; font-weight: bold; color: #2c7; }
        table { border-collapse: collapse; width: 100%; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        thead tr {
            background-color: #007bff;
            color: white;
        }

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


        .paid{ color:green; font-weight:bold; }
        .unpaid{ color:red; font-weight:bold; }
        form { margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="sidebar">
    <h2>Admin Panel</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="user_stats.php">User Stats</a>
    <a href="consultations.php">Consultations</a>
    <a href="lab_requests.php">Lab Requests</a>
    <a class="active" href="revenue.php">Revenue</a>
    <a href="view_messages.php">Messages</a>
    <a href="logout.php">Logout</a>
    </div>

    <div class="main-content">
        <h2>Revenues</h2>

        <form method="get">
            From: <input type="date" name="from" value="<?= $_GET['from'] ?? '' ?>">
            To: <input type="date" name="to" value="<?= $_GET['to'] ?? '' ?>">
            Status: 
            <select name="status">
                <option value="">All</option>
                <option value="unpaid" <?= (($_GET['status'] ?? '')=="unpaid")?"selected":"" ?>>Unpaid</option>
                <option value="paid" <?= (($_GET['status'] ?? '')=="paid")?"selected":"" ?>>Paid</option>
            </select>
            Doctor ID: <input type="number" name="doctorID" value="<?= $_GET['doctorID'] ?? '' ?>">
            Labtech ID: <input type="number" name="labtechID" value="<?= $_GET['labtechID'] ?? '' ?>">
            Patient ID: <input type="number" name="patientID" value="<?= $_GET['patientID'] ?? '' ?>">
            <button type="submit">Filter</button>
        </form>

        <div class="summary">
            <div class="card">
                <h3>Total Revenue</h3>
                <p>Kshs <?= number_format($total_revenue, 2) ?></p>
            </div>
            <div class="card">
                <h3>Visits Revenue</h3>
                <p>Kshs <?= number_format($visits_total, 2) ?></p>
            </div>
            <div class="card">
                <h3>Lab Revenue</h3>
                <p>Kshs <?= number_format($labs_total, 2) ?></p>
            </div>
        </div>

        <h3>Visit Bills</h3>
        <table>
        <tr>
            <th>Bill ID</th>
            <th>Visit ID</th>
            <th>Patient</th>
            <th>Doctor</th>
            <th>Total (Kshs)</th>
            <th>Status</th>
            <th>Date</th>
        </tr>
        <?php foreach ($visit_rows as $row): ?>
        <tr>
            <td><?= $row['billID'] ?></td>
            <td><?= $row['hvisitID'] ?></td>
            <td><?= $row['pfname'] . " " . $row['plname'] ?></td>
            <td><?= $row['docfname'] . " " . $row['doclname'] ?></td>
            <td><?= number_format($row['total_amount'], 2) ?></td>
            <td class="<?= $row['status']==='paid' ? 'paid' : 'unpaid' ?>"><?= ucfirst($row['status']) ?></td>
            <td><?= date("d M Y", strtotime($row['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </table>

        <h3>Lab Bills</h3>
        <table>
        <tr>
            <th>Bill ID</th>
            <th>Visit ID</th>
            <th>Patient</th>
            <th>Doctor</th>
            <th>Total (Kshs)</th>
            <th>Status</th>
            <th>Date</th>
        </tr>
        <?php foreach ($lab_rows as $row): ?>
        <tr>
            <td><?= $row['billID'] ?></td>
            <td><?= $row['hvisitID'] ?></td>
            <td><?= $row['pfname'] . " " . $row['plname'] ?></td>
            <td><?= $row['docfname'] . " " . $row['doclname'] ?></td>
            <td><?= number_format($row['total_amount'], 2) ?></td>
            <td class="<?= $row['status']==='paid' ? 'paid' : 'unpaid' ?>"><?= ucfirst($row['status']) ?></td>
            <td><?= date("d M Y", strtotime($row['created_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
