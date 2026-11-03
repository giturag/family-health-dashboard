<?php

require_once __DIR__ . '/../app/bootstrap.php';
fhd_require_role('pharmacist');


   
    
?>

<!DOCTYPE html>
<html><head>
    <title>Doctor Dashboard</title>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    header {
        background-color: #fd7e14;
        margin: 0 0;
        padding: 0;
        width: 100%;
        border: 1px solid #fd7e14;
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

    input[type=text]{position:relative;width: 72px;height:20px;padding-right:100px;}
    body{
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
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
            <li><a href="prescriptions.php">Prescriptions</a></li>
            <li><a href="dispense.php">Dispense</a></li>
        </ul>
        <ul class="navright">
            <li><a href="profile.php">My Profile</a></li>
            <li><a href="logout.php">Logout</a></li>              
        </ul>
      </nav>
    </header>
    <main>

    </main>

</body>
</html>