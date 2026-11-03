<?php

require_once __DIR__ . '/app/bootstrap.php';

$signupError = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    fhd_verify_csrf();

    $fname = trim((string) ($_POST['fname'] ?? ''));
    $mname = trim((string) ($_POST['mname'] ?? ''));
    $lname = trim((string) ($_POST['lname'] ?? ''));
    $dob = trim((string) ($_POST['dob'] ?? ''));
    $gender = trim((string) ($_POST['gender'] ?? ''));
    $phonenum = trim((string) ($_POST['phonenum'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $cnfmpassword = (string) ($_POST['cnfmpassword'] ?? '');
    $sub_role = strtolower(trim((string) ($_POST['sub_role'] ?? '')));
    $kinfname = trim((string) ($_POST['kinfname'] ?? ''));
    $kinphonenum = trim((string) ($_POST['kinphonenum'] ?? ''));
    $region = trim((string) ($_POST['region'] ?? ''));
    $tnc = isset($_POST['tnc']);
    $specialty = trim((string) ($_POST['specialty'] ?? ''));
    $hospital = trim((string) ($_POST['hospital'] ?? ''));
    $labname = trim((string) ($_POST['lab'] ?? ''));

    $allowedRoles = ['patient', 'doctor', 'labtech'];
    $today = new DateTime('today');
    $dobDate = DateTime::createFromFormat('!d-m-Y', $dob);
    $dobErrors = DateTime::getLastErrors();
    $dobValid = $dobDate !== false
        && ($dobErrors === false || ($dobErrors['warning_count'] === 0 && $dobErrors['error_count'] === 0));
    $dobymd = $dobValid ? $dobDate->format('Y-m-d') : '';

    if ($fname === '' || $lname === '' || $dobymd === '' || $gender === ''
        || $phonenum === '' || $email === '' || $password === ''
        || $kinfname === '' || $kinphonenum === '' || $region === '') {
        $signupError = 'Please fill in all required fields.';
    } elseif (!in_array($sub_role, $allowedRoles, true)) {
        $signupError = 'Please select a valid account type.';
    } elseif (!in_array($gender, ['Male', 'Female'], true)) {
        $signupError = 'Please select a valid gender.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $signupError = 'Please enter a valid email address.';
    } elseif (!$dobValid || $dobDate > $today) {
        $signupError = 'Date of birth must be a valid past date in DD-MM-YYYY format.';
    } elseif (!preg_match('/^[0-9+()\-\s]{9,20}$/', $phonenum)
        || !preg_match('/^[0-9+()\-\s]{9,20}$/', $kinphonenum)) {
        $signupError = 'Please enter valid phone numbers.';
    } elseif (strlen($password) < 16) {
        $signupError = 'Password must be at least 16 characters.';
    } elseif (!hash_equals($password, $cnfmpassword)) {
        $signupError = 'Passwords do not match.';
    } elseif (!$tnc) {
        $signupError = 'You must agree to the terms and conditions.';
    } elseif ($sub_role === 'doctor' && ($specialty === '' || $hospital === '')) {
        $signupError = 'Doctors must select a hospital and specialty.';
    } elseif ($sub_role === 'labtech' && $labname === '') {
        $signupError = 'Lab technicians must select a lab.';
    }

    if ($signupError === '') {
        $emailCheck = $conn->prepare('SELECT subID FROM subscribers WHERE email = ? LIMIT 1');
        $emailCheck->bind_param('s', $email);
        $emailCheck->execute();
        if ($emailCheck->get_result()->num_rows > 0) {
            $signupError = 'This email is already registered. Try logging in or use a different email.';
        }
        $emailCheck->close();
    }

    if ($signupError === '') {
        $pwd_hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Make failures throw so the transaction is rolled back.
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $conn->begin_transaction();

            $stmt = $conn->prepare(
                'INSERT INTO subscribers
                 (fname, mname, lname, DOB, gender, phone_no, email, pwd, sub_role, nok_name, nok_phone_no, region)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'sssssissssis',
                $fname, $mname, $lname, $dobymd, $gender, $phonenum,
                $email, $pwd_hash, $sub_role, $kinfname, $kinphonenum, $region
            );
            $stmt->execute();
            $subID = (int) $conn->insert_id;
            $stmt->close();

            if ($sub_role === 'doctor') {
                $docStmt = $conn->prepare(
                    'INSERT INTO doctor_profiles (specialty, hospital, docID_FK) VALUES (?, ?, ?)'
                );
                $docStmt->bind_param('ssi', $specialty, $hospital, $subID);
                $docStmt->execute();
                $docStmt->close();
            } elseif ($sub_role === 'labtech') {
                $labStmt = $conn->prepare(
                    'INSERT INTO lab_technicians (lab_name, labtechID_FK) VALUES (?, ?)'
                );
                $labStmt->bind_param('si', $labname, $subID);
                $labStmt->execute();
                $labStmt->close();
            }

            $conn->commit();

            session_regenerate_id(true);
            $_SESSION['subID'] = $subID;
            $_SESSION['loggedinusr'] = $email;
            $_SESSION['subfname'] = $fname;
            $_SESSION['sublname'] = $lname;
            $_SESSION['sub_role'] = $sub_role;

            switch ($sub_role) {
                case 'doctor':
                    fhd_redirect('./doctor/dashboard.php');
                case 'labtech':
                    fhd_redirect('./lab/dashboard.php');
                default:
                    fhd_redirect('./myhealth/myhome.php');
            }
        } catch (Throwable $e) {
            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
                // No active transaction.
            }
            error_log('Sign up failed: ' . $e->getMessage());
            $signupError = 'Sign up failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html>
    <head>
    <title>Sign Up</title>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    .container {
        display: grid;
        grid-template-areas: "header"
        "main"
        "footer";
        grid-template-rows: auto auto auto;
    }
     body {
        font-family: 'Segoe UI', sans-serif;
        background: #f9f9f9;
        color: #333;
        margin: 0;
    }
    header{
        background-color: #0069d9;
        margin: 0 0;
        padding: 0;
        width: 100%;
        border: 1px solid #0069d9;
        color: white;
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
        text-decoration: underline;
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
    .terms input {
        width: auto;
    }
    .facility-group {
        display: none;
    }

    footer{
        display: flex;  
        justify-content: space-around;
        background-color: #f1f1f1;
        padding: 2rem;
        margin-top: 3rem;
        flex-wrap: wrap;
    }
    footer li{
        list-style-type: none;
    }
    footer a {
        color: #333;
        text-decoration: none;
    }
    footer a:hover {
        text-decoration: underline;
    }
    .signup{
        padding-top: 50px;
        margin: auto;
        width:400px;
        padding: auto;
        box-sizing: border-box;
        }
    </style>

</head>

<body>
    <div class="container">
    <header>
        <!--h1 id="sitename">Family Health Dashboard</h1-->
        <nav class="menu">
            <ul class="navleft">
                <li><a href="index.php">family health dashboard</a></li>
                <li><a href="about.php">About</a></li>                    
                <li><a href="contact-us.php">Contact Us</a></li> 
            </ul>
        
            <ul class="navright">                   
                <li><a class="active" href="signup.php">Sign Up</a></li>                    
                <li class="signinbtn"><a href="signin.php">Sign In</a></li>
            </ul>
        </nav>

    </header>
    
<div class="signup">
    <form style="position:relative;width:100%;" name="user" method="post"
        action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" onsubmit="return validateForm()">
    <?= fhd_csrf_field() ?>
    <h1>Sign Up</h1>
    <?php if ($signupError !== ''): ?>
        <p class="error" style="color:#b00020; font-weight:bold;"><?= fhd_e($signupError) ?></p>
    <?php endif; ?>
    <label for="fname">First Name</label>
    <input type="text" id="fname" name="fname">
    <label for="mname">Middle Name</label>
    <input type="text" id="mname" name="mname">
    <label for="lname">Last Name</label>
    <input type="text" id="lname" name="lname">
    <label for="dob">Date of Birth</label>
    <input type="text" name="dob" id="dob" placeholder="DD-MM-YYYY">
    <label for="gender">Gender</label>
     <select name="gender" required>
      <option value="" disabled selected>Select Gender</option>
      <option value="Male">Male</option>
      <option value="Female">Female</option>
    </select>
    <label for="phonenum">Phone Number</label>
    <input type="text" name="phonenum" id="phonenum" placeholder="07XXXXXXXX">
    <label for="email">Email Address</label>
    <input type="text" name="email" id="email">
    <label for="password">Password</label>
    <input type="password" name="password" id="password">
    <label for="cnfmpassword">Confirm Password</label>
    <input type="password" name="cnfmpassword" id="cnfmpassword">
    <label for="sub_role">Account Type</label>
    <select name="sub_role" id="role" required onchange="toggleFacilities()">
      <option value="">Select Role</option>
      <option value="patient">Patient</option>
      <option value="doctor">Doctor</option>
      <option value="labtech">Lab Tech</option>
      <!--option value="pharmacist">Pharmacist</option-->
    </select>
    <h4>Emergency Contact</h4>
    <label for="kinfname">Next of Kin's Full name</label>
    <input type="text" name="kinfname" id="kinfname">
    <label for="kinphonenum">Next of Kin's Phone Number</label>
    <input type="text" name="kinphonenum" id="kinphonenum">

     <label>Region</label>
    <select name="region" required>
      <option value="" disabled selected>Select Region</option>
      <option value="Nairobi">Nairobi</option>
      <option value="Mombasa">Mombasa</option>
      <option value="Kisumu">Kisumu</option>
      <!-- Add more regions -->
    </select>

    <div id="hospitalSelect" class="facility-group">
      <label>Select Hospital</label>
      <select name="hospital">
        <option value="" disabled selected>Select Hospital</option>        
        <option value="Aga Khan Hospital">Aga Khan Hospital</option>
        <option value="Avenue Hospital">Avenue Hospital</option>
        <option value="Nairobi West">Nairobi West</option>
        <option value="Pwani Hospital">Pwani Hospital</option>
      </select>
    </div>
    <div id="specialtySelect" class="facility-group">
        <label for="specialty">Select Specialty</label>
        <select name="specialty" id="specialty">
            <option value="" disabled selected>Select Specialty</option>
            <option value="General Medicine">General Medicine</option>
            <option value="Cardiology">Cardiology</option>
            <option value="Chest Pulmonology">Chest/Pulmonology</option>
            <option value="Dermatology">Dermatology</option>
            <option value="Orthopedics">Orthopedics</option>
            <option value="Paediatrics">Paediatrics</option>
            <option value="Rheumatology">Rheumatology</option>
        </select>
    </div>

    <div id="labSelect" class="facility-group">
      <label>Select Lab</label>
      <select name="lab">
        <option value="" disabled selected>Select Lab</option>
        <option value="Lancet">Lancet</option>
        <option value="PathCare">PathCare</option>
      </select>
    </div>

    <div id="pharmacySelect" class="facility-group">
      <label>Select Pharmacy</label>
      <select name="pharmacy">
        <option value="" disabled selected>Select Pharmacy</option>
        <option value="Goodlife">Goodlife</option>
        <option value="Haltons">Haltons</option>
      </select>
    </div>
    <div id="terms" class="terms">
        <label for="tnc">
        <input type="checkbox" name="tnc" id="tnc">
        I agree to the <a href="terms-and-conditions.php">terms and conditions</a>
        </label>
        <input type="submit" value="Sign Up" text-align="center">
    </div>
    </form>
</div>
<footer>
            <div>
                <ul>
                    <li><h3>Quick Links</h3></li>
                    <li><a href="about.php">About</a></li>
                    <li><a href="terms-and-conditions.php">Terms and Conditions</a></li>
                    <li><a href="legal-policy.php">Legal</a></li>
                    <li><a href="privacy-policy.php">Privacy Policy</a></li>

                </ul>
            </div>
            <div>
                <ul>
                    <li><h3>Contact Us</h3></li>

                    <li><a href="tel:+254-734-000000">+254-734-000000</a></li>
                    <li><a href="mailto:admin@famhealthdash.com">admin@famhealthdash.com</a></li>
                    <li><a></a></li>
                    <li><a></a></li>

                </ul>

            </div>
            <div></div>
        </footer>
        <script>
            function validateForm() {
                // Trim and get value
                const val = id => document.getElementById(id)?.value.trim();
                // Check for empty text fields
                const requiredFields = ['fname', 'mname', 'lname', 'dob', 'phonenum', 'email', 'password', 'cnfmpassword', 'kinfname', 'kinphonenum'];  
                const fieldLabels = {
                    fname: "First Name",
                    mname: "Middle Name",
                    lname: "Last Name",
                    dob: "Date of Birth",
                    phonenum: "Phone Number",
                    email: "Email Address",
                    password: "Password",
                    cnfmpassword: "Confirm Password",
                    kinfname: "Next of Kin's Name",
                    kinphonenum: "Next of Kin's Phone Number"
                    };

                    for (let field of requiredFields) {
                    if (!val(field)) {
                        alert(`Please fill in the ${fieldLabels[field]}`);//sttring literal to replace directly
                        document.getElementById(field).focus();
                        return false;
                    }
                }

                /*validate the date of birth as follows:
                1. to be format dd-mm-yyyy
                2. DOB cannot be greater than today
                3. Whether it is empty
                */
                function valDate(){
                    //get the date entered
                    dobdate=document.getElementById("dob").value;
                    //check whether it is of the format dd-mm-yyyy and also if it is empty
                    if(dobdate.indexOf("-")==-1){
                        alert("Date must be entered and of the format (dd-mm-yyyy");
                        document.getElementById("dob").focus();
                        return false;
                    }
                    comps=dobdate.split("-");
                    //Check if date components are of correct length
                    if(comps[0].length<1 || comps[1]<1 || comps[2].length<4){
                        alert("Date must be of format (dd-mm-yyyy)");
                        document.getElementById("dob").focus();
                        return false;
                    }
                    /*Check that date components are integers. 
                    NaN is aJS reserved word indicating that a valueis not a number
                    isNaN() a global JS function that can be used to findout if a value is not a number*/
                    if(isNaN(comps[0])||isNaN(comps[1])||isNaN(comps[2])){
                        alert("Date components must be integers and must be of the format (dd-mm-yyyy");
                        document.getElementById("dob").focus();
                        return false;
                    }
                    //Validate the dob to be within the acceptable range
                    var today=new Date();//Today's date
                    //create a date using the enetered data
                    var givendob= new Date();
                    givendob.setFullYear(comps[2],comps[1] - 1,comps[0]);//setFullYear sets the year month and date yyyy,mm,dd
                    //JS months are zero indexed so subtract 1 from month entry
                    //compare the dates
                    if(givendob>today){
                        alert("Date of Birth cannot be greater than today.");
                        document.getElementById("dob").focus();
                        return false;
                    }

                }//End of Date of Birth Validation

                if (valDate() === false) {
                    return false;
                }

                // Validate gender
                const gender = document.querySelector('select[name="gender"]').value;
                if (!gender) {
                    alert("Please select your gender");
                    return false;
                }

                //Validate email
                const email=document.user.email.value;
                if(email.length==0 || email.indexOf("@")==-1 || email.indexOf(".")==-1){
                    alert("You must enter a valid email");
                    document.getElementById("email").focus();
                    return false;
                }

                //validate phone numbers
                const phonePattern = /^[0-9+()\-\s]{9,20}$/;
                const phonenumber=document.getElementById("phonenum").value.trim();
                if(!phonePattern.test(phonenumber)){
                alert("Please enter a valid phone number");
                return false;
                }

                //check if the next of kin's phonenumber is a number
                const kinphonenumber=document.getElementById("kinphonenum").value.trim();
                if(!phonePattern.test(kinphonenumber)){
                alert("Please enter a valid phone number");
                return false;
                }

                //Validate password
                const password=document.getElementById("password").value;
                if(password.length<16){
                    alert("Password must be at least 16 characters");
                    document.getElementById("password").focus();
                    return false;
                }

                //confirm passwords match
                const cnfmpwd=document.getElementById("cnfmpassword").value;
                const pwd=document.getElementById("password").value;
                if(pwd != cnfmpwd){
                    alert("Passwords do not match");
                    document.getElementById('cnfmpassword').focus();
                    return false;
                }

                //validate user role and conditional fields
                const role = val('role');
                if (!role) {
                    alert("Please select your account type");
                    document.getElementById('role').focus();
                    return false;
                }

                if (role === 'doctor') {
                    if (!val('hospital') || !val('specialty')) {
                    alert("Doctors must select a hospital and specialty");
                    return false;
                    }
                } else if (role === 'labtech') {
                    if (!val('lab')) {
                    alert("Lab Techs must select a lab");
                    return false;
                    }
                }

                // Validate region
                if (!document.querySelector('select[name="region"]').value) {
                    alert("Please select your region");
                    return false;
                }

                //validate terms and conditions checkbox
                const tnc = document.getElementById("tnc").checked;
                if(tnc==false){
                    alert("Kindly agree to the terms and conditions");
                    document.getElementById("tnc").focus();
                    return false;
                }

                return true;
            }//end of validate form
            
            
            
            
            

            //show facility group by role
            function toggleFacilities() {
                document.querySelectorAll('.facility-group').forEach(el => el.style.display = 'none');
                const role = document.getElementById('role').value;
                if (role === 'doctor') {
                    document.getElementById('hospitalSelect').style.display = 'block';
                    document.getElementById('specialtySelect').style.display = 'block';
                } else if (role === 'labtech')  {
                    document.getElementById('labSelect').style.display = 'block';
                } else if (role === 'pharmacist') {
                document.getElementById('pharmacySelect').style.display = 'block';
            }
            }
            


        </script>
</body>
</div>
</html>