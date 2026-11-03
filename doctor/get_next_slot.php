<?php
require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['doctor', 'specialtydr']);

$doctorID = fhd_user_id();
$interval = (string) ($_GET['interval'] ?? '1 week');
$allowedIntervals = ['1 week', '2 weeks', '1 month'];
if (!in_array($interval, $allowedIntervals, true)) {
    $interval = '1 week';
}

function getNextAvailableSlot(mysqli $conn, int $doctorID, string $interval): ?array
{
    $timeSlots = ['09:00:00', '10:00:00', '11:00:00', '12:00:00', '14:00:00', '15:00:00', '16:00:00'];
    $date = new DateTime();

    try {
        $date->modify($interval);
    } catch (Throwable $e) {
        $date->modify('1 week');
    }

    for ($checks = 0; $checks < 35; $checks++) {
        $day = $date->format('Y-m-d');
        foreach ($timeSlots as $slot) {
            $stmt = $conn->prepare(
                'SELECT COUNT(*) AS n FROM appointments
                 WHERE doctorID_FK = ? AND appt_date = ? AND time_slot = ?'
            );
            $stmt->bind_param('iss', $doctorID, $day, $slot);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ((int) ($row['n'] ?? 0) === 0) {
                return ['date' => $day, 'time' => $slot];
            }
        }
        $date->modify('+1 day');
    }

    return null;
}

$slot = getNextAvailableSlot($conn, $doctorID, $interval);

header('Content-Type: application/json; charset=UTF-8');
if ($slot) {
    echo json_encode(['success' => true, 'date' => $slot['date'], 'time' => $slot['time']]);
} else {
    echo json_encode(['success' => false, 'message' => 'No slots available in the selected range.']);
}
