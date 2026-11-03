<?php
include("../config/db_connect.php");
fhd_require_role(['doctor', 'specialtydr']);

// assume $_SESSION['doctorID'] is set after login
$doctorID = $_SESSION['subID'] ?? 0;

$filter = "";
$params = [];
$types = "";

// Filtering by period
if (isset($_GET['from']) && isset($_GET['to'])) {
    $filter .= " AND DATE(b.created_at) BETWEEN ? AND ?";
    $params[] = $_GET['from'];
    $params[] = $_GET['to'];
    $types .= "ss";
}

$sql = "SELECT b.billID, b.total_amount, b.status, b.created_at, h.hvisitID, p.fname, p.lname
        FROM bill_headers b
        INNER JOIN health_visits h ON b.hvisitID_FK = h.hvisitID
        INNER JOIN subscribers p ON b.patientID_FK = p.subID
        WHERE h.doctorID_FK = ? $filter
        ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
$params = array_merge([$doctorID], $params);
$types = "i" . $types;
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Calculate total revenue
$totalRevenue = 0;
foreach ($result as $row) {
    $totalRevenue += $row['total_amount'];
}
$result->data_seek(0); // rewind index to 0 for display
?>
<!DOCTYPE html>
<html>
<head>
    <title>Doctor Revenue</title>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
            background: #f4f4f4;
            display: flex;
        }
        .sidebar {
            background-color: #28a745;
            width: 220px;
            height: 100vh;
            color: white;
        }  
        .navside {
            padding: 0;
            margin: 0;
        }  
        .navside li {
            list-style: none;
            display: block;
            margin-bottom: 5px;
        }
        .menu a {
            text-align: left;
            display: block;
            color: white;
            padding: 16px 16px;
            text-decoration: none;
        }
        .menu a:hover {
            text-decoration: underline; 
        }
        .menu a.active {
            background-color: rgba(255,255,255,0.2);
            font-weight: bold;

        }
        main {
            padding: 20px;
            flex-grow: 1;
        }
        .card {
            background: #fff; 
            padding: 15px; 
            margin-bottom: 20px;         
            border-radius: 10px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }    
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        

        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

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
        .actions {
            margin-right: 10px;
            color: #007bff;
            text-decoration: none;
        }

        
    </style>
</head>
<body>
     <div class="sidebar">
         <nav class="menu">
          <ul class="navside">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="appointments.php">Appointments</a></li>
            <li><a href="consultations.php">Consultations</a></li>
            <li><a href="patients.php">My Patients</a></li>
            <li><a href="labs.php">Labs</a></li>
            <li><a class="active" href="my_earnings.php">My Revenue</a></li>
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </div>
    <main>
        <div class="card">
            <h2>My Revenue</h2>
        </div>

        <form method="get">
            From: <input type="date" name="from">
            To: <input type="date" name="to">
            <button type="submit">Filter</button>
        </form>

        <p><b>Total Revenue: Kshs <?= number_format($totalRevenue, 2) ?></b></p>

        <table>
         <thead>
            <tr>
                <th>Bill ID</th>
                <th>Visit ID</th>
                <th>Patient</th>
                <th>Total (Kshs)</th>
                <th>Status</th>
                <th>Date</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= $row['billID'] ?></td>
                <td><?= $row['hvisitID'] ?></td>
                <td><?= $row['fname'] . " " . $row['lname'] ?></td>
                <td><?= number_format($row['total_amount'], 2) ?></td>
                <td class="<?= $row['status']==='paid' ? 'paid' : 'unpaid' ?>">
                    <?= ucfirst($row['status']) ?>
                </td>
                <td><?= date("d M Y", strtotime($row['created_at'])) ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
