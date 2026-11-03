<?php
require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);
fhd_require_post();
fhd_verify_csrf();

$patientID = fhd_user_id();
$billID = intval($_POST['billID'] ?? 0);

if ($billID <= 0) {
    fhd_abort(400, 'Invalid bill.');
}

// Verify that the bill belongs to this patient and is still unpaid.
$check = $conn->prepare(
    "SELECT b.billID
     FROM bill_headers b
     JOIN health_visits hv ON b.hvisitID_FK = hv.hvisitID
     WHERE b.billID = ? AND hv.patientID_FK = ? AND b.status = 'unpaid'
     LIMIT 1"
);
$check->bind_param('ii', $billID, $patientID);
$check->execute();
$allowed = (bool) $check->get_result()->fetch_row();
$check->close();

if (!$allowed) {
    fhd_abort(400, 'Invalid bill or already paid.');
}

$stmt = $conn->prepare(
    "UPDATE bill_headers SET status = 'paid', paid_date = NOW()
     WHERE billID = ? AND status = 'unpaid'"
);
$stmt->bind_param('i', $billID);
$stmt->execute();
$stmt->close();

fhd_redirect('my_bills.php?msg=Bill+paid+successfully');
