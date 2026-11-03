<?php
require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role('labtech');

$labtechID = fhd_user_id();
$pid = intval($_GET['patient_id'] ?? 0);

$sql = "
    SELECT s.fname, s.lname, s.dob, s.gender, s.phone_no, s.email
    FROM subscribers s
    JOIN subscribers me ON me.subID = ?
    WHERE s.subID = ? AND s.region = me.region
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $labtechID, $pid);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$result) {
    fhd_abort(403, 'Forbidden');
}
?>
<div style="background:#fff; padding:20px; border-radius:8px;">
    <?php
        echo "<h3>Patient Info</h3>";
        echo "<p><strong>Name:</strong> " . fhd_e($result['fname'] . ' ' . $result['lname']) . "</p>";
        echo "<p><strong>DOB:</strong> " . fhd_e(date('d-M-Y', strtotime((string) $result['dob']))) . "</p>";
        echo "<p><strong>Gender:</strong> " . fhd_e(ucfirst((string) $result['gender'])) . "</p>";
        echo "<p><strong>Phone:</strong> " . fhd_e($result['phone_no']) . "</p>";
        echo "<p><strong>Email:</strong> " . fhd_e($result['email']) . "</p>";
    ?>
</div>
