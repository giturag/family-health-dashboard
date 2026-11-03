<?php

require_once __DIR__ . '/../app/bootstrap.php';
//check if user is doctor
fhd_require_role(['doctor', 'specialtydr']);
fhd_require_post();
fhd_verify_csrf();

$doctorID = fhd_user_id();
$appointmentID = intval($_POST['appointment_id'] ?? 0);
$diagnosis = trim((string) ($_POST['diagnosis'] ?? ''));
$notes = trim((string) ($_POST['visit_notes'] ?? ''));
$labtests = array_values(array_unique(array_map('intval', (array) ($_POST['labtests'] ?? []))));
$doctorName = trim(($_SESSION['subfname'] ?? '') . ' ' . ($_SESSION['sublname'] ?? ''));

if ($appointmentID <= 0) {
    fhd_abort(400, 'Missing appointment ID.');
}

// The appointment must belong to the signed-in doctor.
$ptstmt = $conn->prepare('SELECT patientID_FK FROM appointments WHERE apptID = ? AND doctorID_FK = ? LIMIT 1');
$ptstmt->bind_param('ii', $appointmentID, $doctorID);
$ptstmt->execute();
$ptstmt->bind_result($patientID);
$foundPatient = (bool) $ptstmt->fetch();
$ptstmt->close();

if (!$foundPatient) {
    fhd_abort(403, 'Forbidden');
}

//transaction for multiple inserts
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->begin_transaction();

try {
    //insert into health visit table
    $insertVisit = $conn->prepare("INSERT INTO health_visits (appointmentID_FK, doctorID_FK, patientID_FK, diagnosis, visit_notes, visit_date) 
        VALUES (?,?,?,?,?, NOW())");
    $insertVisit->bind_param("iiiss", $appointmentID, $doctorID, $patientID, $diagnosis, $notes);
    $insertVisit->execute();
    $hvisitID = $insertVisit->insert_id;
    $insertVisit->close();

    //insert lab request and lab test items if any
    if (!empty($labtests)) {
        $labstmt = $conn->prepare("INSERT INTO lab_requests (hvisitID_FK, doctorID_FK, patientID_FK) VALUES (?, ?, ?)");
        $labstmt->bind_param("iii", $hvisitID, $doctorID, $patientID);
        $labstmt->execute();
        $requestID = $labstmt->insert_id;
        $labstmt->close();

        //loop to insert array of tests
        $testitemsstmt = $conn->prepare("INSERT INTO lab_test_items (requestID_FK, testID_FK) VALUES (?, ?)");
        foreach ($labtests as $testID) {
            $testitemsstmt->bind_param("ii", $requestID, $testID);
            $testitemsstmt->execute();
        }
        $testitemsstmt->close();
    }

    //insert prescription if included
    if (!empty($_POST['drugname'])) {
        $presstmt = $conn->prepare("INSERT INTO prescriptions (prescribe_date, patientID_FK, doctorID_FK, hvisitID_FK) 
            VALUES (CURDATE(), ?, ?, ?)");
        $presstmt->bind_param("iii", $patientID, $doctorID, $hvisitID);
        $presstmt->execute();
        $presID = $presstmt->insert_id;
        $presstmt->close();

        $drugNames = $_POST['drugname'];
        $strengths = $_POST['drug_strength'];
        $dosages = $_POST['dosage'];
        $frequencies = $_POST['frequency'];
        $durations = $_POST['duration'];
        //insert prescription items/medicines into prescription items table
        $presItemstmt = $conn->prepare("INSERT INTO prescription_items (
            presID_FK, drug_name, strength, dosage_amt, dosage_frequency, dosage_duration) 
            VALUES (?, ?, ?, ?, ?, ?)");
            for ($i = 0; $i < count($drugNames); $i++) {
                $presItemstmt->bind_param("isssss", 
                    $presID, $drugNames[$i], $strengths[$i], $dosages[$i], $frequencies[$i], $durations[$i] );
                $presItemstmt->execute();
            }
            $presItemstmt->close();
    }


    //schedule next appointment and update current appointment as completed
    $nextDate = $_POST['next_app_date'] ?? '';
    $nextTime = $_POST['next_appt_time'] ?? '';

    if ($nextDate && $nextTime) {
        $status = "Scheduled";
        $apptstmt = $conn->prepare("INSERT INTO appointments (patientID_FK, doctorID_FK, appt_date, time_slot, status) VALUES (?, ?, ?, ?, ?)");
        $apptstmt->bind_param("iisss", $patientID, $doctorID, $nextDate, $nextTime, $status);
        $apptstmt->execute();
        $apptstmt->close();
    }
    //update appoointment status for current appointment
    $updateAppt = $conn->prepare("UPDATE appointments SET status = 'Completed' WHERE apptID = ?");
    $updateAppt->bind_param("i", $appointmentID);
    $updateAppt->execute();
    $updateAppt->close();

    //insert consultation bill
    $bill_amount = 1000;
    $bill_type = "Consultation";
    $billstmt = $conn->prepare("INSERT INTO bill_headers (patientID_FK, hvisitID_FK, total_amount, description, created_at) VALUES (?, ?, ?, ?, NOW())");
    $billstmt->bind_param("iiis", $patientID, $hvisitID, $bill_amount, $bill_type);
    $billstmt->execute();
    $billID = $conn->insert_id; // Capture the new billID
    $billstmt->close();

    //insert consultation item
    $desc = $doctorName !== ''
        ? "Consultation Fee for Dr. {$doctorName} visit."
        : 'Consultation Fee';
    $amount = 1000;
    $itemstmt = $conn->prepare("INSERT INTO bill_items (billID_FK, description, amount) VALUES (?, ?, ?)");
    $itemstmt->bind_param("isd", $billID, $desc, $amount);//billID fetched using insert_id above
    $itemstmt->execute();
    $itemstmt->close();

    //commit if all succeed
    $conn->commit();
    fhd_redirect('appointments.php');
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
        // No active transaction.
    }
    error_log('Consultation save failed: ' . $e->getMessage());
    fhd_abort(500, 'Unable to save the consultation. Please try again.');
}

$conn->close();
?>

