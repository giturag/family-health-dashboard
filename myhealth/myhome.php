<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);

$patientID_FK = $_SESSION['subID']; // assuming patient is logged in

// Fetch latest vitals
$vitals = $conn->query("SELECT * FROM vitals WHERE subID_FK = $patientID_FK ORDER BY date_of_reading DESC LIMIT 1")->fetch_assoc();

// Fetch allergies
$allergies = $conn->query("SELECT allergen FROM allergy_records WHERE patientID_FK = $patientID_FK");

// Fetch next appointment
$appointment = $conn->query("
    SELECT a.*, CONCAT(d.fname, ' ', d.lname) AS doctor_name
    FROM appointments a 
    JOIN subscribers d ON d.subID = a.doctorID_FK 
    WHERE a.patientID_FK = $patientID_FK AND a.appt_date >= CURDATE()
    ORDER BY a.appt_date ASC LIMIT 1
    ")->fetch_assoc();

// Fetch last health visit
$visit = $conn->query("
    SELECT hv.*, CONCAT(d.fname, ' ', d.lname) AS doctor_name 
    FROM health_visits hv
    JOIN subscribers d ON d.subID = hv.doctorID_FK 
    WHERE hv.patientID_FK = $patientID_FK 
    ORDER BY visit_date DESC LIMIT 1
    ")->fetch_assoc();

// Fetch latest prescription
$prescription = $conn->query("SELECT p.*, pi.drug_name FROM prescriptions p 
    JOIN prescription_items pi ON p.presID = pi.presID_FK 
    WHERE p.patientID_FK = $patientID_FK 
    ORDER BY p.prescribe_date DESC LIMIT 1")->fetch_assoc();

// Fetch dependents
$dependents = $conn->query("SELECT CONCAT(fname, ' ', lname) AS name FROM subscribers WHERE guardianID = $patientID_FK");

// Fetch billing summary
$bills = $conn->query("SELECT 
    SUM(CASE WHEN status = 'unpaid' THEN total_amount ELSE 0 END) AS pending,
    SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) AS paid,
    SUM(total_amount) AS total
    FROM bill_headers WHERE patientID_FK = $patientID_FK")->fetch_assoc();

// Fetch diagnoses
$diagnoses = $conn->query("SELECT diagnosis FROM health_visits WHERE patientID_FK = $patientID_FK LIMIT 5");


$conn->close();
?>
<!DOCTYPE html>
<html>
    <head>
      <title>Home</title>
      <meta http-equiv="content-type" content="text/html; charset=UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{
            font-family: 'Segoe UI', sans-serif; 
            margin: 0 0;
        }
        header {
            background-color: #17a2b8;
            margin: 0 0;
            padding: 0;
            width: 100%;
            border: 1px solid #17a2b8;
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
        .menu a.active {
            background-color: white;
            color: #17a2b8;
        }
        .intro {
            text-align: center;
        }
        .features, .summaries {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            padding: 2rem;
            max-width: 1200px;
            margin: auto;
        }
        .feature, .summary {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1); /*horizontal vertical blur*/
            text-align: center;

        }
        .feature h3, .summary h3 {
            color: #17a2b8;
        }
        .feature a, .summary a {
            text-decoration: none;
            color: #17a2b8;
        }
        .feature a:hover, .summary a:hover {
            text-decoration: underline;
        }
        .feature li, .summary li {
            list-style: none;
        }
    </style>



    <script>
    

    </script>

</head>

<body>
    <header>
      <nav class="menu">
        <ul class="navleft">
            <li><a class="active" href="myhome.php">Home</a></li>
            <li><a href="allergies_and_conditions.php">Allergies and Conditions</a></li>
            <li><a href="vitals.php">Vitals</a></li>
            <li><a href="appointments.php">Appointments</a></li>
            <li><a href="health_visits.php">Health Visit</a></li>
            <li><a href="labs.php">Labs</a></li>
            <li><a href="prescriptions.php">Prescriptions</a></li>
            <li><a href="dependents.php">Dependents</a></li>
            <li><a href="my_bills.php">My Bills</a></li>

        </ul>
        <ul class="navright">
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </header>
    <main>
        <section class="intro">
            <h1>Home</h1>

        </section>
        <section class="features">
            <div class="feature">
                <h3>My Vitals</h3>
                <p>Latest Vitals: 
                    <ul>
                        <li>Temperature: <?= $vitals['temperature'] . '&deg;C' ?? 'N/A' ?></li>
                        <li>Blood Pressure: <?= $vitals['blood_pressure_systolic'] . '/' . $vitals['blood_pressure_diastolic'] . 'mmHg' ?></li>
                        <li>Weight: <?= $vitals['user_weight'] .'kg' ?></li>
                    </ul>
                </p>
                <p><a href="vitals.php">Enter Vitals</a></p>
            </div>
            <div class="feature">
                <h3>My Allergies</h3>
                <ul>
                    <?php while($row = $allergies->fetch_assoc()): ?>
                        <li><?= htmlspecialchars($row['allergen']) ?></li>
                    <?php endwhile; ?>
                </ul>
                <p><a href="allergies_and_conditions.php">Manage Allergies and Conditions</a></p>
            </div>
            <div class="feature">
                <h3>Appointments</h3>
                <p><a href="appointments.php">Manage Appointments</a></p>
                <p>Next Appointment: 
                    <?= $appointment ? $appointment['appt_date'] . ' with ' . $appointment['doctor_name'] : 'None scheduled' ?>
                </p>
            </div>
            <div class="feature">
                <h3>Health Visits</h3>
                <p>Last Health Visit:
                    <ul>
                         <li>Date: <?= $visit['visit_date'] ?? 'N/A' ?></li>
                         <li>Doctor: <?= $visit['doctor_name'] ?? 'N/A' ?></li>
                    </ul>
                </p>
                <p><a href="health_visits.php">View Health Visits</a></p>
            </div>

            <div class="feature">
                <h3>Labs</h3>
                <p><a href="labs.php">View Labs</a></p>
            </div>
            <div class="feature">
                <h3>Prescriptions</h3>
                <p>Latest Prescription: <?= $prescription['drug_name'] ?? 'No prescriptions yet' ?></p>
                <p><a href="prescriptions.php">View Prescriptions</a></p>
            </div>
            <div class="feature">
                <h3>My Dependents</h3>
                <ul>
                        <?php while($dep = $dependents->fetch_assoc()): ?>
                            <li><?= htmlspecialchars($dep['name']) ?></li>
                        <?php endwhile; ?>

                </ul>
                <p><a href="dependents.php">Manage Dependents</a></p>
            </div>
            <div class="feature">
                <h3>My Bills</h3>
                <ul>
                    <li>Pending Bills: <?= number_format($bills['pending'], 2) ?></li>
                    <li>Paid Bills: <?= number_format($bills['paid'], 2) ?></li>
                    <li>Total Bills: <?= number_format($bills['total'], 2) ?></li>
                </ul>
            </div>
        </section>
        <section class="summaries">
            <div class="condition summary">
                <h3>Diagnoses</h3>
                     <?php while($diag = $diagnoses->fetch_assoc()): ?>
                            <li><?= htmlspecialchars($diag['diagnosis']) ?></li>
                    <?php endwhile; ?>

            </div>
            <div class="allergies summary">
                <h3>Allergies</h3>

            </div>
        </section>
        
    </main>
    

</body>
</html>