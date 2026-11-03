<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role('labtech');


$labtechID = $_SESSION['subID'];

//get labtech's region
$regionQuery = $conn->prepare("SELECT region FROM subscribers WHERE subID = ?");
$regionQuery->bind_param("i", $labtechID);
$regionQuery->execute();
$regionResult = $regionQuery->get_result();
$labRegion = $regionResult->fetch_assoc()['region'];
$regionQuery->close();


//fetch pending lab requests
$query = $conn->prepare("
    SELECT l.labtestID, p.subID, CONCAT(p.fname, ' ', p.lname) AS patient_name, hv.visit_date, l.patientID_FK
    FROM lab_requests l
    JOIN subscribers p ON l.patientID_FK = p.subID
    JOIN health_visits hv ON l.hvisitID_FK = hv.hvisitID
    WHERE p.region = ?
    AND l.labtestID IN (
        SELECT requestID_FK FROM lab_test_items WHERE test_date IS NULL
    )
    GROUP BY l.labtestID
    ORDER BY l.labtestID DESC
");
$query->bind_param("s", $labRegion);
$query->execute();
$requests = $query->get_result();

?>
<!DOCTYPE html>
<html>
<head>
  <title>View Requests</title>
  <style>
     body{
        font-family: 'Segoe UI', sans-serif;
        margin: 0;
        background-color: #f9f9f9;
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
      width: 80%; border-collapse: collapse; background: #fff;
     }
    th, td { 
      padding: 10px; border: 1px solid #ccc;
     }
    th { 
      background: #6f42c1; color: white; 
    }
    button { padding: 6px 12px; margin-right: 5px; border: none; border-radius: 4px; cursor: pointer; }
    .btn-collect { background-color: #28a745; color: white; }
    .btn-view { background-color: #007bff; color: white; }
    .modal {
      display: none; position: fixed; z-index: 1000;
      left: 0; top: 0; width: 100%; height: 100%;
      background-color: rgba(0,0,0,0.5);
    }
    .modal-content {
      background-color: #fff; margin: 10% auto; padding: 20px;
      border-radius: 8px; width: 60%; box-shadow: 0 0 10px #333;
    }
    .close {
      float: right; font-size: 24px; cursor: pointer;
    }

  </style>
</head>
<body>
     <header>
      <nav class="menu">
        <ul class="navleft">
              <li><a href="dashboard.php">Dashboard</a></li>
              <li><a class="active" href="view_requests.php">View Pending Requests</a></li>
              <li><a href="enter_results.php">Enter Results</a></li>
              <li><a href="view_all_requests.php">View All Requests</a></li>
              <li><a href="my_revenue.php">My Revenue</a></li>            

           
        </ul>
        <ul class="navright">
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </header>
  <h2>Pending Lab Requests</h2>
  <table>
    <tr>
      <th>Request ID</th>
      <th>Patient</th>
      <th>Request Date</th>
      <th>Actions</th>
    </tr>
    <?php while ($row = $requests->fetch_assoc()): ?>
      <tr>
        <td><?= $row['labtestID'] ?></td>
        <td><?= htmlspecialchars($row['patient_name']) ?></td>
        <td><?php
          $visitDate = date_create(htmlspecialchars($row['visit_date']));
          echo date_format($visitDate, "d-M-Y");
        ?></td>
        <td>
          <form method="post" action="collect_sample.php" onsubmit="return confirm('Are you sure you want to confirm sample collection for this request?');"
            style="display: inline;">
            <?= fhd_csrf_field() ?>

            <input type="hidden" name="labtestID" value="<?= (int) $row['labtestID'] ?>">
            <button class="btn-collect" type="submit">Collect Sample</button>
          </form>
            <button class="btn-view" onclick="viewPatient(<?= $row['patientID_FK'] ?>)">View Patient</button>
            <button class="btn-view" onclick="viewTests(<?= $row['labtestID'] ?>)">View Tests</button>
          </td>
      </tr>
    <?php endwhile; ?>
  </table>

  <!-- Patient Info Modal -->
  <div id="patientModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal('patientModal')">&times;</span>
      <div id="patientDetails"></div>
    </div>
  </div>

  <!-- Test Info Modal -->
  <div id="testModal" class="modal">
    <div class="modal-content">
      <span class="close" onclick="closeModal('testModal')">&times;</span>
      <div id="testDetails"></div>
    </div>
  </div>

  <script>
        function viewPatient(patientID) {
          fetch(`get_patient_info.php?patient_id=${patientID}`)
              .then(res => res.text())
              .then(html => {
                document.getElementById('patientDetails').innerHTML = html;
                document.getElementById('patientModal').style.display = 'block';
              });
        }

        function viewTests(labtestID) {
            fetch(`get_test_info.php?labtest_id=${labtestID}`)
                .then(res => res.text())
                .then(html => {
                  document.getElementById('testDetails').innerHTML = html;
                  document.getElementById('testModal').style.display = 'block';
                });
        }

        function closeModal(modalID) {
          document.getElementById(modalID).style.display = 'none';
        }

    </script>

</body>
</html>
