<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role('labtech');

$labtechID = $_SESSION['subID'];

//get collected sample requests 
$collectedquery = "
    SELECT DISTINCT lt.labtestID, CONCAT(p.fname, ' ', p.lname) AS patient_name, lt.test_centre, i.test_date, i.result_date, hv.visit_date
    FROM lab_requests lt
    JOIN subscribers p ON lt.patientID_FK = p.subID
    JOIN subscribers l ON lt.labtechID_FK = l.subID
    JOIN lab_test_items i ON lt.labtestID = i.requestID_FK
    LEFT JOIN health_visits hv ON lt.hvisitID_FK = hv.hvisitID
    WHERE p.region = l.region AND lt.labtechID_FK = ? AND i.test_date IS NOT NULL AND i.result_date IS NULL
    ORDER BY lt.labtestID DESC 
";

$stmt = $conn->prepare($collectedquery);
$stmt->bind_param("i", $labtechID);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <title>Enter Lab Results</title>
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
            
            table { 
                width: 80%; 
                border-collapse: collapse; 
                background: #fff;
            }
            th, td { 
                padding: 10px; 
                border: 1px solid #ccc;
            }
            th { 
                background: #6f42c1; 
                color: white; 
            }
            
        </style>
    </head>
    <body>
        <header>
            <nav class="menu">
                <ul class="navleft">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="view_requests.php">View Pending Requests</a></li>
                    <li><a class="active" href="enter_results.php">Enter Results</a></li>
                    <li><a href="view_all_requests.php">View All Requests</a></li>
                    <li><a href="my_revenue.php">My Revenue</a></li>            
                
                </ul>
                <ul class="navright">
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="logout.php">Logout</a></li>              
                </ul>
            </nav>
        </header>
        <h2>Enter Lab Results</h2>
        <table>
            <thead>
                <tr>
                    <th>Lab Request ID</th>
                    <th>Patient</th>
                    <th>Visit Date</th>                    
                    <th>Test Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['labtestID']) ?></td>
                    <td><?= htmlspecialchars($row['patient_name']) ?></td>
                    <td><?= date_format(date_create(htmlspecialchars($row['visit_date'])), 'd-M-Y') ?></td>
                    <td><?= date_format(date_create(htmlspecialchars($row['test_date'])), 'd-M-Y') ?></td>
                    <td><a href="lab_fill_results.php?labtestID=<?= $row['labtestID'] ?>">Enter Results</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        
    </body>
</html>