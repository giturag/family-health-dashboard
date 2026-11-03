<?php
//close connection
?>
<!DOCTYPE html>
<html>
<head>
    <title>Family Health Dashboard</title>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body{
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
        }
        header {
            background-color: #0069d9;
            margin: 0 0;
            padding: 0;
            width: 100%;
            border: 1px solid navy;
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
            padding: 10px;
        }
        .menu li {
            float: left;
            text-decoration: none;
            list-style-type: none;
        }
        .menu a {
            text-align: center;
            display: inline-block;
            color: white;
            padding: 16px 16px;
            text-decoration: none;
        }
        .signinbtn {            
                border: 1px solid navy;            
            }
        .signinbtn a {
            color: navy;
            background-color: white;
            border-radius: 15%;
        }

        .menu a:hover  {
            background-color:  #1967d2;

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
            
        .intro {
        padding: 3rem;
        text-align: center;
        background: white;
        }
        .intro h1 {
        font-size: 2.5rem;
        margin-bottom: 1rem;
        }
        .features {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 2rem;
        padding: 2rem;
        max-width: 1200px;
        margin: auto;
        }
        .feature {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        text-align: center;
        }
        .feature h3 {
        color: #007bff;
        }

    </style>
    

    

</head>

<body>
    <header>
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
    <section class="intro">
        <h1>Terms and Conditions</h1>
        <p>Terms</p>
    </section>
   
        <!--<div class="feature">
            <h3>Pharmacy</h3>
            <p>Process prescription scripts and dispense medication.</p>
        </div>-->
    </section>
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
            <p>&copy; <?= date('Y') ?> Family Health Dashboard. All Rights Reserved</p>
            </div>
            <div></div>
        </footer>

</body>
</html>