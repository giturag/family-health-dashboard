<?php

require_once __DIR__ . '/app/bootstrap.php';

$msg = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    fhd_verify_csrf();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $msg = 'Please fill both email and password fields!';
    } else {
        $stmt = $conn->prepare(
            'SELECT subID, fname, lname, sub_role, pwd, email
             FROM subscribers WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $msg = 'User not found.';
        } elseif (!password_verify($password, $user['pwd'])) {
            $msg = 'Invalid password.';
        } else {
            session_regenerate_id(true); // prevent session fixation
            $_SESSION['loggedinusr'] = $user['email'];
            $_SESSION['subID'] = (int) $user['subID'];
            $_SESSION['subfname'] = $user['fname'];
            $_SESSION['sublname'] = $user['lname'];
            $_SESSION['sub_role'] = strtolower((string) $user['sub_role']);

            // Redirect to the correct portal for this role.
            switch (fhd_role()) {
                case 'doctor':
                case 'specialtydr':
                    fhd_redirect('./doctor/dashboard.php');
                case 'labtech':
                    fhd_redirect('./lab/dashboard.php');
                case 'pharmacist':
                    fhd_redirect('./pharma/dashboard.php');
                case 'admin':
                    fhd_redirect('./admin/dashboard.php');
                default:
                    fhd_redirect('./myhealth/myhome.php');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Family Health Dashboard</title>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f9f9f9;
            margin: 0 0;
        }
        header, footer {
            background-color: #0069d9;
            margin: 0 0;
            padding: 0;
            width: 100%;
            border: 1px solid #0069d9;
            color: white;
        }
        #sitename {
            text-align: center;
            font-family: Arial, Helvetica, sans-serif;
            color: white;
            background-color: #0069d9;
        }
        .logo{
            height: 50px;
            width: 100px;
        }
        .menu {
            display: flex;
        }
        .navleft {
            text-decoration: none;
            list-style-type: none;
            display: flex;
            justify-content: flex-start;
            flex-grow: 1;

        }
        .navright {
            text-decoration: none;
            list-style-type: none;
            display: flex;
            justify-content: flex-end;
            margin-right: 10px;

        }
        .menu li {
            float: left;
            text-decoration: none;

        }
        .menu a {
            text-align: center;
            display: inline-block;
            font-weight: bold;
            color: white;
            padding: 16px 16px;
            text-decoration: none;
        }
        .signinbtn {
            
            border: 1px solid #0069d9;
            
        }
        .signinbtn a {
            color: #0069d9;
            background-color: white;
            border-radius: 15%;
        }

        .menu a:hover, footer a:hover {
            text-decoration: underline; 

        }
        .menu a.active {
            color: #0069d9;
            background-color: white;
            border: 2px solid #0069d9;
        }
        footer{
            display: flex;
            position: fixed;
            bottom: 0;
            justify-content: space-around;
        }
        footer li{
            list-style-type: none;
        }
        footer a {
            color: antiquewhite;
        }
        label {
            display: block;
            margin-top: 0.6rem;
        }
        input {
            width: 100%;
            padding: 0.6rem;
            margin-top: 0rem;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        input[type=password] {
            width: 100%;
            padding: 0.6rem;
            margin-top: 0.3rem;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        input[type=submit] {
            width: 50%;
            padding: 0.6rem;
            margin-top: 0.6rem;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        
        form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            max-width:600px;
            margin: auto;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        .loginusr{
            padding-top: 50px;
            margin: auto;
            width: 400px;
            padding: auto;
            box-sizing: border-box;
        }
       .intro {
            padding: 3rem;
            text-align: center;
            background: white;
        }
        .intro h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .msg {
            color: red;
        }

    </style>
    

    

</head>
<body>
    <header>
        <!--h1 id="sitename">Family Health Dashboard</h1-->
        <nav class="menu">
            <ul class="navleft">
                <li><a href="index.php">Family Health Dashboard</a></li>            
                <li><a href="about.php">About</a></li>                    
                <li><a href="contact-us.php">Contact Us</a></li> 
            </ul>
        
            <ul class="navright">
                <li><a href="signup.php">Sign Up</a></li>                    
                <li class="signinbtn"><a href="signin.php">Sign In</a></li>
            </ul>
        </nav>

    </header>
    <div class="loginusr">
       
        <div class="signin">
        <form style="position:relative;width:100%;" name="login" method="post"
        action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" onsubmit="return validateForm()">
            <?= fhd_csrf_field() ?>
            <h1>Sign In</h1>

            <p>New here?    <a href="signup.php">Create an account</a></p>
            <p class="msg"><?= isset($msg) ? htmlspecialchars($msg) : '' ?></p>
            <label for="email">Email Address</label><br>
            <input type="text" name="email" id="email" placeholder="Email"><br>
            <label for="password">Password</label><br>
            <input type="password" name="password" id="password" placeholder="Password"><br>
            <input type="submit" name="submitbtn" id="submitbtn" value="Sign In" text-align="center">
        </form>
        </div>
    </div>
    <script>
        function validateForm() {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim(); 

            if (email == '' || password == '') {
                alert('Please fill in both email and password');
                return false;
            }
            return true;
            
        }
    </script>
</body>
</html>