<?php

require_once __DIR__ . '/../app/bootstrap.php';
//echo '<pre>'; print_r($_SESSION); echo '</pre>';


//check if user is doctor
fhd_require_role(['doctor', 'specialtydr']);
 $doctor_id = fhd_user_id();
 $appointment_id = intval($_GET['appointment_id'] ?? 0);

 //get the vitals of current patient
 $stmt = $conn->prepare("
        SELECT
            a.*,
            CONCAT(p.fname, ' ', p.lname) AS patient_name,
            p.gender, p.subID,
            TIMESTAMPDIFF(YEAR, p.DOB, CURDATE()) AS age,
            v.*
        FROM appointments a
        JOIN subscribers p ON a.patientID_FK = p.subID
        LEFT JOIN vitals v ON v.vitalsID = (
            SELECT vitalsID
            FROM vitals
            WHERE subID_FK = p.subID
            ORDER BY date_of_reading DESC
            LIMIT 1
            )
        WHERE a.apptID = ? AND a.doctorID_FK = ?");
$stmt->bind_param("ii", $appointment_id, $doctor_id);
$stmt->execute();
$result = $stmt->get_result();
$vitals = $result->fetch_assoc();
if (!$vitals) {
    fhd_abort(403, 'Forbidden');
}

$labstmt = $conn->prepare("SELECT * FROM lab_tests_catalog ORDER BY test_type, test_name");
$labstmt->execute();
$labresult = $labstmt->get_result();
    
?>
<!DOCTYPE html>
<html><head>
    <title>Start Consultation</title>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
   body {
        font-family: 'Segoe UI', sans-serif;
        margin: 0;
        padding: 0;
        background: #f4f4f4;
        display: flex;
        box-sizing: border-box;
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
    h2, h3 {
        color: #007bff;
        margin-bottom: 10px;
    }
    .section {
        background-color: #fff;
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }


    .container {
        max-width: 800px;
        margin: auto;
        padding: 20px;
        background: white;
        margin-top: 50px;
        border-radius: 8px;
    }
    label {
        display: block;
        margin-top: 10px;
    }
    .lab-request-block  label {
        display: inline;
    }
    select, textarea, input {
        width: 90%;
        padding: 0.6rem;
        margin: 0.3rem 1rem 0 0;
        border: 1px solid #ccc;
        border-radius: 5px;
    }
    .lab-request-block input {
        width: auto;
    }
    fieldset {
        border: 1px solid #ccc;
        padding: 10px;
        margin-bottom: 15px;
        border-radius: 6px;
        background-color: #f9f9f9;
    }
    .lab-request-block legend {
        font-weight: bold;
        color: #007bff;
    }
    button {
        margin-top: 15px;
        padding: 10px 15px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 8px;
    }
    button:hover {
        box-shadow: 5px 5px 5px grey;
    }
    .close {
        float: right;
        border: 1px solid red;
        border-radius: 4px;
        padding: 4px;
    }
    .close:hover {
        background-color: red;
        color: white;
    }
    details summary {
        padding: 6px;
        background-color: #e3f2fd;
        border-radius: 4px;
        margin-bottom: 4px;
    }
    .prescription-block input {
        margin-bottom: 10px;
    }

    #prescription-wrapper button {
        background-color: #28a745;
    }

    .next_appt {
        margin-top: 20px;
    }

    #slot_preview {
        color: #17a2b8;
    }

    .confirmation.success {
        color: green;
        font-weight: bold;
    }

    .confirmation.error {
        color: red;
        font-weight: bold;
    }

    /* Modal Styling */
    .modal {
        display: flex;
        align-items: center;
    justify-content: center;

        position: fixed;
        z-index: 999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
    }

    .modal-content {
        background-color: #fff;
        margin: 10% auto;
        padding: 20px;
        border-radius: 8px;
        width: 80%;
        max-width: 900px;
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        position: relative;
    }

    .modal-content .close {
        float: right;
        font-size: 18px;
        cursor: pointer;
        color: #dc3545;
        font-weight: bold;
    }

    #modal-body {
        margin-top: 20px;
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
            <li><a class="active" href="consultations.php">Consultations</a></li>
            <li><a href="patients.php">My Patients</a></li>
            <li><a href="labs.php">Labs</a></li>
            <li><a href="my_earnings.php">My Revenue</a></li>
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </div>
   <div class="container">
<h2>Consultation with: <?= htmlspecialchars($vitals['patient_name'] . ' Age: ' . $vitals['age']) . ' Gender: ' . ucfirst($vitals['gender']) ?></h2>
<div class="section">
    <h3>Latest Vitals</h3>
    <?php if ($vitals): ?>
       Weight: <?= $vitals['user_weight'] ?> kg<br>
       Height: <?= $vitals['user_height'] ?> cm<br>
       BMI: <?= $vitals['user_BMI'] ?><br>
       BP: <?= $vitals['blood_pressure_systolic'] ?>/ <?= $vitals['blood_pressure_diastolic'] ?> mmHg<br>
       Temperature: <?= $vitals['temperature'] ?> °C <br>
       Pulse:  <?= $vitals['heart_rate'] ?> bpm<br>
       Symptoms:  <?=  $vitals['symptoms'] ?><br>
    <?php endif; ?>

</div>
<div class="section" id="history">
    <h3>View History</h3>
    <button onclick="loadHistory('lab', <?=$vitals['subID']?>)">View Lab History</button>
    <button onclick="loadHistory('prescription', <?=$vitals['subID']?>)">View Prescription History</button>
</div>

<form action="submit_consultation.php" method="post">
    <?= fhd_csrf_field() ?>
    <input type="hidden" name="appointment_id" value="<?= (int) $appointment_id ?>">
    <div class="section">
        <h3>Diagnosis</h3>
        <label>Condition</label>
        <textarea name="diagnosis" required></textarea>
        <label for="visit_notes">Notes</label>
        <textarea name="visit_notes" id="visit_notes"></textarea>
    </div>

    <div class="section lab-request-block">
        <h3>Request Lab tests</h3>
        <?php
            /*retrieve and group tests by test type and display each set in 
            their own fieldset */
            $currenttype = '';
            while ($lablist = $labresult->fetch_assoc()) :
                if ($lablist['test_type'] !== $currenttype) {
                    if ($currenttype !== '') echo "</fieldset>";
                    $currenttype = $lablist['test_type'];
                    echo "<fieldset><legend><strong>{$currenttype}</strong></legend>";

                } echo "<label><input type='checkbox' name='labtests[]' value='{$lablist['testID']}'> {$lablist['test_name']}</label><br>";
            endwhile;
            if ($currenttype !== '') echo "</fieldset>";

        ?>
    </div>

    <div class="section prescription-block">
        <h3>Prescription</h3>
        <label>Drug Name</label><input type="text" name="drugname[]" id="drugname">
        <label>Strength</label><input type="text" name="drug_strength[]" id="drug_strength">
        <label>Dosage</label><input type="text" name="dosage[]" id="dosage_amount">
        <label>Frequency</label><input type="text" name="frequency[]" id="dosage_frequency">
        <label>Duration</label><input type="text" name="duration[]" id="dosage_duration">
                
        <div id="prescription-wrapper">
        <!--further prescription blocks addedd here-->
        <button type="button" onclick="addPrescription()">Add Another Drug</button><br>


    </div>
    </div>

    <div class="next_appt">
        <label for="next_interval">Schedule Next Appointment</label>
        <select name="next_interval" id="next_interval" onchange="fetchNextSlot()">
            <option value="">--Select Interval--</option>
            <option value="1 day">After 1 Day</option>
            <option value="1 week">After 1 Week</option>
            <option value="2 weeks">After 2 Weeks</option>
            <option value="1 month">After 1 Month</option>            
        </select>

        <p id="slot_preview" style="margin-top: 10px; font-weight: bold;"></p>

        <!--hidden inputs to store slots-->
        <input type="hidden" name="next_appt_date" id="next_appt_date">
        <input type="hidden" name="next_appt_time" id="next_appt_time">
    </div>
    
    <button type="submit">Submit Consultation</button>
</form>
</div>

<!--modal to display labs n previous prescription-->
<div id="historyModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="document.getElementById('historyModal').style.display='none'">Click this to close [&times;]</span>
        <div id="modal-body">Loading...</div>
    </div>
</div>
<script>
    //function to load history of labs and prescriptions
    function loadHistory(type, patientID) {
        const url = type === 'lab' ? 'fetch_lab_results.php' : 'fetch_prescriptions.php';
        fetch(`${url}?patient_id=${patientID}`)
        .then(res => res.text())
        .then(data => {
        document.getElementById('modal-body').innerHTML = data;
        document.getElementById('historyModal').style.display = 'block';

        });
    }

    let prescriptionCount = 1;
    //function to add another prescription input, afterend backtick

    function addPrescription() {
        const container = document.createElement("div");
        container.className = "prescription-block";
        prescriptionCount++;
        container.innerHTML = `
            <div style="font-weight: bold; margin-bottom: 10px;">
                Drug #${prescriptionCount}
                <button type="button" style="float: right;" onclick="this.closest('.prescription-block').remove()">Remove</button>
            </div>
            <label>Drug Name</label><input type="text" name="drugname[]" required>
            <label>Strength</label><input type="text" name="drug_strength[]" required>
            <label>Dosage</label><input type="text" name="dosage[]" required>
            <label>Frequency</label><input type="text" name="frequency[]" required>
            <label>Duration</label><input type="text" name="duration[]" required>
            `;
        document.getElementById("prescription-wrapper").appendChild(container);
    }
    function fetchNextSlot() {
        const interval = document.getElementById('next_interval').value;

        if (!interval) return;

        fetch(`get_next_slot.php?interval=${encodeURIComponent(interval)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('slot_preview').textContent =
                `Next Available: ${data.date} at ${data.time}`;
                document.getElementById('next_appt_date').value = data.date;
                document.getElementById('next_appt_time').value = data.time;
            } else {
                document.getElementById('slot_preview').textContent = "No Available Slots Found.";

            }
        });
    }

</script>
</body>
</html>