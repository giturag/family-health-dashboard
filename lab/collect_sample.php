<?php
require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role('labtech');
fhd_require_post();
fhd_verify_csrf();

$labtestID = intval($_POST['labtestID'] ?? 0);
$labtechID = fhd_user_id();

if ($labtestID <= 0) {
    fhd_abort(400, 'Invalid lab request.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn->begin_transaction();

    // Lock the request and verify it belongs to this lab tech's region,
    // is not already assigned to someone else, and still has uncollected items.
    $accessStmt = $conn->prepare(
        'SELECT lr.labtestID, lr.patientID_FK, lr.hvisitID_FK
         FROM lab_requests lr
         JOIN subscribers p ON p.subID = lr.patientID_FK
         JOIN subscribers me ON me.subID = ?
         WHERE lr.labtestID = ?
           AND p.region = me.region
           AND (lr.labtechID_FK IS NULL OR lr.labtechID_FK = ?)
           AND EXISTS (
               SELECT 1 FROM lab_test_items lti
               WHERE lti.requestID_FK = lr.labtestID AND lti.test_date IS NULL
           )
         FOR UPDATE'
    );
    $accessStmt->bind_param('iii', $labtechID, $labtestID, $labtechID);
    $accessStmt->execute();
    $request = $accessStmt->get_result()->fetch_assoc();
    $accessStmt->close();

    if (!$request) {
        $conn->rollback();
        fhd_abort(403, 'Forbidden or already collected.');
    }

    $patientID = (int) $request['patientID_FK'];
    $hvisitID = (int) $request['hvisitID_FK'];

    // Record the lab technician who collected the sample.
    $stmt = $conn->prepare('UPDATE lab_requests SET labtechID_FK = ? WHERE labtestID = ?');
    $stmt->bind_param('ii', $labtechID, $labtestID);
    $stmt->execute();
    $stmt->close();

    // Count the tests that are still waiting for a sample.
    $countStmt = $conn->prepare(
        'SELECT COUNT(*) FROM lab_test_items WHERE requestID_FK = ? AND test_date IS NULL'
    );
    $countStmt->bind_param('i', $labtestID);
    $countStmt->execute();
    $countStmt->bind_result($itemCount);
    $countStmt->fetch();
    $countStmt->close();

    // Stamp the collection date.
    $updateItems = $conn->prepare(
        'UPDATE lab_test_items SET test_date = CURDATE() WHERE requestID_FK = ? AND test_date IS NULL'
    );
    $updateItems->bind_param('i', $labtestID);
    $updateItems->execute();
    $updateItems->close();

    // Insert the lab bill (Ksh 500 per test item).
    if ($itemCount > 0) {
        $lab_amount = $itemCount * 500;
        $bill_type = 'Lab Test';
        $billstmt = $conn->prepare(
            'INSERT INTO bill_headers
             (patientID_FK, hvisitID_FK, lab_testID_FK, total_amount, description, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $billstmt->bind_param('iiids', $patientID, $hvisitID, $labtestID, $lab_amount, $bill_type);
        $billstmt->execute();
        $billID = (int) $conn->insert_id;
        $billstmt->close();

        $testQuery = $conn->prepare(
            'SELECT tc.test_name, tc.test_type
             FROM lab_test_items lti
             JOIN lab_tests_catalog tc ON tc.testID = lti.testID_FK
             WHERE lti.requestID_FK = ?'
        );
        $testQuery->bind_param('i', $labtestID);
        $testQuery->execute();
        $testResults = $testQuery->get_result();

        $itemstmt = $conn->prepare(
            'INSERT INTO bill_items (billID_FK, description, amount) VALUES (?, ?, ?)'
        );

        while ($test = $testResults->fetch_assoc()) {
            $desc = $test['test_name'] . ' (' . $test['test_type'] . ')';
            $amount = 500.00;
            $itemstmt->bind_param('isd', $billID, $desc, $amount);
            $itemstmt->execute();
        }

        $itemstmt->close();
        $testQuery->close();
    }

    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
        // No active transaction.
    }
    error_log('Sample collection failed: ' . $e->getMessage());
    fhd_abort(500, 'Unable to record sample collection. Please try again.');
}

fhd_redirect('view_requests.php?status=collected');
