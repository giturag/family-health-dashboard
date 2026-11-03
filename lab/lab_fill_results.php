<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role('labtech');

$labtechID = fhd_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fhd_verify_csrf();

    $results = $_POST['results'] ?? [];
    if (!is_array($results)) {
        fhd_abort(400, 'Invalid results payload.');
    }

    $fillstmt = $conn->prepare(
        'UPDATE lab_test_items i
         JOIN lab_requests r ON i.requestID_FK = r.labtestID
         SET i.result_date = ?, i.test_result = ?, i.test_verdict = ?
         WHERE i.lbitemID = ? AND r.labtechID_FK = ?'
    );

    foreach ($results as $itemID => $data) {
        $itemID = intval($itemID);
        $testResult = trim((string) ($data['test_result'] ?? ''));
        $verdict = (string) ($data['test_verdict'] ?? '');

        if ($itemID <= 0 || $testResult === ''
            || !in_array($verdict, ['Normal', 'High', 'Low', 'Critical'], true)) {
            continue;
        }

        $resultDate = date('Y-m-d');
        $fillstmt->bind_param('sssii', $resultDate, $testResult, $verdict, $itemID, $labtechID);
        $fillstmt->execute();
    }
    $fillstmt->close();

    echo "Results submitted. <a href='enter_results.php'>Back</a>";
    exit;
}

$requestID = intval($_GET['labtestID'] ?? 0);

// The request must be assigned to the signed-in lab technician.
$metaQuery = $conn->prepare(
    'SELECT p.fname, p.lname, lt.labtestID,
            (SELECT MIN(test_date) FROM lab_test_items WHERE requestID_FK = lt.labtestID) AS collected_on
     FROM lab_requests lt
     JOIN subscribers p ON p.subID = lt.patientID_FK
     WHERE lt.labtestID = ? AND lt.labtechID_FK = ?
     LIMIT 1'
);
$metaQuery->bind_param('ii', $requestID, $labtechID);
$metaQuery->execute();
$metaResult = $metaQuery->get_result()->fetch_assoc();
$metaQuery->close();

if (!$metaResult) {
    fhd_abort(403, 'Forbidden');
}

$stmt = $conn->prepare(
    'SELECT i.lbitemID, tc.test_name, tc.test_type
     FROM lab_test_items i
     JOIN lab_tests_catalog tc ON i.testID_FK = tc.testID
     WHERE i.requestID_FK = ?'
);
$stmt->bind_param('i', $requestID);
$stmt->execute();
$result = $stmt->get_result();

$patientName = $metaResult['fname'] . ' ' . $metaResult['lname'];
$labtestID = (int) $metaResult['labtestID'];
$testDate = $metaResult['collected_on'] ? date('d-M-Y', strtotime($metaResult['collected_on'])) : 'Not Collected';


?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Enter Test Results</title>
        <style>
            body{
                font-family: 'Segoe UI', sans-serif; 
                background-color: #f9f9f9;
                margin: 0 0;
            }
            header {
                background-color: #6f42c1;
                margin: 0 0;
                padding: 0;
                width: 100%;
                border: 1px solid #6f42c1;
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
             .container {
                max-width: 800px;
                margin: auto;
                padding: 20px;
                background: white;
                margin-top: 50px;
                border-radius: 8px;
            }
            fieldset {
                border: 1px solid #ccc;
                padding: 10px;
                margin-bottom: 15px;
                border-radius: 6px;
                background-color: #f9f9f9;
            }
            legend {
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
            
            table { 
            width: 80%; border-collapse: collapse; background: #fff;
            }
            th, td { 
            padding: 10px; border: 1px solid #ccc;
            }
            th { 
            background: #6f42c1; color: white; 
            }
        </style>
    </head>
    <body>
        <header>
            <nav class="menu">
                <ul class="navleft">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="view_requests.php">View Requests</a></li>
                    <li><a class="active" href="enter_results.php">Enter Results</a></li>
                    <li><a href="view_all_requests.php">View All Requests</a></li>

                </ul>
                <ul class="navright">
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>              
                </ul>
            </nav>
        </header>
        <div class="container">

        
            <h2>Enter Lab Requests </h2>
            <div style="background:#f2f2f2; padding:10px; border-radius:6px; margin-bottom:20px;">
                <strong>Patient:</strong> <?= htmlspecialchars($patientName) ?><br>
                <strong>Request ID:</strong> <?= $labtestID ?><br>
                <strong>Sample Collected:</strong> <?= $testDate ?>
            </div>
            <form action="lab_fill_results.php" method="post" onsubmit="return confirm('Are you sure you want to submit these results?');">
                <?= fhd_csrf_field() ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <fieldset>
                        <legend><?= htmlspecialchars($row['test_name']) ?></legend>
                        <label for="">Result:</label>
                        <input type="text" name="results[<?=$row['lbitemID'] ?>][test_result]" required id="">
                        <label for="verdict">Verdict</label>
                        <select name="results[<?= $row['lbitemID']?>][test_verdict]" required id="">
                            <option value="Normal">Normal</option>
                            <option value="High">High</option>
                            <option value="Low">Low</option>
                            <option value="Critical">Critical</option>
                        </select>
                        <input type="hidden" name="results[<?= $row['lbitemID'] ?>][result_date]" value="<?= date('Y-m-d') ?>">

                    </fieldset>
                    <?php endwhile; ?>
                    <input type="submit" value="Submit Results">
            </form>
        </div>
        
    </body>
</html>