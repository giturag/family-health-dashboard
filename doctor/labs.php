<?php

require_once __DIR__ . '/../app/bootstrap.php';
//check if doctor is logged in
fhd_require_role(['doctor', 'specialtydr']);

$doctorID = $_SESSION['subID'];

// Fetch lab requests grouped by labtestID
$query = "
    SELECT 
    lt.labtestID,
    MAX(ltc.lab_name) AS lab_name,
    lt.patientID_FK,
    lt.hvisitID_FK,
    lt.labtechID_FK,
    MAX(s.fname) AS fname,
    MAX(s.lname) AS lname,
    COUNT(lti.lbitemID) AS total_tests,
    COALESCE(SUM(CASE WHEN lti.result_date IS NOT NULL THEN 1 ELSE 0 END), 0) AS completed_tests
    FROM lab_requests lt
    JOIN subscribers s ON s.subID = lt.patientID_FK
    LEFT JOIN lab_technicians ltc ON ltc.labtechID_FK = lt.labtechID_FK
    LEFT JOIN lab_test_items lti ON lti.requestID_FK = lt.labtestID
    WHERE lt.doctorID_FK = ?
    GROUP BY lt.labtestID
    ORDER BY lt.labtestID DESC
    ";

//Max to avoid group by error
//
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $doctorID);
$stmt->execute();
$requests = $stmt->get_result();

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
            <li><a href="patients.php">My Patients</a></li>
            <li><a class="active" href="labs.php">Labs</a></li>
            <li><a href="my_earnings.php">My Revenue</a></li>
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </div>
    <main>
        <h2>Lab Requests</h2>

        <table>
        <tr>
            <th>Request ID</th>
            <th>Patient</th>
            <th>Test Centre</th>
            <th>Total Tests</th>
            <th>Completed</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php while ($row = $requests->fetch_assoc()):
            $status = ($row['completed_tests'] == 0) ? 'Pending' :
                    (($row['completed_tests'] < $row['total_tests']) ? 'Partial' : 'Complete');
        ?>
            <tr>
            <td><?= $row['labtestID'] ?></td>
            <td><?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?></td>
            <td><?= $row['lab_name'] ? htmlspecialchars($row['lab_name']) : 'Pending' ?></td>
            <td><?= $row['total_tests'] ?></td>
            <td><?= $row['completed_tests'] ?></td>
            <td><?= $status ?></td>
            <td><a class="btn" href="lab_request_view.php?id=<?= $row['labtestID'] ?>">View</a></td>
            </tr>
        <?php endwhile; ?>
        </table>


    </main>

</body>
</html>