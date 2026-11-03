<?php

require_once __DIR__ . '/../app/bootstrap.php';
//display mysqli errors
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

//echo '<pre>'; print_r(array_keys($_SESSION)); echo '</pre>';

//check if user is logged in
fhd_require_role(['patient', 'dependent']);

//validate doctorID and date are set
if (!isset($_GET['doctor']) || (!isset($_GET['date']))) {
    echo "Missing required parameters";
    exit;
}

$doctorID = intval($_GET['doctor']);
$apptDate = $_GET['date'];

$allSlots = ["09:00:00","10:00:00","11:00:00","12:00:00","14:00:00","15:00:00","16:00:00"];

$stmt = $conn->prepare("SELECT time_slot FROM appointments WHERE doctorID_FK = ? AND appt_date = ?");
$stmt->bind_param("is", $doctorID, $apptDate);
$stmt->execute();
$result = $stmt->get_result();

$booked = [];

//get array of booked slots on slected day for selected doctor
while ($row = $result->fetch_assoc()) {
    $booked[] = $row['time_slot'];
}

$available = array_values(array_diff($allSlots, $booked));
sort($available);

header('Content-Type: application/json');
echo json_encode($available);

?>