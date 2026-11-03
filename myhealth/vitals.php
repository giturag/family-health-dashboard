<?php

require_once __DIR__ . '/../app/bootstrap.php';
//check if user is logged in
fhd_require_role(['patient', 'dependent']);

$userID =$_SESSION['subID'];
$dependents = [];
$results = [];

$periods = [
    'week' => 'Past Week',
    'month' => 'Past Month',
    'year' => 'Past Year',
    'all' => 'All Time'
];

$selectedUser = $userID;
$selectedPeriod = 'all';
$message = "";

//fetch dependents 
$depstmt = $conn->prepare("SELECT subID, fname, lname FROM subscribers WHERE guardianID = ?");
$depstmt->bind_param("i", $userID);
$depstmt->execute();
$res = $depstmt->get_result();

while ($row = $res->fetch_assoc()) {
    $dependents[] = $row;
}

$allowedIDs = array_merge([$userID], array_map('intval', array_column($dependents, 'subID')));

$feedback = "";

//Save vitals if form submitted
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'save') {
    fhd_verify_csrf();

    $dofr = date("Y-m-d");
    $weight = floatval($_POST["weight"] ?? 0);
    $height = floatval($_POST["height"] ?? 0);
    $bglucose = floatval($_POST["bglucose"] ?? 0);
    $systolic = intval($_POST["systolic"] ?? 0);
    $diastolic = intval($_POST["diastolic"] ?? 0);
    $pulse = intval($_POST["pulse"] ?? 0);
    $patientID = intval($_POST['patientID'] ?? $userID);
    $temperature = floatval($_POST["temperature"] ?? 0);
    $symptoms = trim((string) ($_POST["symptoms"] ?? ''));

    fhd_require_patient_access($conn, $patientID);

    if ($weight <= 0 || $height <= 0) {
        $feedback = "<div class='error'>Please enter a valid weight and height.</div>";
    } else {
        $bmi = (($weight * 10000) / ($height ** 2));

        // Use prepared statements to prevent SQL injection
        $stmt = $conn->prepare("INSERT INTO vitals (
            date_of_reading, user_weight, user_height, user_BMI,
            blood_pressure_systolic, blood_pressure_diastolic,
            heart_rate, blood_glucose, symptoms, subID_FK, temperature
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->bind_param(
            "sdddddddssi",
            $dofr,
            $weight,
            $height,
            $bmi,
            $systolic,
            $diastolic,
            $pulse,
            $bglucose,
            $symptoms,
            $patientID,
            $temperature
        );

        if ($stmt->execute()) {
            $feedback = "<div class='success'>Saved vitals successfully</div>";
        } else {
            $feedback = "<div class='error'>Unable to save vitals. Please try again.</div>";
        }

        $stmt->close();
    }
}
//Handle filter form for viewing vitals
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'filter') {
    $selectedUser = intval($_POST['patientID'] ?? $userID);
    if (!in_array($selectedUser, $allowedIDs, true)) {
        fhd_abort(403, 'Forbidden');
    }
    $selectedPeriod = in_array($_POST['period'] ?? '', array_keys($periods), true)
        ? $_POST['period']
        : 'all';
}

//Build filter query
$dateFilter = '';
switch ($selectedPeriod) {
    case 'week':
        $dateFilter = "AND date_of_reading >= CURDATE() - INTERVAL 7 DAY";
        break;
    case 'month':
        $dateFilter = "AND date_of_reading >= CURDATE() - INTERVAL 1 MONTH";
        break;
    case 'year':
        $dateFilter = "AND date_of_reading >= CURDATE() - INTERVAL 1 YEAR";
        break;
}
$periodsql = "SELECT * FROM vitals WHERE subID_FK = ? $dateFilter ORDER BY date_of_reading DESC";
$filterStmt = $conn->prepare($periodsql);
$filterStmt->bind_param("i", $selectedUser);
$filterStmt->execute();
$results = $filterStmt->get_result(); 

?>
<!DOCTYPE html>
<html lang="en">
    <head>
    <title>Vitals</title>
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
        .menu a.active {
            background-color: white;
            color: #17a2b8;
        }
        body {
            font-family: 'Segoe UI', sans-serif; 
            background-color: #f9f9f9;            
        }
        main {
            padding: 20px;
        }
        .intro {
            text-align: center;
        }
        .feedback {
            max-width: 600px;
            margin: 20px auto;
            padding: 10px 15px;
            border-radius: 6px;
            font-weight: bold;
            font-size: 1em;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        fieldset {
            border-radius: 12px;

        }
        .vitals-form {
        max-width: 600px;
        margin: 0 auto;
        font-family: 'Segoe UI', sans-serif;
        }

        .form-group {
        margin-bottom: 15px;
        display: flex;
        flex-direction: column;
        }

        label {
        font-weight: bold;
        margin-bottom: 5px;
        }
        legend {
            font-weight: bold;
        }

        input[type="text"],
        textarea {
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        }

        .form-actions {
        text-align: center;
        margin-top: 20px;
        }
        /*input[type="text"], select, textarea {
            padding: 6px;
            width: 100%;
            max-width: 300px;
        }*/

        button {
        padding: 10px 20px;
        background-color: #17a2b8;
        color: white;
        border: none;
        border-radius: 4px;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
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
        .high {
            background-color: #f8d7da;
        }
        .low {
            background-color: #fff3cd;
        }
       
    </style>
    


</head>

<body>
    <header>
      <nav class="menu">
        <ul class="navleft">
            <li><a href="myhome.php">Home</a></li>
            <li><a href="allergies_and_conditions.php">Allergies and Conditions</a></li>
            <li><a class="active" href="vitals.php">Vitals</a></li>
            <li><a href="appointments.php">Appointments</a></li>
            <li><a href="health_visits.php">Health Visit</a></li>
            <li><a href="labs.php">Labs</a></li>
            <li><a href="prescriptions.php">Prescriptions</a></li>
            <li><a href="dependents.php">Dependents</a></li>
        </ul>
        <ul class="navright">
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </header>

    <main>
    <div class="intro">
        <h1>Manage Your Vitals</h1>
    </div>   
     

    <?php if (!empty($feedback)): ?>
        <div class="feedback">
            <?= $feedback ?>
        </div>
    <?php endif; ?>

    <form method="post" class="vitals-form" onsubmit="return validateVitals()">
        <?= fhd_csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <fieldset>
            <legend>Vitals Entry</legend>
            <h2>Enter Vitals</h2>

            <div class="form-group">
                <?php if (count($dependents) > 0): ?>
                        <label for="patientID">Select Patient: </label>
                        <select name="patientID" id="patientID">
                            <option value="">---Select---</option>
                            <option value="<?= $userID ?>">Myself</option>
                            <?php foreach ($dependents as $d): ?>
                                <option value="<?= $d['subID'] ?>">
                                    <?= htmlspecialchars($d['fname'] . ' ' . $d['lname']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="hidden" name="patientID" value="<?= $userID ?>">
                    <?php endif; ?>
            </div>

            <div class="form-group">
            <label for="date">Date:</label>
            <input type="text" id="date" name="date" value="<?= date('d-M-Y') ?>" readonly>
            </div>

            <div class="form-group">
            <label for="weight">Weight (kg):</label>
            <input type="text" name="weight" id="weight">
            </div>

            <div class="form-group">
            <label for="height">Height (cm):</label>
            <input type="text" name="height" id="height" onchange="calculateBMI()">
            </div>

            <div class="form-group">
            <label for="bmi">Body Mass Index (BMI):</label>
            <input type="text" name="bmi" id="bmi" readonly>
            </div>

            <div class="form-group">
            <label for="temperature">Temperature (°C):</label>
            <input type="text" name="temperature" id="temperature">
            </div>

            <div class="form-group">
            <label for="bglucose">Blood Glucose (mmol/L):</label>
            <input type="text" name="bglucose" id="bglucose">
            </div>

            <fieldset>
            <legend>Blood Pressure</legend>
            <div class="form-group">
                <label for="systolic">Systolic(mmHg):</label>
                <input type="text" name="systolic" id="systolic">
            </div>
            <div class="form-group">
                <label for="diastolic">Diastolic(mmHg):</label>
                <input type="text" name="diastolic" id="diastolic">
            </div>
            <div class="form-group">
                <label for="pulse">Pulse(bpm):</label>
                <input type="text" name="pulse" id="pulse">
            </div>
            </fieldset>

            <div class="form-group">
            <label for="symptoms">Symptoms:</label>
            <textarea name="symptoms" id="symptoms" rows="5" cols="50"></textarea>
            </div>

            <div class="form-actions">
            <button type="submit">Save Vitals</button>
            </div>
        </fieldset>
    </form>

    <hr>
    <!--Form to select user and period to display vitals-->
    <form method="post">
        <input type="hidden" name="action" value="filter">
        <label for="patientID">Patient:</label>
         <select name="patientID">
            <option value="<?= $userID ?>" <?= $selectedUser == $userID ? 'selected' : '' ?>>Myself</option>
            <?php foreach($dependents as $dep): ?>
                <option value="<?= $dep['subID'] ?>" <?= $selectedUser == $dep['subID'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dep['fname'] . ' ' . $dep['lname']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="period">Period:</label>
        <select name="period" id="period">
            <?php /*loop over the periods array to set option-value as period-key and option-label as period-value   */
            foreach($periods as $key => $label): ?>
                <option value="<?= $key ?>" <?= $selectedPeriod == $key? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">View</button>

    </form>
    <!--output vita;s to table-->
    <h2>View Vitals</h2>
    <?php if ($results && $results->fetch_assoc() > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th><th>Weight (kg)</th><th>Height (cm)</th><th>BMI</th>
                    <th>Temp (&deg;C)</th><th>BP</th><th>Pulse (bpm)</th><th>Glucose</th><th>Symptoms</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $results->fetch_assoc()):
                    $bmi = floatval($row['user_BMI']);
                    $temp = floatval($row['temperature']);
                    $sys = floatval($row['blood_pressure_systolic']);
                    $dia = floatval($row['blood_pressure_diastolic']);   
                    $formattedDate = date('d-M-Y', strtotime($row['date_of_reading']));  //format date to have date start
                ?>
                <tr>
                    <td><?= htmlspecialchars(date('d-M-Y', strtotime($row['date_of_reading']))) ?></td>
                    <td><?= $row['user_weight'] ?></td>
                    <td><?= $row['user_height'] ?></td> 
                    <td class="<?= $bmi < 18.5 ? 'low' : ($bmi > 25 ? 'high' : '') ?>" ><?= number_format($bmi, 1)?></td>
                    <td class="<?= $temp < 36.0 ? 'low' : ($temp > 37.5 ? 'high' : '') ?>"><?= $temp ?></td>
                    <td class="<?= $sys > 130 || $dia > 90 ? 'high' : ''?>"><?= $sys ?>/<?= $dia?></td>
                    <td><?= $row['heart_rate'] ?></td>
                    <td><?= $row['blood_glucose'] ?></td>
                    <td><?= htmlspecialchars($row['symptoms']) ?></td>
                </tr>
                <?php endwhile;?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No vitals found for selected options</p>
    <?php endif; ?>
    </main>

    <script>
        function calculateBMI() {
        const weightInput = document.getElementById("weight");
        const heightInput = document.getElementById("height");
        const bmiOutput   = document.getElementById("bmi");

        const weight = parseFloat(weightInput.value);
        const heightCm = parseFloat(heightInput.value);
        const heightM = heightCm / 100;

        if (weight > 0 && heightM > 0) {
            const bmi = (weight / (heightM * heightM)).toFixed(1);
            bmiOutput.value = bmi;
        } else {
            bmiOutput.value = '';
        }
        }

        //validate numeric inputs        
        function isValidNumber(inputId, label) {
            const input = document.getElementById(inputId);
            const value = input.value.trim();
            if (value === "" || isNaN(value)) {
                alert(`Please enter a valid number for ${label}`);
                input.focus();
                return false;
            }
            return true;
        }

        function validateVitals() {
            return (
                isValidNumber("weight", "Weight") &&
                isValidNumber("height", "Height") &&
                isValidNumber("systolic", "Blood Pressure (Systolic)") &&
                isValidNumber("diastolic", "Blood Pressure (Diastolic)") &&
                isValidNumber("pulse", "Pulse")
            );
        }


    </script>
</body>
</html>