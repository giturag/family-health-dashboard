<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);

$userID =$_SESSION['subID'];
$dependents = [];
$patientIDs = [$userID];

//fetch dependents 
$depstmt = $conn->prepare("SELECT subID, fname, lname FROM subscribers WHERE guardianID = ?");
$depstmt->bind_param("i", $userID);
$depstmt->execute();
$result = $depstmt->get_result();

while ($row = $result->fetch_assoc()) {
    $dependents[] = $row;
    $patientIDs[] = $row['subID']; //append dependents to patients list
}

$patientIDs = array_map('intval', $patientIDs);

// Handle allergy and condition submissions.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fhd_verify_csrf();

    $formType = $_POST['form_type'] ?? '';
    $patientID = intval($_POST['patientID'] ?? 0);

    if (!in_array($patientID, $patientIDs, true)) {
        fhd_abort(403, 'Forbidden');
    }

    if ($formType === 'allergy') {
        $allergen = trim((string) ($_POST['allergen'] ?? ''));
        $severity = $_POST['severity'] ?? '';
        $allergyNotes = trim((string) ($_POST['notes'] ?? ''));
        if ($allergyNotes === '') {
            $allergyNotes = 'N/A';
        }

        if ($allergen !== '' && in_array($severity, ['Mild', 'Moderate', 'Severe', 'Critical', 'Unknown'], true)) {
            $stmt = $conn->prepare(
                'INSERT INTO allergy_records (allergen, patientID_FK, severity, notes) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('siss', $allergen, $patientID, $severity, $allergyNotes);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($formType === 'condition') {
        $condition = trim((string) ($_POST['condition'] ?? ''));
        $status = trim((string) ($_POST['condition_status'] ?? ''));
        $conditionNotes = trim((string) ($_POST['condition_notes'] ?? ''));
        if ($conditionNotes === '') {
            $conditionNotes = 'N/A';
        }

        if ($condition !== '' && in_array($status, ['Active', 'Resolved'], true)) {
            $stmt = $conn->prepare(
                'INSERT INTO chronic_condition_records (condition_name, patientID_FK, status, notes) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('siss', $condition, $patientID, $status, $conditionNotes);
            $stmt->execute();
            $stmt->close();
        }
    }
}
//placeholders for IN clause in the queries as ['?','?'...] then imploded to string
$placeholders = implode(',', array_fill(0, count($patientIDs), '?'));
$types = str_repeat('i', count($patientIDs)); //string of 'i's to use in bind_param


// Fetch allergies
$allergyQuery = "
    SELECT a.*, CONCAT(s.fname, ' ', s.lname) AS patient_name
    FROM allergy_records a
    JOIN subscribers s ON a.patientID_FK = s.subID
    WHERE a.patientID_FK IN ($placeholders)
    ORDER BY a.created_at DESC
";

$allergiesStmt = $conn->prepare($allergyQuery);
$allergiesStmt->bind_param($types, ...$patientIDs); //'...' splat operator to expand array into individual arguments
$allergiesStmt->execute();
$allergiesResult = $allergiesStmt->get_result();

// Fetch conditions
$conditionQuery = "
    SELECT c.*, CONCAT(s.fname, ' ', s.lname) AS patient_name
    FROM chronic_condition_records c
    JOIN subscribers s ON c.patientID_FK = s.subID
    WHERE c.patientID_FK IN ($placeholders)
    ORDER BY c.created_at DESC
";

$conditionsStmt = $conn->prepare($conditionQuery);
$conditionsStmt->bind_param($types, ...$patientIDs);
$conditionsStmt->execute();
$conditionsResult = $conditionsStmt->get_result();



?>

<!DOCTYPE html>
<html>
    <head>
      <title>Allergies and Conditions</title>
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
            text-decoration: underline; 
        }
        .menu a.active {
            background-color: white;
            color: #17a2b8;
        }
        .intro {
            text-align: center;
        }
        form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            max-width:600px;
            margin: auto;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        label {
            display: block;
            margin-top: 1rem;
        }
        input, select {
            width: 100%;
            padding: 0.6rem;
            margin-top: 0.3rem;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        textarea {
            border-radius: 5px;
        }
        .btn {
            cursor: pointer;
            padding: 6px 12px;
        }
        button[type='submit'] {
            margin-top: 0.6rem;
            display: block;
        }
        .btn-edit {
            background: #3498db;
            color: white;
            border: none;
            text-decoration: none;
        }
        .btn-delete {
            background: #e74c3c;
            color: white;
            border: none;
            text-decoration: none;
        }
        .btn-add {
            background: #2ecc71;
            color: white;
            border: none;
        }
        .close-btn {
            background: #e74c3c;
            color: white;
            cursor: pointer;
            float: right;
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
        .modal {
            display: none; /* Hide on page load */
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 20px;
            width: 80%;
            max-width: 500px;
            border-radius: 8px;
        }

    </style>



    <script>
    

    </script>

</head>

<body>
    <header>
      <nav class="menu">
        <ul class="navleft">
            <li><a href="myhome.php">Home</a></li>
            <li><a class="active" href="allergies_and_conditions.php">Allergies and Conditions</a></li>
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
            <h1>Allergies and  Conditions</h1>

        </section>
        <!--Allergies Form-->
        <section class="allergies">
            <div class="allergy">
                <form action="allergies_and_conditions.php" method="post">
                    <?= fhd_csrf_field() ?>
                    <h3>Add Allergen</h3>
                     <input type="hidden" name="form_type" value="allergy">
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
                    <label for="allergen">Allergen</label>
                    <input type="text" name="allergen" id="allergen" placeholder="Allergen Name">
                    <label for="severity">Severity</label>
                    <select name="severity" id="severity">
                        <option value="Mild">Mild</option>
                        <option value="Moderate">Moderate</option>
                        <option value="Severe">Severe</option>
                        <option value="Unknown">Unknown</option>

                    </select>
                    <label for="notes">Notes</label>
                    <textarea name="notes" id="notes" cols="50" rows="4"></textarea>
                    <button type="submit" class="btn btn-add">Add Allergy</button>
                </form>
            </div>
        </section>
        <!--Chronic Conditions form-->
        <section class="conditions">
           <div class="conditions">
            <br>
                <form method="post">
                    <?= fhd_csrf_field() ?>
                    <h3>Add Chronic Condition</h3>
                    <input type="hidden" name="form_type" value="condition">
                    <?php if (count($dependents) > 0): ?>
                        <label for="patientID">Select Patient: </label>
                        <select name="patientID" id="patientID" required>
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
                    <label for="condition">Condition</label>
                    <input type="text" name="condition" id="condition" placeholder="Condition Name" required>
                    <label for="condition_status">Status</label>
                    <select name="condition_status" id="condition_status">
                        <option value="Active">Active</option>
                        <option value="Resolved">Resolved</option>
                    </select>                    
                    
                    <label for="condition_notes">Notes</label>
                    <textarea name="condition_notes" id="condition_notes" cols="50" rows="4"></textarea>
                    

                    <button type="submit" class="btn btn-add">Add Condition</button>
                </form>
           </div>

        </section>
        <!-- Allergies Table -->
        <h3>Existing Allergies</h3>
        <table  cellspacing="0">
            <tr>
                <th>Patient</th>
                <th>Allergy</th>
                <th>Severity</th>
                <th>Notes</th>
                <th>Date Added</th>
                <th>Action</th>
            </tr>
            <?php if ($allergiesResult->num_rows > 0): ?>
                <?php while ($row = $allergiesResult->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['patient_name'])?></td>
                        <td><?= htmlspecialchars($row['allergen']) ?></td>
                        <td><?= htmlspecialchars($row['severity']) ?></td>
                        <td><?= htmlspecialchars($row['notes']) ?></td>
                        <td><?= date("d M Y", strtotime($row['created_at'])) ?></td>
                        <td>
                            <a href=# class="btn btn-edit"
                                onclick="openEditAllergyModal(<?= $row['allergyID']?>)"
                                data-id="<?= $row['allergyID']?>">Edit</a>
                                <form method="post" action="delete_allergy.php" style="display:inline"
                                    onsubmit="return confirm('Delete this allergy?')">
                                    <?= fhd_csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['allergyID'] ?>">
                                    <button type="submit" class="btn btn-delete" style="display:inline-block; margin-top:0;">Delete</button>
                                </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">No allergies recorded.</td></tr>
            <?php endif; ?>
        </table>

        <!-- Conditions Table -->
        <h3>Existing Conditions</h3>
        <table  cellspacing="0">
            <tr>
                <th>Patient</th>
                <th>Condition</th>
                <th>Status</th>
                <th>Notes</th>
                <th>Date Added</th>
                <th>Action</th>
            </tr>
            <?php if ($conditionsResult->num_rows > 0): ?>
                <?php while ($row = $conditionsResult->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['patient_name'])?></td>
                        <td><?= htmlspecialchars($row['condition_name']) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td><?= htmlspecialchars($row['notes']) ?></td>
                        <td><?= date("d M Y", strtotime($row['created_at'])) ?></td>
                        <td>
                           <a href="#" 
                                class="btn btn-edit" 
                                onclick="openEditConditionModal(<?= $row['conditionID'] ?>)" 
                                data-id="<?= $row['conditionID'] ?>">Edit</a>
                            <form method="post" action="delete_condition.php" style="display:inline"
                                onsubmit="return confirm('Delete this condition?')">
                                <?= fhd_csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $row['conditionID'] ?>">
                                <button type="submit" class="btn btn-delete" style="display:inline-block; margin-top:0;">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">No conditions recorded.</td></tr>
            <?php endif; ?>
        </table>

        <!-- modals for editing allergies and conditions-->
         <!-- Edit Allergy Modal -->
        <div id="editAllergyModal" class="modal" display="none">
            <div class="modal-content">                
                <form id="editAllergyForm">
                    <?= fhd_csrf_field() ?>
                    <span onclick="document.getElementById('editAllergyModal').style.display='none'"
                         class="close-btn">&times;</span>

                    <h3>Edit Allergy</h3>
                    <input type="hidden" name="id">
                    <label for="allergy_name">Allergen</label>
                    <input type="text" name="allergy_name" required>
                    <label for="severity">Severity</label>
                    <select name="severity" required>
                        <option value="Mild">Mild</option>
                        <option value="Moderate">Moderate</option>
                        <option value="Severe">Severe</option>
                        <option value="Critical">Critical</option>
                    </select>
                    <label for="notes">Notes</label>
                    <input type="text" name="notes">
                    <button type="submit" class="btn btn-add">Save</button>
                </form>
            </div>
        </div>

        <!-- Edit Condition Modal -->
        <div id="editConditionModal" class="modal">
            <div class="modal-content">                
                <form id="editConditionForm">
                    <?= fhd_csrf_field() ?>
                    <span onclick="document.getElementById('editAllergyModal').style.display='none'" 
                        class="close-btn">&times;</span>
                    <h3>Edit Condition</h3>
                    <input type="hidden" name="id">
                    <label for="condition_name">Condition Name</label>
                    <input type="text" name="condition_name" required>
                    <label for="status">Status</label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Resolved">Resolved</option>
                    </select>
                    <label for="notes">Notes</label>
                    <input type="text" name="notes">
                    <button type="submit" class="btn btn-add">Save</button>
                </form>
            </div>
        </div>

        
    </main>
    <script>
        function openEditAllergyModal(id) {
            fetch(`get_allergy.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                const form =  document.getElementById('editAllergyForm');
                 form.id.value = data.allergyID;
                form.allergy_name.value = data.allergen;
                form.severity.value = data.severity;
                form.notes.value = data.notes || '';
                document.getElementById('editAllergyModal').style.display = 'block';
            })
        }

        function openEditConditionModal(id) {
            fetch(`get_condition.php?id=${id}`)
                .then(res => res.json())
                .then(data => {
                    const form = document.getElementById('editConditionForm');
                    form.id.value = data.conditionID;
                    form.condition_name.value = data.condition_name;
                    form.status.value = data.status;
                    form.notes.value = data.notes || '';
                    document.getElementById('editConditionModal').style.display = 'block';
                });
        }
        // Close modals on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = "none";
            }
        }

        document.getElementById('editAllergyForm').addEventListener('submit', function(e) {
           e.preventDefault(); //prevent default form submission

           //collect form data
           const form = e.target;
           const formData = new FormData(form);

           //send data
           fetch('save_allergy.php', {
            method: 'POST',
            body: formData
           })
           .then(response => {
            if (!response.ok) throw new Error('Failed to save');
            return response.text();
           })
           .then(data => {
            alert('Allergy updated successfully!');
            closeModal('editAllergyModal');
            location.reload(); //refresh to show updated data
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to update allergy.');
            });        
        });

        document.getElementById('editConditionForm').addEventListener('submit', function(e) {
           e.preventDefault(); //prevent default form submission

           //collect form data
           const form = e.target;
           const formData = new FormData(form);

           //send data
           fetch('update_condition.php', {
            method: 'POST',
            body: formData
           })
           .then(response => {
            if (!response.ok) throw new Error('Failed to save');
            return response.text();
           })
           .then(data => {
            alert('COndition updated successfully!');
            closeModal('editConditionModal');
            location.reload(); // refresh to show updated data
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to update allergy.');
            });        
        });

    </script>

</body>
</html>