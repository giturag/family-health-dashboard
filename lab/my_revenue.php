<?php
include("../config/db_connect.php");
fhd_require_role('labtech');


$labtechID = $_SESSION['subID'] ?? 0;

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

$sql = "SELECT b.billID, b.status, b.created_at, p.fname, p.lname,
            SUM(bi.amount) AS total_amount
        FROM bill_headers b
        LEFT JOIN bill_items bi ON b.billID = bi.billID_FK
        LEFT JOIN lab_requests lt ON b.hvisitID_FK = lt.hvisitID_FK
        LEFT JOIN subscribers p ON b.patientID_FK = p.subID
        WHERE lt.labtechID_FK = ? $filter
        GROUP BY b.billID
        ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
$params = array_merge([$labtechID], $params);
$types = "i" . $types;
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Calculate total revenue
$totalRevenue = 0;
foreach ($result as $row) {
    $totalRevenue += $row['total_amount'];
}
$result->data_seek(0);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Lab Technician Revenue</title>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        header {
            background-color: #6f42c1;
            margin: 0 0;
            padding: 0;
            width: 100%;
            border: 1px solid #6f42c1;
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

        input[type=text] {
            position: relative;
            width: 72px;
            height: 20px;
            padding-right: 100px;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background-color: #f9f9f9;
        }
        table { 
            width: 80%; 
            border-collapse: collapse; 
            background: #fff;
        }
        th, td { 
            padding: 10px; 
            border: 1px solid #ccc;
        }
        th { 
            background: #6f42c1; 
            color: white; 
        }
        .paid{ color:green; font-weight:bold; }
        .unpaid{ color:red; font-weight:bold; }
    </style>
</head>
<body>
    <header>
        <nav class="menu">
            <ul class="navleft">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="view_requests.php">View Pending Requests</a></li>
                <li><a href="enter_results.php">Enter Results</a></li>
                <li><a href="view_all_requests.php">View All Requests</a></li>
                <li><a class="active" href="my_revenue.php">My Revenue</a></li>            
            </ul>
            <ul class="navright">
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="logout.php">Logout</a></li>              
            </ul>
        </nav>
    </header>
<h2>My Revenue</h2>

<form method="get">
    From: <input type="date" name="from">
    To: <input type="date" name="to">
    <button type="submit">Filter</button>
</form>

<p><b>Total Revenue: Kshs <?= number_format($totalRevenue, 2) ?></b></p>

<table >
<tr>
    <th>Bill ID</th>
    <th>Patient</th>
    <th>Amount (Kshs)</th>
    <th>Status</th>
    <th>Date</th>
</tr>
<?php while ($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= $row['billID'] ?></td>
    <td><?= $row['fname'] . " " . $row['lname'] ?></td>
    <td><?= number_format($row['total_amount'], 2) ?></td>
    <td class="<?= $row['status']==='paid'?'paid':'unpaid' ?>"><?= ucfirst($row['status']) ?></td>
    <td><?= date("d M Y", strtotime($row['created_at'])) ?></td>
</tr>
<?php endwhile; ?>
</table>
</body>
</html>
