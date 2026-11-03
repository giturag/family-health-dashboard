<?php

require_once __DIR__ . '/../app/bootstrap.php';
//display mysqli errors
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

//echo '<pre>'; print_r(array_keys($_SESSION)); echo '</pre>';

//check if user is logged in
fhd_require_role(['patient', 'dependent']);


//fetch patients and dependents
$uid = isset($_SESSION['subID']) ? intval($_SESSION['subID']) : 0;
if (!$uid) {
    die("User session not set.");
}

$specialties = ['General Medicine', 'Cardiology', 'Chest Pulmonology', 'Dermatology', 'Gastroenterology', 'Orthopedics','Paediatrics', 'Rheumatology' ];

try {
$patients = $conn->prepare("SELECT subID, CONCAT(fname, ' ', lname) AS name FROM subscribers WHERE subID = ? OR guardianID = ?");
$patients->bind_param("ii", $uid, $uid);
$patients->execute();
$patientsResult = $patients->get_result();
} catch (Exception $e) {
    die("Error fetching patients: " . $e->getMessage());
}
if (!$patientsResult) {
    die("Patients query failed" . $conn->error);
}

//fetch doctors
$doctorsResult = $conn->query("SELECT subID, CONCAT(fname, ' ', lname) AS doctor_name FROM subscribers WHERE sub_role='doctor' OR sub_role='specialtydr' ORDER BY fname, lname; ");
if (!$doctorsResult) {
    die("Doctors query failed: " . $conn->error);
}

$time_slots = ["09:00:00","10:00:00","11:00:00","12:00:00","14:00:00","15:00:00","16:00:00"];
$allowedPatientIDs = fhd_patient_ids($conn);

//Book appointment
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'book') {
    fhd_verify_csrf();

    $patientID = intval($_POST['patient'] ?? 0);
    $doctorID = intval($_POST['doctor'] ?? 0);
    $appointment_date = trim((string) ($_POST['appt_date'] ?? ''));
    $time_slot = trim((string) ($_POST['time_slot'] ?? ''));

    if (!in_array($patientID, $allowedPatientIDs, true)) {
        fhd_abort(403, 'Forbidden');
    } elseif (!in_array($time_slot, $time_slots, true)) {
        $feedback = 'Please choose a valid time slot.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointment_date)
        || $appointment_date < date('Y-m-d')) {
        $feedback = 'Please choose a valid future appointment date.';
    } else {
        $docCheck = $conn->prepare(
            "SELECT 1 FROM subscribers WHERE subID = ? AND sub_role IN ('doctor', 'specialtydr') LIMIT 1"
        );
        $docCheck->bind_param('i', $doctorID);
        $docCheck->execute();
        $doctorExists = (bool) $docCheck->get_result()->fetch_row();
        $docCheck->close();

        $slotCheck = $conn->prepare(
            "SELECT 1 FROM appointments
             WHERE doctorID_FK = ? AND appt_date = ? AND time_slot = ? AND status <> 'Cancelled'
             LIMIT 1"
        );
        $slotCheck->bind_param('iss', $doctorID, $appointment_date, $time_slot);
        $slotCheck->execute();
        $slotTaken = (bool) $slotCheck->get_result()->fetch_row();
        $slotCheck->close();

        if (!$doctorExists) {
            $feedback = 'Selected doctor is not available.';
        } elseif ($slotTaken) {
            $feedback = 'That time slot is already booked. Please choose another.';
        } else {
            $status = 'Scheduled';
            $bkstmt = $conn->prepare(
                'INSERT INTO appointments (patientID_FK, doctorID_FK, appt_date, time_slot, status)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $bkstmt->bind_param('iisss', $patientID, $doctorID, $appointment_date, $time_slot, $status);
            if ($bkstmt->execute()) {
                $feedback = 'Appointment Booked';
            } else {
                $feedback = 'Unable to book appointment';
            }
            $bkstmt->close();
        }
    }
}
try {
$patients = $conn->prepare("SELECT subID, CONCAT(fname, ' ', lname) AS name FROM subscribers WHERE subID = ? OR guardianID = ?");
$patients->bind_param("ii", $uid, $uid);
$patients->execute();
$patientsResult2 = $patients->get_result();
} catch (Exception $e) {
    die("Error fetching patients: " . $e->getMessage());
}
if (!$patientsResult2) {
    die("Patients query failed" . $conn->error);
}
//handle filter for fetching appointments
$selectedUser = $uid;
$appointmentStatus = 'all';
$selectedSpecialty = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'filter') {
    $selectedUser = intval($_POST['patientID'] ?? $uid);
    if (!in_array($selectedUser, $allowedPatientIDs, true)) {
        fhd_abort(403, 'Forbidden');
    }
    $appointmentStatus = in_array($_POST['appt_status'] ?? 'all', ['all', 'scheduled', 'completed'], true)
        ? $_POST['appt_status']
        : 'all';
    $selectedSpecialty = trim((string) ($_POST['specialty'] ?? ''));
}

//Values to build the query and prepared statement
$filters = [];
$params = [];
$types = "";

// Always filter by patient ID
$filters[] = "a.patientID_FK = ?";
$params[] = $selectedUser;
$types .= "i";

// Filter by appointment status
if ($appointmentStatus === 'scheduled') {
    $filters[] = "a.status = 'Scheduled'";
    $params[] = $appointmentStatus;
    $types .= "s";
} elseif ($appointmentStatus === 'completed') {
    $filters[] = "a.status = 'Completed'";
    $params[] = $appointmentStatus;
    $types .= "s";
}

// Filter by specialty
if (!empty($selectedSpecialty)) {
    $filters[] = "d.specialty = ?";
    $params[] = $selectedSpecialty;
    $types .= "s";
}

// Build WHERE clause from filters
$whereClause = !empty($filters) ? "WHERE " . implode(" AND ", $filters) : "";


$apptsql = "
    SELECT 
        a.*,
        CONCAT(s.fname, ' ', s.lname) AS doctor_name,
        d.specialty
    FROM appointments a
    LEFT JOIN doctor_profiles d ON a.doctorID_FK = d.docID_FK
    LEFT JOIN subscribers s ON d.docID_FK = s.subID
    $whereClause
    ORDER BY a.appt_date DESC";

$filterStmt = $conn->prepare($apptsql);
// Bind only if we have params
if (!empty($params)) {
    $filterStmt->bind_param($types, ...$params);
}
$filterStmt->execute();
$results = $filterStmt->get_result();

//Fetch specialties for dropdown
$specialtiesResult = $conn->query("
    SELECT DISTINCT specialty
    FROM doctor_profiles
    where specialty IS NOT NULL AND specialty <> ''
    ORDER BY specialty
");
?>
<!DOCTYPE html>
<html>
    <head lang="en">
        <title>Appointments</title>
        <meta http-equiv="content-type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <style>
            body{
            font-family: 'Segoe UI', sans-serif; 
            background-color: #f9f9f9;
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
                background-color: #1967d2;
            }
            .vitalsusr{
                padding-top: 80px;
                margin: auto;
                width:400px;
                padding: auto;
                box-sizing: border-box;
            }
            h2 {
                text-align: center;
                color: #17a2b8;

            }
            form {
                width: 60vw;
                max-width: 500px;
                min-width: 300px;
                margin: 30px auto;
                padding: 15px;
                border: 1px solid #ccc;
                border-radius: 25px;
                width: 450px;
            }
            label {
                margin-top: 0.6rem;
                font-weight: bold;
            }
            input, select {
                /*margin: 0.6rem auto;
                display: block;
                width: 100%;
                height: 2em;
                background-color: #f9f9f9;*/
                box-sizing: border-box;
                width: 100%;
                padding: 0.6rem;
                margin-bottom: 0.6rem;
                border: 1px solid #ccc;
                border-radius: 5px;
            }
            button[type="submit"]  {
                width: 50%;
                margin: 0.6rem auto;
                height: 2rem;
                border: 1px solid #ccc;
                border-radius: 5px;            
            }
            .confirmation.success {
                color: green;
                font-weight: bold;
            }
            .confirmation.error {
                color: red;
                font-weight: bold;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 20px;
            }
            th, td {
                border: 1px solid #ddd;
                padding: 8px;
            }
            th {
                background-color: #4CAF50;
                color: white;
            }
            tr:nth-child(even) {
                background-color: #f2f2f2;
            }
        </style>
        <script>
            //fetch doctors in patients region
            function fetchPatientRegionAndFilterDoctors() {
            const patientID = document.getElementById('patient').value;
            const specialty = document.getElementById('specialty').value;

            if (patientID && specialty) {
                fetch(`get_doctors_by_region_specialty.php?patient=${patientID}&specialty=${encodeURIComponent(specialty)}`)
                .then(response => response.json())
                .then(data => {
                    const doctorSelect = document.getElementById('doctor');
                    doctorSelect.innerHTML = '';

                    //populate doctor element with array of doctors if any
                    if (data.length > 0) {
                    data.forEach(doc => {
                        const option = document.createElement('option');
                        option.value = doc.subID;
                        option.textContent = `Dr. ${doc.doctor_name}`;
                        doctorSelect.appendChild(option);
                    });
                    } else {
                    const option = document.createElement('option');
                    option.textContent = 'No doctors found for this region and specialty';
                    option.disabled = true;
                    doctorSelect.appendChild(option);
                    }
                });
            }
            }

            function fetchAvailableSlots() {
                const doctorID = document.getElementById('doctor').value;
                const apptDate = document.getElementById('appt_date').value;

                if (doctorID && apptDate) {
                    fetch(`get_available_slots.php?doctor=${doctorID}&date=${apptDate}`)
                    .then(response => response.json())
                    .then(data => {
                        const slotSelect = document.getElementById('time_slot');
                        slotSelect.innerHTML = '';

                        if (data.length > 0) {
                            data.forEach(slot => {
                                const option = document.createElement('option');
                                option.value = slot;
                                option.textContent = slot;
                                slotSelect.appendChild(option);
                            })
                        } else {
                            const option = document.createElement('option');
                            option.textContent = 'No available slots for this day';
                            option.disabled = true;
                            slotSelect.appendChild(option);
                        }
                    })
                }
            }
            
        </script>

    </head>
    <body>
        <div class="container">
            <header>
                <nav class="menu">
                    <ul class="navleft">
                        <li><a href="myhome.php">Home</a></li>
                        <li><a href="allergies_and_conditions.php">Allergies and Conditions</a></li>
                        <li><a href="vitals.php">Vitals</a></li>
                        <li><a class="active" href="appointments.php">Appointments</a></li>
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
                
                <form name="bookingform" method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                    <?= fhd_csrf_field() ?>
                    <input type="hidden" name="action" value="book">
                    <h2>Book Appointment</h2>
                    <hr>
                    <!--confirm if the booking is successful or not-->
                    <?php 
                    $confirmationClass = '';
                    if (isset($feedback)) {
                        $confirmationClass = strpos($feedback, 'Booked') !== false ? 'success' : 'error';
                    }
                    ?>
                    <p class="confirmation <?= $confirmationClass ?>">
                        <?= isset($feedback) ? htmlspecialchars($feedback) : '' ?>
                    </p>
                    <!---select patient, primary or dependents--->

                    <label for="patient">Select Patient: </label>
                    <select name="patient" id="patient" required>
                        <option value="">---Select---</option>

                        <?php if ($patientsResult && $patientsResult->num_rows > 0): ?>
                        <?php while ($pt = $patientsResult->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($pt['subID']) ?>">
                                <?= htmlspecialchars($pt['name']) ?>
                            </option>
                        <?php endwhile; ?>
                        <?php else: ?>
                            <option value="">No patients found</option>
                        <?php endif; ?>

                    </select>  

                     <!-- Specialty Selection -->
                    <label for="specialty">Select Specialty:</label>
                    <select name="specialty" id="specialty" required onchange="fetchPatientRegionAndFilterDoctors()">
                        <option value="">---Select---</option>
                        <?php foreach ($specialties as $spec): ?>
                        <option value="<?= $spec ?>"><?= $spec ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!---Select doctor--->  
                    <label for="doctor">Select Doctor:</label>
                    <select id="doctor" name="doctor" required>
                        <option value="">Select patient and specialty first</option>
                    </select>


                    <label for="date">Date</label><br>
                    <input type="date" name="appt_date" id="appt_date" required onchange="fetchAvailableSlots()" min="<?= date('Y-m-d') ?>">
                    <label for="time">Available Time Slots</label><br>
                    <select name="time_slot" id="time_slot" required>
                        <option value="">Select doctor and date first</option>
                        <!-- <?php /* foreach ($time_slots as $slot) {
                            echo "<option value=\"$slot\">$slot</option>";
                        }*/ ?>-->
                    </select><br>                    
                    <button type="submit">Book Appointment</button>
                </form>

                <div class="myappointments">
                    <!--form to select user to fetch appointments-->
                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post">
                        <input type="hidden" name="action" value="filter">
                        <h2>View Appointments</h2>
                        <!-- Patient Filter -->
                        <label for="patientID">Patient:</label>
                        <select name="patientID">
                        <?php if ($patientsResult2 && $patientsResult2->num_rows > 0): ?>
                        <?php while ($pt = $patientsResult2->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($pt['subID']) ?>">
                                <?= htmlspecialchars($pt['name']) ?>
                            </option>
                        <?php endwhile; ?>
                        <?php else: ?>
                            <option value="">No patients found</option>
                        <?php endif; ?>
                                
                            
                        </select>

                        <!-- Appointment Status Filter-->
                        <label for="appt_status">Appointment Status:</label>
                        <select name="appt_status" id="appt_status">
                            <option value="all">All Appointments</option>
                            <option value="scheduled">Upcoming Appointments</option>
                            <option value="completed">Past Appointments</option>
                        </select>

                        <!-- Specialty Filter -->
                         <label for="specialty">Specialty:</label>
                         <select name="specialty" id="specialty">
                            <option value="">All Specialties</option>
                            <?php while($row = $specialtiesResult->fetch_assoc()) {
                                //if user selected a specialty before and it matches this row's specialty
                                $sel = (isset($selectedSpecialty) && $selectedSpecialty == $row['specialty']) ? 'selected' : '';
                                //output the option for this specialty
                                echo "<option value=`{$row['specialty']}` $sel>{$row['specialty']}</option>";
                            }?>
                         </select>

                        <button type="submit">Filter</button>
                    </form>
                </div>
                <div class="viewappts">
                     <?php if ($results && $results->num_rows > 0): 
                         echo "<p>Total appointments found: " . $results->num_rows . "</p>";
                    ?>

                    <table>
                        <thead>
                            <tr>
                                <th>Doctor</th>
                                <th>Specialty</th>
                                <th>Appointment Date</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $results->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['doctor_name']) ?></td>
                                        <td><?= htmlspecialchars($row['specialty']) ?></td>
                                        <td><?= date('d-M-Y', strtotime(htmlspecialchars($row['appt_date']))) ?></td>
                                        <td><?= htmlspecialchars($row['time_slot']) ?></td>
                                        <td><?= htmlspecialchars($row['status']) ?></td>
                                    </tr>

                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p>No appointments found for selected options</p>
                    <?php endif; ?>
                </div>

            </main>
            <footer>

            </footer>
        </div>
    </body>
</html>