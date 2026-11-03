<?php

require_once __DIR__ . '/../app/bootstrap.php';
//check if doctor is logged in
fhd_require_role(['doctor', 'specialtydr']);

$doctorID = $_SESSION['subID'];

// Get filters
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : 0;

$query = "
    SELECT v.hvisitID, v.visit_date, p.fname, p.lname, v.visit_notes
    FROM health_visits v
    JOIN subscribers p ON v.patientID_FK = p.subID
    WHERE v.doctorID_FK = ?
    AND YEAR(v.visit_date) = ?
";

$params = [$doctorID, $year];
$types = "ii";

if ($month > 0) {
    $query .= " AND MONTH(v.visit_date) = ?";
    $params[] = $month;
    $types .= "i";
}

$query .= " ORDER BY v.visit_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$consults = $stmt->get_result();

    
?>

<!DOCTYPE html>
<html><head>
    <title>Doctor Dashboard</title>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    body {
        font-family: 'Segoe UI', sans-serif;
        margin: 0;
        padding: 0;
        background: #f4f4f4;
        display: flex;
    }
    .sidebar {
        background-color: #28a745;
        width: 220px;
        height: 100vh;
        color: white;
    }  
    .navside {
        padding: 0;
        margin: 0;
    }  
    .navside li {
        list-style: none;
        display: block;
        margin-bottom: 5px;
    }
    .menu a {
        text-align: left;
        display: block;
        color: white;
        padding: 16px 16px;
        text-decoration: none;
    }
    .menu a:hover {
        text-decoration: underline; 
    }
    .menu a.active {
         background-color: rgba(255,255,255,0.2);
         font-weight: bold;

    }
    main {
        padding: 20px;
        flex-grow: 1;
    }
    .filter-bar {
        margin-bottom: 20px;
    }
    .card {
        background: #fff; 
        padding: 15px; 
        margin-bottom: 20px;         
        border-radius: 10px; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }    
    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 10px;
    }
    th, td {
        border: 1px solid #ccc;
        padding: 8px;
    }
    th {
        background-color: #007bff;
        color: white;
    }
    tbody tr:nth-child(even) td {
            background-color: #f1f7ff; /* soft blue */
    }

    tbody tr:nth-child(odd) td {
        background-color: #ffffff; /* pure white */
    }

    tbody tr:hover td {
        background-color: #e0ecff; /* highlight on hover */
        cursor: pointer;
    }
   .actions {
    margin-right: 10px;
    color: #007bff;
    text-decoration: none;
   }

    
</style>



<script>
 

</script>


</head>

<body>   
    <div class="sidebar">
         <nav class="menu">
          <ul class="navside">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="appointments.php">Appointments</a></li>
            <li><a class="active" href="consultations.php">Consultations</a></li>
            <li><a href="patients.php">My Patients</a></li>
            <li><a href="labs.php">Labs</a></li>
            <li><a href="my_earnings.php">My Revenue</a></li>
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </div>
    <main>
        <h2> My Consultations</h2>

        <div class="filter-bar">
            <form method="GET" action="consultations.php">
                <label for="year">Year:</label>
                <select id="year" name="year"></select>

                <label for="month">Month:</label>
                <select name="month" id="month">
                    <option value="0">All</option>
                    <?php
                    for ($m = 1; $m <= 12; $m++) {
                        $selected = ($month == $m) ? 'selected' : '';
                        echo "<option value='$m' $selected>" . date('F', mktime(0, 0, 0, $m, 10)) . "</option>";
                    }
                    ?>
                </select>

                <button type="submit">Filter</button>
            </form>
        </div>

        <table>
            <tr>
                <th>Date</th>
                <th>Patient</th>
                <th>Notes</th>
                <th>Action</th>
            </tr>
            <?php if ($consults->num_rows > 0): ?>
                <?php while ($row = $consults->fetch_assoc()): ?>
                <tr>
                    <td><?= date("d M Y", strtotime($row['visit_date'])) ?></td>
                    <td><?= htmlspecialchars($row['fname'] . ' ' . $row['lname']) ?></td>
                    <td><?= htmlspecialchars(substr($row['visit_notes'], 0, 50)) ?>...</td>
                    <td><a class="actions" href="view_consultation.php?id=<?= $row['hvisitID'] ?>">View</a></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4">No consultations found for selected period.</td></tr>
            <?php endif; ?>
        </table>

        <script>
        // Populate year dropdown dynamically
        const yearSelect = document.getElementById('year');
        const currentYear = new Date().getFullYear();
        const selectedYear = <?= $year ?>;

        for (let y = currentYear; y >= currentYear - 9; y--) {
            const opt = document.createElement('option');
            opt.value = y;
            opt.textContent = y;
            if (y === selectedYear) opt.selected = true;
            yearSelect.appendChild(opt);
        }
        </script>

       

    </main>

</body>
</html>