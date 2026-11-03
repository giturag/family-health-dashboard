<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role('labtech');


$labtechID = $_SESSION['subID'];

$statusFilter = $_GET['status'] ?? 'all';
$periodFilter = $_GET['period'] ?? 'alltime';

//date filter logic
$dateClause = '';
switch ($periodFilter) {
  case 'today':
    $dateClause = "DATE(i.test_date) = CURDATE()";
    break;
  case 'week':
    $dateClause = "WEEK(i.test_date) = WEEK(CURDATE()) AND YEAR(i.test_date) = YEAR(CURDATE())";
    break;
  case 'month':
    $dateClause = "MONTH(i.test_date) = MONTH(CURDATE()) AND YEAR(i.test_date) = YEAR(CURDATE())";
    break;

}

//get labtech's region
$regionQuery = $conn->prepare("SELECT region FROM subscribers WHERE subID = ?");
$regionQuery->bind_param("i", $labtechID);
$regionQuery->execute();
$regionResult = $regionQuery->get_result();
$labRegion = $regionResult->fetch_assoc()['region'];
$regionQuery->close();


//fetch lab requests
$query = "
    SELECT lt.labtestID, lt.*, p.subID, CONCAT(p.fname, ' ', p.lname) AS patient_name, tc.test_name, tc.test_type, i.*
    FROM lab_requests lt
    JOIN subscribers p ON p.subID = lt.patientID_FK
    JOIN lab_test_items i ON i.requestID_FK = lt.labtestID
    JOIN lab_tests_catalog tc ON tc.testID = i.testID_FK
    ";
$where = [];
$params = [];
$types = '';

switch ($statusFilter) {
  case 'pending':
    $where[] = "i.test_date IS NULL AND p.region = ?";
    $params[] = $labRegion;
    $types .= 's';
    break;
  case 'collected':
    $where[] = "i.test_date IS NOT NULL AND i.result_date IS NULL AND lt.labtechID_FK = ?";
    $params[] = $labtechID;
    $types .= 'i';
    break;
  case 'completed':
    $where[] = "i.result_date IS NOT NULL and lt.labtechID = ?";
    $params[] = $labtechID;
    $types .= 'i';
    break;
}

//add date clause if any to the where array
if ($dateClause) {
  $where[] = $dateClause; 
}

//combine all conditions in the where array using AND then append them to the query in a single where clause
if(!empty($where)) {
  $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " ORDER BY i.test_date DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>View All Requests</title>
  <style>
    body{
        font-family: 'Segoe UI', sans-serif; 
        background-color: #f9f9f9;
        margin: 0 0;
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
     
    table { 
      width: 80%; border-collapse: collapse; background: #fff;
     }
    th, td { 
      padding: 10px; border: 1px solid #ccc;
     }
    th { 
      background: #6f42c1; color: white; 
    }
    
    .pending {
      background-color: #fff9c4; /*light yellow*/
    }
    .collected {
      background-color: #b3e5fc; /*light blue */
    }
    .completed {
      background-color: #c8e6c9; /*light green */
    }
  </style>
</head>
<body>
     <header>
      <nav class="menu">
        <ul class="navleft">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="view_requests.php">View Pending Requests</a></li>
            <li><a href="enter_results.php">Enter Results</a></li>
            <li><a class="active" href="view_all_requests.php">View All Requests</a></li>
            <li><a href="my_revenue.php">My Revenue</a></li>            

           
        </ul>
        <ul class="navright">
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </header>
  <h2>Select Lab Requests</h2>
  <form action="view_all_requests.php" method="get">
    <label for="status">Status:</label>
    <select name="status" id="status">
      <option value="all">All</option>
      <option value="pending">Pending (<?= $labRegion ?>)</option>
      <option value="collected">Collected by Me</option>
      <option value="completed">Completed by Me</option>
    </select>
    <label for="period">Time Period:</label>
    <select name="period" id="period">
      <option value="alltime">All Time</option>
      <option value="today">Today</option>
      <option value="week">This Week</option>
      <option value="month">This Month</option>
    </select>
    <button type="submit">Filter</button>
  </form>
  <!--Results Table-->
  <table>
    <thead>
      <tr>
        <th>Lab Request  ID</th>
        <th>Patient</th>
        <th>Test Name</th>
        <th>Sample Date</th>
        <th>Result Date</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php function statusClass($row) {
        //function to set row class for css
          if (!$row['test_date']) return 'pending';
          elseif (!$row['result_date']) return 'collected';
          else return 'completed';
        }
        while($row = $result->fetch_assoc()): 
         ?>
        <tr class="<?= statusClass($row) ?>">
          <td><?= $row['labtestID'] ?></td>
          <td><?= htmlspecialchars($row['patient_name']) ?></td>
          <td><?= htmlspecialchars($row['test_name']) ?></td>
          <td><?= $row['test_date'] ?
            date_format(date_create($row['test_date']), "d-M-Y")
            : 'Not Taken' ?></td>
          <td><?= $row['result_date'] ? 
            date_format(date_create($row['result_date']), "d-M-Y")
            : 'Not Reported' ?></td>
          <td>
            <?php
              if (!$row['test_date']) echo 'Pending';
              elseif (!$row['result_date']) echo 'Collected';
              else echo 'Completed';
            ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
  
</body>
</html>
