<?php

require_once __DIR__ . '/../app/bootstrap.php';
if (!isset($_SESSION['loggedinusr']) || $_SESSION['sub_role'] !== 'admin') {
    header('Location: ../signin.php');
    exit;
}

// Totals by role
$roles = ['doctor', 'labtech', 'patient', 'admin'];
$roleCounts = [];
foreach ($roles as $role) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM subscribers WHERE sub_role = ?");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $roleCounts[$role] = $stmt->get_result()->fetch_row()[0];
}

// Filters
$roleFilter = $_GET['role'] ?? '';
$regionFilter = $_GET['region'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$query = "SELECT subID, fname, lname, sub_role, region, acc_creation_time FROM subscribers WHERE 1=1";
$params = [];
$types = "";

if ($roleFilter) {
    $query .= " AND sub_role = ?";
    $params[] = $roleFilter;
    $types .= "s";
}
if ($regionFilter) {
    $query .= " AND region = ?";
    $params[] = $regionFilter;
    $types .= "s";
}
if ($startDate) {
    $query .= " AND acc_creation_time >= ?";
    $params[] = $startDate;
    $types .= "s";
}
if ($endDate) {
    $query .= " AND acc_creation_time <= ?";
    $params[] = $endDate;
    $types .= "s";
}

$query .= " ORDER BY acc_creation_time DESC";
$stmt = $conn->prepare($query);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$users = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>User Stats</title>
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
    th { background-color: #007bff; color: white; }
    input, select { padding: 6px; margin-right: 10px; }
  </style>
</head>
<body>
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <a href="dashboard.php">Dashboard</a>
        <a class="active" href="user_stats.php">User Stats</a>
        <a href="consultations.php">Consultations</a>
        <a href="revenue.php">Revenue</a>
        <a href="lab_requests.php">Lab Requests</a>
        <a href="view_messages.php">Messages</a>
        <a href="logout.php">Logout</a>
    </div>
    <div class="main-content">
    <h2>User Statistics</h2>

    <div class="card">
    <h3>Totals by Role</h3>
    <ul>
        <?php foreach ($roleCounts as $role => $count): ?>
        <li><strong><?= ucfirst($role) ?>s:</strong> <?= $count ?></li>
        <?php endforeach; ?>
    </ul>
    </div>

    <div class="card">
    <h3>Filter Users</h3>
    <form method="GET" action="user_stats.php">
        <select name="role">
        <option value="">All Roles</option>
        <?php foreach ($roles as $role): ?>
            <option value="<?= $role ?>" <?= $roleFilter == $role ? 'selected' : '' ?>><?= ucfirst($role) ?></option>
        <?php endforeach; ?>
        </select>
        <input type="text" name="region" placeholder="Region" value="<?= htmlspecialchars($regionFilter) ?>">
        <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
        <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
        <button type="submit">Filter</button>
    </form>
    </div>

    <div class="card">
    <h3>Users</h3>
    <table>
        <tr><th>Name</th><th>Role</th><th>Region</th><th>Signup Date</th></tr>
        <?php while ($u = $users->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($u['fname'] . ' ' . $u['lname']) ?></td>
            <td><?= ucfirst($u['sub_role']) ?></td>
            <td><?= htmlspecialchars($u['region']) ?></td>
            <td><?= date("d M Y", strtotime($u['acc_creation_time'])) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
    </div>
</div>

</body>
</html>