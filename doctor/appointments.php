<?php

require_once __DIR__ . '/../app/bootstrap.php';
//echo '<pre>'; print_r($_SESSION); echo '</pre>';


//check if user is doctor
fhd_require_role(['doctor', 'specialtydr']);
 $doctor_id = $_SESSION['subID'];
 //get appointments from database
 $stmt = $conn->prepare("SELECT a.*, CONCAT(p.fname, ' ', p.lname) AS patient_name FROM appointments a 
        JOIN subscribers p ON a.patientID_FK = p.subID 
        WHERE a.doctorID_FK = ? AND DATE(a.appt_date) = CURDATE() 
        ORDER BY a.time_slot");
$stmt->bind_param("i", $doctor_id);
$stmt->execute();
$result = $stmt->get_result();    
    
?>

<!DOCTYPE html>
<html><head>
    <title>Appointments</title>
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
        background: #159131ff;
        color: white;
    }
    tr:nth-child(even) {
        background: #a3bda9ff;
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
            <li><a class="active" href="appointments.php">Appointments</a></li>
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
        <h1>Here are your appointments for today</h1>
        <table>
            <thead>
                <th>Time</th>
                <th>Patient</th>
                <th>Status</th>
                <th>Action</th>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['time_slot']) ?></td>
                        <td><?= htmlspecialchars($row['patient_name']) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td>
                            <?php if ($row['status'] === 'Scheduled'): ?>
                                <a href="start_consultation.php?appointment_id=<?=$row['apptID'] ?>">Start Consultation</a>
                            <?php else: ?>
                            <em>In Progress / Done</em>
                            <?php endif; ?>
                            </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($result->num_rows === 0): ?>
                    <tr><td colspan="4" style="text-align:center;">No appointments scheduled for today.</td></tr>
                    <?php endif; ?>
                    
            </tbody>
        </table>
    </main>

</body>
</html>