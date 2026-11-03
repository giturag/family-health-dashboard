<?php
require_once "../config/db_connect.php";

// only allow admin
if ($_SESSION['sub_role'] !== 'admin') {
    die("Unauthorized");
}

$result = $conn->query("SELECT * FROM messages ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
  <title>Messages</title>
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
  <a href="dashboard.php">Dashboard</a>
  <a href="user_stats.php">User Stats</a>
  <a href="consultations.php">Consultations</a>
  <a href="lab_requests.php">Lab Requests</a>
  <a href="revenue.php">Revenue</a>
  <a  class="active" href="view_messages.php">Messages</a>
  <a href="logout.php">Logout</a>
</div>

<div class="main-content">
  <h2>Contact Messages</h2>
  <table >
    <tr>
      <th>ID</th>
      <th>Name</th>
      <th>Email</th>
      <th>Message</th>
      <th>Date</th>
    </tr>
    <?php while($row = $result->fetch_assoc()): ?>
      <tr>
        <td><?= $row['messageID'] ?></td>
        <td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td><?= nl2br(htmlspecialchars($row['message'])) ?></td>
        <td><?= $row['created_at'] ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>
</body>
</html>
