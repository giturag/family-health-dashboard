<?php
require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);

// patient ID from session
$patientID = fhd_user_id();

// filters
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "
    SELECT b.billID, b.total_amount, b.created_at, b.status,
           hv.visit_date, d.fname AS doc_fname, d.lname AS doc_lname
    FROM bill_headers b
    JOIN health_visits hv ON b.hvisitID_FK = hv.hvisitID
    JOIN doctor_profiles dr ON hv.doctorID_FK = dr.docID_FK
    JOIN subscribers d ON dr.docID_FK = d.subID
    WHERE hv.patientID_FK = ?
";

$params = [$patientID];
$types = "i";

if ($from && $to) {
    $sql .= " AND DATE(b.created_at) BETWEEN ? AND ? ";
    $params[] = $from;
    $params[] = $to;
    $types .= "ss";
}

if ($status) {
    $sql .= " AND b.status = ? ";
    $params[] = $status;
    $types .= "s";
}

$sql .= " ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$bills = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Bills</title>
  <style>
    body{ font-family: 'Segoe UI', sans-serif; background:#f9f9f9; margin:0; }
    header{ background:#17a2b8; color:white; }
    .menu{ display:flex; }
    .navleft, .navright{ display:flex; list-style:none; margin:0; padding:0; }
    .navleft{ flex-grow:1; }
    .menu a{ color:white; padding:16px; text-decoration:none; display:inline-block; }
    .menu a.active{ background:white; color:#17a2b8; }
    table{ width:100%; border-collapse:collapse; margin-top:1rem; }
    th,td{ border:1px solid #ccc; padding:8px; text-align:left; }
    th{ background:#f5f5f5; }
    .paid{ color:green; font-weight:bold; }
    .unpaid{ color:red; font-weight:bold; }
    .card{ border:1px solid #ddd; padding:1rem; margin:0.5rem; border-radius:8px; flex:1; text-align:center; }
    .cards{ display:flex; gap:1rem; margin:1rem 0; }
    .btn{ padding:6px 12px; border:none; border-radius:4px; cursor:pointer; }
    .btn-view{ background:#007bff; color:white; }
    .btn-pay{ background:#28a745; color:white; }
  </style>
</head>
<body>
<header>
  <nav class="menu">
    <ul class="navleft">
      <li><a href="myhome.php">Home</a></li>
      <li><a href="allergies_and_conditions.php">Allergies</a></li>
      <li><a href="vitals.php">Vitals</a></li>
      <li><a href="appointments.php">Appointments</a></li>
      <li><a href="health_visits.php">Health Visit</a></li>
      <li><a href="labs.php">Labs</a></li>
      <li><a href="prescriptions.php">Prescriptions</a></li>
      <li><a href="dependents.php">Dependents</a></li>
      <li><a class="active" href="my_bills.php">My Bills</a></li>
    </ul>
    <ul class="navright">
      <li><a href="profile.php">My Profile</a></li>
      <li><a href="logout.php">Logout</a></li>
    </ul>
  </nav>
</header>

<main style="padding:1rem;">
  <h2>My Bills</h2>

  <!-- Filters -->
  <form method="get" class="filters">
    From: <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
    To: <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
    Status:
    <select name="status">
      <option value="">All</option>
      <option value="paid" <?= $status==="paid"?"selected":"" ?>>Paid</option>
      <option value="unpaid" <?= $status==="unpaid"?"selected":"" ?>>Unpaid</option>
    </select>
    <button type="submit">Filter</button>
  </form>

  <!-- Summary Cards -->
  <?php
  $total=0; $paid=0; $unpaid=0;
  foreach ($bills as $b) {
    $amt = $b['total_amount'];
    $total += $amt;
    if ($b['status']==='paid') $paid += $amt; else $unpaid += $amt;
  }
  ?>
  <div class="cards">
    <div class="card">Total: Ksh <?= number_format($total) ?></div>
    <div class="card">Paid: Ksh <?= number_format($paid) ?></div>
    <div class="card">Unpaid: Ksh <?= number_format($unpaid) ?></div>
  </div>

  <!-- Bills Table -->
  <table>
    <tr>
      <th>#</th>
      <th>Visit Date</th>
      <th>Doctor</th>
      <th>Amount (Ksh)</th>
      <th>Status</th>
      <th>Created</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($bills as $i=>$row): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><?= htmlspecialchars($row['visit_date']) ?></td>
      <td><?= htmlspecialchars($row['doc_fname']." ".$row['doc_lname']) ?></td>
      <td><?= number_format($row['total_amount']) ?></td>
      <td class="<?= $row['status']==='paid'?'paid':'unpaid' ?>"><?= ucfirst($row['status']) ?></td>
      <td><?= htmlspecialchars($row['created_at']) ?></td>
      <td>
        <button class="btn btn-view" onclick="showBreakdown(<?= $row['billID'] ?>)">Breakdown</button>
        <?php if ($row['status']==='unpaid'): ?>
        <form method="post" action="pay_bill.php" style="display:inline;">
          <?= fhd_csrf_field() ?>
          <input type="hidden" name="billID" value="<?= (int) $row['billID'] ?>">
          <button type="submit" class="btn btn-pay">Pay Bill</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

  <!-- Bill Breakdown Modal -->
  <div id="modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5);">
    <div style="background:#fff; padding:1rem; margin:5% auto; width:400px; border-radius:8px;">
      <h3>Bill Breakdown</h3>
      <!--details will be displayed in the div below-->
      <div id="modal-content"></div>
      <button onclick="document.getElementById('modal').style.display='none'">Close</button>
    </div>
  </div>
</main>

<script>
function showBreakdown(billID){
  fetch("bill_breakdown.php?billID="+billID)
    .then(r=>r.text())
    .then(html=>{
      document.getElementById("modal-content").innerHTML=html;
      document.getElementById("modal").style.display="block";
    });
}
</script>
</body>
</html>
