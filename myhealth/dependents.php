<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role(['patient', 'dependent']);

$guardianID = fhd_user_id();
$dependents = [];

//fetch dependents 
$depstmt = $conn->prepare("SELECT subID, fname, mname, lname, gender, dob FROM subscribers WHERE guardianID = ?");
$depstmt->bind_param("i", $guardianID);
$depstmt->execute();
$result = $depstmt->get_result();

while ($row = $result->fetch_assoc()) {
    $dependents[] = $row;
}

$addError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    fhd_verify_csrf();

    $fname = trim((string) ($_POST['dependent_fname'] ?? ''));
    $mname = trim((string) ($_POST['dependent_mname'] ?? ''));
    $lname = trim((string) ($_POST['dependent_lname'] ?? ''));
    $dob = trim((string) ($_POST['dob'] ?? ''));
    $gender = trim((string) ($_POST['gender'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phonenum = trim((string) ($_POST['phonenum'] ?? ''));

    $today = new DateTime('today');
    $dobDate = DateTime::createFromFormat('!d-m-Y', $dob);
    $dobErrors = DateTime::getLastErrors();
    $dobValid = $dobDate !== false
        && ($dobErrors === false || ($dobErrors['warning_count'] === 0 && $dobErrors['error_count'] === 0));
    $dobymd = $dobValid ? $dobDate->format('Y-m-d') : '';

    if ($fname === '' || $lname === '' || !$dobValid || $dobDate > $today
        || !in_array($gender, ['Male', 'Female'], true)
        || !preg_match('/^[0-9+()\-\s]{9,20}$/', $phonenum)) {
        $addError = 'Please enter valid dependent details.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $addError = 'Please enter a valid email address or leave it blank.';
    } else {
        // Fetch the guardian's region; this is the only detail copied.
        $guardianstmt = $conn->prepare('SELECT fname, lname, phone_no, region FROM subscribers WHERE subID = ? LIMIT 1');
        $guardianstmt->bind_param('i', $guardianID);
        $guardianstmt->execute();
        $guardianrow = $guardianstmt->get_result()->fetch_assoc();
        $guardianstmt->close();

        if (!$guardianrow) {
            fhd_abort(403, 'Forbidden');
        }

        $nextofkin = $guardianrow['fname'] . ' ' . $guardianrow['lname'];
        $nextofkin_phone = $guardianrow['phone_no'];
        $region = $guardianrow['region'];
        $sub_role = 'dependent';
        // Dependents do not inherit the guardian's password.
        $password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            INSERT INTO subscribers (fname, mname, lname, DOB, gender, phone_no, email, pwd, sub_role, nok_name, nok_phone_no, guardianID, region)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            'sssssissssiis',
            $fname, $mname, $lname, $dobymd, $gender, $phonenum,
            $email, $password, $sub_role, $nextofkin, $nextofkin_phone,
            $guardianID, $region
        );
        $stmt->execute();
        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html>
    <head>
      <title>Home</title>
      <meta http-equiv="content-type" content="text/html; charset=UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{
            font-family: 'Segoe UI', sans-serif; 
            background-color: #f9f9f9;
            margin: 0 0;
        }
        header {
            background-color: #17a2b8;
            margin: 0 0;
            padding: 0;
            width: 100%;
            border: 1px solid #17a2b8;
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
        .menu a.active {
            background-color: white;
            color: #17a2b8;
        }
        .intro {
            text-align: center;
        }
        form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            max-width:600px;
            margin: auto;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        label {
            display: block;
            margin-top: 1rem;
        }
        input, select {
        width: 100%;
        padding: 0.6rem;
        margin-top: 0.3rem;
        border: 1px solid #ccc;
        border-radius: 5px;
        }
        .mydependents {
            width: 80%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ddd;
        }
        th {
            background-color: #17a2b8;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }


    </style>



    <script>
    

    </script>

</head>

<body>
    <header>
      <nav class="menu">
        <ul class="navleft">
            <li><a href="myhome.php">Home</a></li>
            <li><a href="allergies_and_conditions.php">Allergies and Conditions</a></li>
            <li><a href="vitals.php">Vitals</a></li>
            <li><a href="appointments.php">Appointments</a></li>
            <li><a href="health_visits.php">Health Visit</a></li>
            <li><a href="labs.php">Labs</a></li>
            <li><a href="prescriptions.php">Prescriptions</a></li>
            <li><a class="active" href="dependents.php">Dependents</a></li>
            <li><a href="my_bills.php">My Bills</a></li>

        </ul>
        <ul class="navright">
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </header>
    <main>
        <section class="intro">
            <h1>Manage Dependents</h1>
            <?php if ($addError !== ''): ?>
                <p style="color:#b00020; font-weight:bold;"><?= fhd_e($addError) ?></p>
            <?php endif; ?>
        </section>
        <section class="add_dependent">
            <div class="addone">
                <form action="dependents.php" method="post">
                    <?= fhd_csrf_field() ?>
                    <h3>Add Dependent's Details</h3>
                    <label for="dependent_fname">First Name</label>
                    <input type="text" name="dependent_fname" id="dependent_fname" placeholder="First Name">
                    <label for="dependent_mname">Middle Name</label>
                    <input type="text" name="dependent_mname" id="dependent_mname" placeholder="Middle Name">
                    <label for="dependent_fname">Last Name</label>
                    <input type="text" name="dependent_lname" id="dependent_lname" placeholder="Last Name">
                    <label for="dob">Date of Birth</label>
                    <input type="text" name="dob" id="dob" placeholder="DD-MM-YYYY">
                    <label for="gender">Gender</label>
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                        <label for="phonenum">Phone Number</label>
                    <input type="text" name="phonenum" id="phonenum" placeholder="07XXXXXXXX">
                    <label for="email">Email Address</label>
                    <input type="text" name="email" id="email">

                    <input type="hidden" name="sub_role" value="dependent">
                    <input type="hidden" name="guardianID" value="<?= $guardianID ?>">
                    <input type="hidden" name="next_of_kin" value="<?= $nextofkin ?>">
                    <input type="hidden" name="next_of_kin_phoneno" value="<?= $nextofkin_phone ?>">
                    <input type="hidden" name="region" value="<?= $region ?>">
                    <input type="hidden" name="password" value="<?= $passsword ?>">
                    <button type="submit">Submit</button>
                </form>
            </div>
        </section>
        <section class="dependents">
            <div class="dependent">
                <h3>My Dependents</h3>
                <?php if (count($dependents) > 0): ?>
                     <table class="mydependents">
                            <thead>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Date of Birth</th>
                            </thead>
                    <?php foreach ($dependents as $d): ?>
                        <tr>
                            <td><?= $d['fname'] . ' ' . $d['mname'] . ' ' . $d['lname']; ?></td>
                            <td><?= htmlspecialchars($d['gender']) ?></td>
                            <td>
                                <?php $depdob = htmlspecialchars($d['dob']);
                                $dobstr = (new DateTime($depdob))->format('d M Y');
                                echo $dobstr;
                            ?>
                            </td>
                        </tr>                            
                        
                    <?php endforeach; ?>
                    </table>

                    <?php else: ?>
                    <p>No dependents registered</p>
                <?php endif; ?>
            </div>

        </section>
        
    </main>
    

</body>
</html>