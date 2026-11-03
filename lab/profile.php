<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role('labtech');

$subID = fhd_user_id();
$role = fhd_role();
$msg = '';
$pass_msg = '';

if (isset($_POST['update_profile'])) {
    fhd_verify_csrf();

    $fname = trim((string) ($_POST['first_name'] ?? ''));
    $mname = trim((string) ($_POST['middle_name'] ?? ''));
    $lname = trim((string) ($_POST['last_name'] ?? ''));
    $phone = trim((string) ($_POST['phone_no'] ?? ''));
    $region = trim((string) ($_POST['region'] ?? ''));
    $kinname = trim((string) ($_POST['next_of_kin_name'] ?? ''));
    $kinphone = trim((string) ($_POST['next_of_kin_phone'] ?? ''));
    $labname = trim((string) ($_POST['lab_name'] ?? ''));

    if ($fname === '' || $lname === '' || $region === '' || $labname === ''
        || !preg_match('/^[0-9+()\-\s]{9,20}$/', $phone)
        || !preg_match('/^[0-9+()\-\s]{9,20}$/', $kinphone)) {
        $msg = 'Please enter valid profile details.';
    } else {
        $stmt = $conn->prepare(
            'UPDATE subscribers
             SET fname = ?, mname = ?, lname = ?, phone_no = ?, region = ?, nok_name = ?, nok_phone_no = ?
             WHERE subID = ?'
        );
        $stmt->bind_param('sssissii', $fname, $mname, $lname, $phone, $region, $kinname, $kinphone, $subID);
        if ($stmt->execute()) {
            $msg = 'Profile updated successfully.';
        } else {
            $msg = 'Failed to update profile.';
        }
        $stmt->close();

        $labStmt = $conn->prepare(
            'UPDATE lab_technicians SET lab_name = ? WHERE labtechID_FK = ?'
        );
        $labStmt->bind_param('si', $labname, $subID);
        $labStmt->execute();
        $labStmt->close();
    }
}


//fetch user data
$user = [];
$extra = [];

$userStmt = $conn->prepare("SELECT * FROM subscribers WHERE subID=?");
$userStmt->bind_param("i", $subID);
$userStmt->execute();
$result = $userStmt->get_result();
$user = $result->fetch_assoc();

//Fetch extra data if labtech
if ($role === 'labtech') {
    $labExtraStmt = $conn->prepare("SELECT * FROM lab_technicians WHERE labtechID_FK=?");
    $labExtraStmt->bind_param("i", $subID);
    $labExtraStmt->execute();
    $extra = $labExtraStmt->get_result()->fetch_assoc();
    $labExtraStmt->close();
}
//Handle password change
if (isset($_POST['change_password'])) {
    fhd_verify_csrf();

    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (strlen($new) < 16) {
        $pass_msg = 'New password must be at least 16 characters.';
    } elseif ($new !== $confirm) {
        $pass_msg = 'New passwords do not match.';
    } else {
        $passStmt = $conn->prepare('SELECT pwd FROM subscribers WHERE subID = ? LIMIT 1');
        $passStmt->bind_param('i', $subID);
        $passStmt->execute();
        $passStmt->bind_result($hashed);
        $passStmt->fetch();
        $passStmt->close();

        if (!password_verify($current, (string) $hashed)) {
            $pass_msg = 'Current password is incorrect.';
        } else {
            $newHashed = password_hash($new, PASSWORD_DEFAULT);
            $newHashStmt = $conn->prepare('UPDATE subscribers SET pwd = ? WHERE subID = ?');
            $newHashStmt->bind_param('si', $newHashed, $subID);
            if ($newHashStmt->execute()) {
                $pass_msg = 'Password updated successfully.';
            } else {
                $pass_msg = 'Failed to update password.';
            }
            $newHashStmt->close();
        }
    }
}


$conn->close();

?>

<!DOCTYPE html>
<html>
    <head>
      <title>My Profile</title>
      <meta http-equiv="content-type" content="text/html; charset=UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
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

        body{
            font-family: 'Segoe UI', sans-serif; 
            background-color: #f9f9f9;
        }
        .intro {
            text-align: center;
        }
        form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            max-width:700px;
            margin: auto;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        label {
            display: block;
            margin-top: 1rem;
            font-weight: bold;
        }
        input, select {
        width: 100%;
        padding: 0.6rem;
        margin-top: 0.3rem;
        border: 1px solid #ccc;
        border-radius: 5px;
        }
        .success {
            color: green;
        }
    </style>



    <script>
    

    </script>

</head>

<body>
    <header>
      <nav class="menu">
         <ul class="navleft">
                <li><a class="active" href="dashboard.php">Dashboard</a></li>
                <li><a href="view_requests.php">View Pending Requests</a></li>
                <li><a href="enter_results.php">Enter Results</a></li>
                <li><a class="active" href="view_all_requests.php">View All Requests</a></li>

            </ul>
        <ul class="navright">
            <li><a class="active" href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </header>
    <main>
        <div class="intro">
            <h1>My Profile</h1>

        </div>
        <div class="container">
            <h2>Welcome, <?php echo htmlspecialchars($user['fname']); ?></h2>
            <?php if ($msg !== '') echo "<p class='success'>" . fhd_e($msg) . "</p>"; ?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <?= fhd_csrf_field() ?>
                <label for="first_name">First Name</label>
                <input type="text" name="first_name" value="<?= htmlspecialchars($user['fname']); ?>">
                <label for="middle_name">Middle Name</label>
                <input type="text" name="middle_name" value="<?= htmlspecialchars($user['mname']); ?>">
                <label for="last_name">Last Name</label>
                <input type="text" name="last_name" value="<?= htmlspecialchars($user['lname']); ?>">
                <label for="phone_no">Phone Number</label>
                <input type="text" name="phone_no" value="<?= htmlspecialchars($user['phone_no']); ?>">
                <label for="email">Email</label>
                <input type="text" name="email" disabled value="<?= htmlspecialchars($user['email']); ?>">
                <label for="region">Region</label>
                <select name="region" id="region">
                    <?php
                    $regions = ['Nairobi', 'Mombasa', 'Kisumu'];
                    foreach($regions as $r) {
                        $sel = $r == $user['region'] ? "selected" : "";
                        echo "<option $sel>$r</option>";
                    }
                    ?>
                </select>                
                <h4>Next Of Kin</h4>
                <label for="next_of_kin_name">Full Name</label>
                <input type="text" name="next_of_kin_name" value="<?= htmlspecialchars($user['nok_name']); ?>">
                <label for="next_of_kin_phone">Phone</label>
                <input type="text" name="next_of_kin_phone" value="<?= htmlspecialchars($user['nok_phone_no']); ?>">

                <?php if($role === 'doctor'): ?>
                    <label for="specialty">Specialty</label>
                    <input type="text" name="specialty" id="specialty" value="<?= htmlspecialchars($extra['specialty'] ?? ''); ?>">
                    <label for="hospital">Hospital</label>
                    <input type="text" name="hospital" id="specialty" value="<?= htmlspecialchars($extra['hospital'] ?? ''); ?>">
                <?php elseif($role === 'labtech'): ?>
                    <label for="lab_name">Lab Name</label>
                    <input type="text" name="lab_name" id="lab_name" value="<?= htmlspecialchars($extra['lab_name'] ?? ''); ?>">
                <?php endif; ?>

                <input type="submit" name="update_profile" value="Save Changes">

            </form>
            <hr>
            <h3>Change Password</h3>
            <?php if ($pass_msg !== '') echo "<p class='success'>" . fhd_e($pass_msg) . "</p>"; ?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                <?= fhd_csrf_field() ?>
                <label>Current Password</label>
                <input type="password" name="current_password" required>

                <label>New Password</label>
                <input type="password" name="new_password" required>

                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" required>

                <input type="submit" name="change_password" value="Update Password">
            </form>

        </div>
       
        
    </main>
    

</body>
</html>