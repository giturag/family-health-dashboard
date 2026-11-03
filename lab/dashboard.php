<?php

require_once __DIR__ . '/../app/bootstrap.php';
//check if user is logged in
fhd_require_role('labtech');
$labtechid = $_SESSION['subID'];
$labtechname = $_SESSION['subfname'];


//count requests for patients in the same region and group by status
$query = "
    SELECT
        SUM(CASE WHEN i.test_date IS NULL THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN i.test_date IS NOT NULL AND i.result_date IS NULL THEN 1 ELSE 0 END) AS collected,
        SUM(CASE WHEN i.result_date IS NOT NULL THEN 1 ELSE 0 END) AS completed
    FROM lab_test_items i
    JOIN lab_requests t ON i.requestID_FK = t.labtestID
    JOIN subscribers p ON t.patientID_FK = p.subID
    JOIN subscribers l ON l.subID = ?
    WHERE p.region = l.region
    GROUP BY t.labtestID
    ";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $labtechid);
$stmt->execute();
$result = $stmt->get_result();

//fetch the pending, collected and completed values from the query result
$pending = $collected =$completed = 0;
while($row = $result->fetch_assoc()) {
    $pending += $row['pending'];
    $collected += $row['collected'];
    $completed += $row['completed'];
}


//Recent Activity
$recentQuery = "
SELECT tc.test_name, i.test_date, i.result_date
FROM lab_test_items i
JOIN lab_tests_catalog tc ON tc.testID = i.testID_FK
JOIN lab_requests t ON t.labtestID = i.requestID_FK
WHERE t.labtechID_FK = ?
ORDER BY i.result_date DESC
LIMIT 5";
$recentStmt = $conn->prepare($recentQuery);
$recentStmt->bind_param("i", $labtechid);
$recentStmt->execute();
$recentResults = $recentStmt->get_result();

//Fetch lab revenue details
$revenueQuery = "
SELECT 
    b.total_amount,
    b.status
FROM bill_headers b
JOIN lab_requests lt ON lt.labtestID = b.lab_testID_FK
WHERE lt.labtechID_FK = ?
";

$stmt = $conn->prepare($revenueQuery);
$stmt->bind_param("i", $labtechid);
$stmt->execute();
$result = $stmt->get_result();

$total = $paid = $partial = $unpaid = 0;

while ($row = $result->fetch_assoc()) {
    $total += $row['total_amount'];
    if ($row['status'] === 'paid') {
        $paid += $row['total_amount'];
    } elseif ($row['status'] === 'partially_paid') {
        $partial += $row['total_amount'];
    } elseif ($row['status'] === 'unpaid') {
        $unpaid += $row['total_amount'];
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Lab Technician Dashboard</title>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            background-color: #f9f9f9;
        }
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
        /* Section headers */
        .section {
            margin: 40px 20px;
            padding: 20px;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .section h2 {
            margin-top: 0;
            color: #6f42c1;
            font-size: 22px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }

        /* Dashboard cards */
        .dashboard-cards {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 20px 0;
        }

        .card {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            padding: 20px;
            flex: 1;
            min-width: 200px;
            text-align: center;
            transition: transform 0.2s ease;
        }

        .card:hover {
            transform: translateY(-4px);
        }

        .card h3 {
            color: #6f42c1;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .card p {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            color: #333;
        }

        .card a {
            display: inline-block;
            margin-top: 10px;
            color: #6f42c1;
            text-decoration: none;
            font-size: 14px;
        }

        .card a:hover {
            text-decoration: underline;
        }

        /* Table styling */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th, table td {
            padding: 12px 16px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        table th {
            background-color: #f2f2f2;
            color: #6f42c1;
            font-weight: 600;
        }

        table tr:hover {
            background-color: #fafafa;
        }

        
    </style>

    <script>


    </script>

</head>

<body>
    <header>
        <nav class="menu">
            <ul class="navleft">
                <li><a class="active" href="dashboard.php">Dashboard</a></li>
                <li><a href="view_requests.php">View Pending Requests</a></li>
                <li><a href="enter_results.php">Enter Results</a></li>
                <li><a href="view_all_requests.php">View All Requests</a></li>
                <li><a href="my_revenue.php">My Revenue</a></li>            


            </ul>
            <ul class="navright">
                <li><a href="profile.php">My Profile</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <h1>Welcome, <?= $labtechname; ?></h1>
        <div class="dashboard-cards">
            <div class="card">
                <h3>Pending Samples</h3>
                <p><?= $pending ?></p>
                <a href="view_requests.php">View</a>
            </div>
            <div class="card">
                <h3>Collected Samples</h3>
                <p><?= $collected ?></p>
                <a href="enter_results.php">Enter Results</a>
            </div>
            <div class="card">
                <h3>Completed Results</h3>
                <p><?= $completed ?></p>
                <a href="results_history.php">History</a>
            </div>
            <div class="card">
                <h3>Total Billed</h3>
                <p>Kshs <?= number_format($total, 2) ?></p>
            </div>
            <div class="card">
                <h3>Paid</h3>
                <p>Kshs <?= number_format($paid, 2) ?></p>
            </div>
            <div class="card">
                <h3>Partially Paid</h3>
                <p>Kshs <?= number_format($partial, 2) ?></p>
            </div>
            <div class="card">
                <h3>Unpaid</h3>
                <p>Kshs <?= number_format($unpaid, 2) ?></p>
            </div>
        </div>
       
        

        <div class="section">
            <h2>Recent Completed Tests</h2>
            <ul>
                <?php while ($row = $recentResults->fetch_assoc()): ?>
                    <li><?= htmlspecialchars($row['test_name']) ?> — <?= $row['result_date'] ? date('d-M-Y', strtotime($row['result_date'])) : 'Pending' ?></li>
                <?php endwhile; ?>
            </ul>
        </div>
        <div class="section">
            <h2>Quick Actions</h2>
            <button onclick="location.href='view_requests.php'">Collect Samples</button>
            <button onclick="location.href='enter_results.php'">Enter Results</button>
            <button onclick="location.href='my_revenue.php'">View Revenue</button>
        </div>

    </main>

</body>

</html>