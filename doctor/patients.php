<?php

require_once __DIR__ . '/../app/bootstrap.php';
//check if doctor is logged in
fhd_require_role(['doctor', 'specialtydr']);

$doctorID = $_SESSION['subID'];

// Filters
$name = $_GET['name'] ?? '';
$gender = $_GET['gender'] ?? '';
$minAge = $_GET['min_age'] ?? '';
$maxAge = $_GET['max_age'] ?? '';

$query = "
  SELECT s.subID, s.fname, s.lname, s.gender, s.dob
  FROM subscribers s
  JOIN health_visits v ON v.patientID_FK = s.subID
  WHERE v.doctorID_FK = ?
";

$params = [$doctorID];
$types = "i";

if (!empty($name)) {
    $query .= " AND CONCAT(s.fname, ' ', s.lname) LIKE ?";
    $params[] = "%$name%";
    $types .= "s";
}
if (!empty($gender)) {
    $query .= " AND s.gender = ?";
    $params[] = $gender;
    $types .= "s";
}
if (!empty($minAge)) {
    $query .= " AND s.dob <= DATE_SUB(CURDATE(), INTERVAL ? YEAR)";
    $params[] = $minAge;
    $types .= "i";
}
if (!empty($maxAge)) {
    $query .= " AND s.dob >= DATE_SUB(CURDATE(), INTERVAL ? YEAR)";
    $params[] = $maxAge;
    $types .= "i";
}

$query .= " GROUP BY s.subID ORDER BY s.fname, s.lname";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$patients = $stmt->get_result();

    
?>

<!DOCTYPE html>
<html><head>
    <title>Doctor Dashboard</title>
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
    }
    th {
        background-color: #007bff;
        color: white;
    }
   .actions {
    margin-right: 10px;
    color: #007bff;
    text-decoration: none;
   }

    
</style>



<script>
 

</script>


</head>

<body>   
    <div class="sidebar">
         <nav class="menu">
          <ul class="navside">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="appointments.php">Appointments</a></li>
            <li><a href="consultations.php">Consultations</a></li>
            <li><a class="active" href="patients.php">My Patients</a></li>
            <li><a href="labs.php">Labs</a></li>
            <li><a href="my_earnings.php">My Revenue</a></li>
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </div>
    <main>
        <h2>My Patients</h2>

        <form method="GET" action="patients.php">
        <input type="text" name="name" placeholder="Search by name" value="<?= htmlspecialchars($name) ?>">
        <select name="gender">
            <option value="">Gender</option>
            <option value="Male" <?= $gender == 'Male' ? 'selected' : '' ?>>Male</option>
            <option value="Female" <?= $gender == 'Female' ? 'selected' : '' ?>>Female</option>
        </select>
        <input type="number" name="min_age" placeholder="Min Age" value="<?= htmlspecialchars($minAge) ?>">
        <input type="number" name="max_age" placeholder="Max Age" value="<?= htmlspecialchars($maxAge) ?>">
        <button type="submit">Filter</button>
        </form>

        <table>
        <tr>
            <th>Name</th>
            <th>Gender</th>
            <th>Age</th>
            <th>Action</th>
        </tr>
        <?php while ($row = $patients->fetch_assoc()): 
            $age = date_diff(date_create($row['dob']), date_create('today'))->y;
        ?>
            <tr>
            <td><?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?></td>
            <td><?= htmlspecialchars($row['gender']) ?></td>
            <td><?= $age ?></td>
            <td><a class="actions" href="patient_view.php?id=<?= $row['subID'] ?>">Details</a></td>
            </tr>
        <?php endwhile; ?>
        </table>

        

    </main>

</body>
</html>