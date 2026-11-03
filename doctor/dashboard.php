<?php

require_once __DIR__ . '/../app/bootstrap.php';
//check if doctor is logged in
fhd_require_role(['doctor', 'specialtydr']);

$doctorID = $_SESSION['subID'];

//Doctor Metadata
$docQuery = $conn->prepare("SELECT fname, lname FROM subscribers WHERE subID = ?");
$docQuery->bind_param("i", $doctorID);
$docQuery->execute();
$docData = $docQuery->get_result()->fetch_assoc();
$doctorName = $docData['fname'] . ' ' . $docData['lname'];
//$doctorSpecialty = $docData['specialty'] ?? 'General';


//fetch summaries
$today = date('Y-m-d');

$appointments = $conn->query("SELECT COUNT(*) as total FROM appointments WHERE doctorID_FK = $doctorID
    AND appt_date >= CURDATE()")->fetch_assoc()['total'];
$pendingResults = $conn->query("SELECT COUNT(*) as total FROM lab_requests lt
    JOIN lab_test_items i ON i.requestID_FK = lt.labtestID
    WHERE i.result_date IS NULL AND test_date IS NOT NULL AND lt.doctorID_FK = $doctorID")->fetch_assoc()['total'];
$criticalResults = $conn->query("SELECT COUNT(*) as total FROM lab_requests lt
    JOIN lab_test_items i ON i.requestID_FK = lt.labtestID
    WHERE i.test_verdict = 'critical' AND lt.doctorID_FK = $doctorID")->fetch_assoc()['total'];
$recentConsults = $conn->query("SELECT v.hvisitID, v.visit_date, p.fname, p.lname, v.visit_notes 
    FROM health_visits v 
    JOIN subscribers p ON v.patientID_FK = p.subID
    WHERE v.doctorID_FK = $doctorID
    ORDER BY v.visit_date DESC LIMIT 5
    ");  
    
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
            <li><a class="active" href="dashboard.php">Dashboard</a></li>
            <li><a href="appointments.php">Appointments</a></li>
            <li><a href="consultations.php">Consultations</a></li>
            <li><a href="patients.php">My Patients</a></li>
            <li><a href="labs.php">Labs</a></li>
            <li><a href="my_earnings.php">My Revenue</a></li>
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </div>
    <main>
        <div class="card">
            <h1>Welcome, Dr <?= htmlspecialchars($doctorName) ?></h1>
        </div>
         <div class="card summary-boxes">
            <div class="summary-box">
                <h3><?= $appointments ?></h3>
                <p>Upcoming Appointments</p>
            </div>
            <div class="summary-box">
                <h3><?= $pendingResults ?></h3>
                <p>Pending Lab Results</p>
            </div>
            <div class="summary-box">
                <h3><?= $criticalResults ?></h3>
                <p>Critical Results</p>
            </div>
         </div>
        <div class="card">
            <h3>Recent Consultations</h3>
            <?php if ($recentConsults->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Patient</th>
                            <th>Summary</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $recentConsults->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('d-M-Y', strtotime($row['visit_date'])) ?></td>
                                <td><?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?></td>
                                <td><?= nl2br(htmlspecialchars(substr($row['visit_notes'], 0, 60))) 
                                /* nlbr newline break for all new lines and substr to get 0-60 chars*/?></td>
                                <td class="actions">
                                    <a href="view_consultation.php?id=<?= $row['hvisitID'] ?>">View</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p>NO consultations found.</p>
                <?php endif; ?>
        </div>
        <div class="card">
            <h3>Quick Actions</h3>
            
        </div>

    </main>

</body>
</html>