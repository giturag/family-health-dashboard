<?php

require_once __DIR__ . '/../app/bootstrap.php';
if (!isset($_SESSION['loggedinusr']) || $_SESSION['sub_role'] !== 'admin') {
    header('Location: ../signin.php');
    exit;
}

//filters
$regionFilter = $_GET['region'] ?? '';
$testCentreFilter = $_GET['test_centre'] ?? '';
$doctorFilter = $_GET['doctor_id'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

//get counts
$countQuery = "
SELECT COUNT(DISTINCT lt.labtechID_FK)
FROM lab_requests lt
JOIN subscribers s ON lt.labtechID_FK = s.subID
WHERE 1=1";

$countParams = [];
$countTypes = "";

if ($regionFilter) {
    $countQuery .= " AND s.region = ?";
    $countParams[] = $regionFilter;
    $countTypes .= "s";
}
if ($testCentreFilter) {
    $countQuery .= " AND lt.test_centre = ?";
    $countParams[] = $testCentreFilter;
    $countTypes .= "s";
}
if ($doctorFilter) {
    $countQuery .= " AND lt.doctorID_FK = ?";
    $countParams[] = $doctorFilter;
    $countTypes .= "i";
}

$countStmt = $conn->prepare($countQuery);
if ($countParams) $countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$totalTechs = $countStmt->get_result()->fetch_row()[0];
$totalPages = ceil($totalTechs / $limit);

//aggregated lab test counts
$query = "
SELECT lt.labtechID_FK, s.fname, s.lname, s.region, COUNT(*) AS test_count
FROM lab_requests lt
JOIN subscribers s ON lt.labtechID_FK = s.subID
WHERE 1=1";

$params = [];
$types = "";

if ($regionFilter) {
    $query .= " AND s.region = ?";
    $params[] = $regionFilter;
    $types .= "s";
}
if ($testCentreFilter) {
    $query .= " AND lt.test_centre = ?";
    $params[] = $testCentreFilter;
    $types .= "s";
}
if ($doctorFilter) {
    $query .= " AND lt.doctorID_FK = ?";
    $params[] = $doctorFilter;
    $types .= "i";
}

$query .= " GROUP BY lt.labtechID_FK ORDER BY test_count DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$results = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Lab Test Stats</title>
  <meta charset="UTF-8">
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
    .card { background: #fff; padding: 20px; margin-bottom: 20px; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; background: #fff; }
    th, td { border: 1px solid #ccc; padding: 8px; }
    th { background-color: #20c997; color: white; }
    input, select { padding: 6px; margin-right: 10px; }
  </style>
</head>
<body>
     <div class="sidebar">
        <h2>Admin Panel</h2>
        <a href="dashboard.php">Dashboard</a>
        <a href="user_stats.php">User Stats</a>
        <a href="consultations.php">Consultations</a>
        <a class="active" href="lab_requests.php">Lab Requests</a>
        <a href="revenue.php">Revenue</a>
        <a href="view_messages.php">Messages</a>
        <a href="logout.php">Logout</a>
    </div>


    <div class="main-content">
        <h2>Lab Test Statistics</h2>

        <div class="card">
        <h3>Filter Lab Tests</h3>
        <form method="GET" action="labtest_stats.php">
            <input type="text" name="region" placeholder="Region" value="<?= htmlspecialchars($regionFilter) ?>">
            <input type="text" name="test_centre" placeholder="Test Centre" value="<?= htmlspecialchars($testCentreFilter) ?>">
            <input type="number" name="doctor_id" placeholder="Doctor ID" value="<?= htmlspecialchars($doctorFilter) ?>">
            <button type="submit">Filter</button>
        </form>
        </div>

        <div class="card">
        <h3>Tests by Lab Technician</h3>
        <table>
            <tr><th>Lab Technician</th><th>Region</th><th>Test Count</th></tr>
            <?php while ($row = $results->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?></td>
                <td><?= htmlspecialchars($row['region']) ?></td>
                <td><?= $row['test_count'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>

        <?php if ($totalPages > 1): ?>
            <div style="margin-top: 20px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                style="padding: 6px 10px; margin: 2px; background: <?= $i == $page ? '#20c997' : '#eee' ?>; color: <?= $i == $page ? '#fff' : '#000' ?>; text-decoration: none; border-radius: 4px;">
                <?= $i ?>
                </a>
            <?php endfor; ?>
            </div>
        <?php endif; ?>
        </div>
    </div>

</body>
</html>
