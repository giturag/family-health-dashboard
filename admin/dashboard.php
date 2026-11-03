<?php

require_once __DIR__ . '/../app/bootstrap.php';
if (!isset($_SESSION['loggedinusr']) || $_SESSION['sub_role'] !== 'admin') {
    header('Location: ../signin.php');
    exit;
}

// User counts
$totalUsers = $conn->query("SELECT COUNT(*) FROM subscribers")->fetch_row()[0];
$totalDoctors = $conn->query("SELECT COUNT(*) FROM subscribers WHERE sub_role = 'doctor'")->fetch_row()[0];
$totalLabtechs = $conn->query("SELECT COUNT(*) FROM subscribers WHERE sub_role = 'labtech'")->fetch_row()[0];

// Labs
$totalLabRequests = $conn->query("SELECT COUNT(*) FROM lab_requests")->fetch_row()[0];
$pendingLabResults = $conn->query("
  SELECT COUNT(*) FROM lab_test_items 
  WHERE result_date IS NULL AND test_date IS NOT NULL
")->fetch_row()[0];

// Total consultations
$totalConsults = $conn->query("SELECT COUNT(*) FROM health_visits")->fetch_row()[0];

// Monthly breakdown
$monthlyConsults = $conn->query("
  SELECT 
    YEAR(visit_date) AS year,
    MONTH(visit_date) AS month,
    COUNT(*) AS count
  FROM health_visits
  GROUP BY YEAR(visit_date), MONTH(visit_date)
  ORDER BY YEAR(visit_date) DESC, MONTH(visit_date) DESC
");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

    .card {
      background: white;
      padding: 20px;
      margin-bottom: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    h1 {
      color: #333;
    }
     table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #ccc; padding: 8px; }
    th { background-color: #007bff; color: white; }
  </style>
</head>
<body>

<div class="sidebar">
  <h2>Admin Panel</h2>
  <a href="dashboard.php" class="active">Dashboard</a>
  <a href="user_stats.php">User Stats</a>
  <a href="consultations.php">Consultations</a>
  <a href="lab_requests.php">Lab Requests</a>
  <a href="revenue.php">Revenue</a>
  <a href="view_messages.php">Messages</a>
  <a href="logout.php">Logout</a>
</div>

<div class="main-content">
  <h1>Welcome, Admin</h1>
  <div class="card">
    <p>This is your dashboard overview. Select a section from the sidebar to manage users, view consultations, or handle lab requests.</p>
  </div>

    <div class="card">
    <h2>User Overview</h2>
    <p>Total Users: <strong><?= $totalUsers ?></strong></p>
    <p>Doctors: <strong><?= $totalDoctors ?></strong></p>
    <p>Lab Technicians: <strong><?= $totalLabtechs ?></strong></p>
    </div>

    <div class="card">
    <h2>Lab Summary</h2>
    <p>Total Lab Requests: <strong><?= $totalLabRequests ?></strong></p>
    <p>Pending Results: <strong><?= $pendingLabResults ?></strong></p>
    </div>

    <div class="card">
    <h2>Consultations</h2>        
        <p>Total Consultations: <strong><?= $totalConsults ?></strong></p>

        <h3>Monthly Breakdown</h3>
        <table>
            <tr><th>Month</th><th>Count</th></tr>
           <?php while ($month = $monthlyConsults->fetch_assoc()): ?>
            <tr>
                <td><?= date("M Y", strtotime($month['year'] . '-' . $month['month'] . '-01')) ?></td>
                <td><?= $month['count'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>        
    </div>

</div>

</body>
</html>