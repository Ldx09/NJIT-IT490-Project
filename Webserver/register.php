<?php

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["confirm"] ?? "";
    $vin      = $_POST["vin"] ?? "";
    $carMake  = $_POST["car_make"] ?? "";
    $model    = $_POST["model"] ?? "";
    $trim     = $_POST["trim"] ?? "";
    $color    = $_POST["color"] ?? "";
    $year     = $_POST["year"] ?? "";

    if ($username == "" || $password == "") {
echo 'All fields required.
<br><br><a href="index.html">Back to Login</a>';
        exit();
    }

    if (strlen($password) < 6) {
        echo "Password must be at least 6 characters.";
        exit();
    }

    if ($password != $confirm) {
        echo "Passwords do not match.";
        exit();
    }

    if ($vin == "" || $carMake == "" || $model == "" || $trim == "" || $color == "" || $year == "") {
        echo "Car info required.";
        exit();
    }

    require_once('path.inc');
    require_once('get_host_info.inc');
    require_once('rabbitMQLib.inc');

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $request = array();
    $request['type'] = "register";
    $request['username'] = $username;
    $request['password'] = $password;
    $request['vin'] = $vin;
    $request['car_make'] = $carMake;
    $request['model'] = $model;
    $request['trim'] = $trim;
    $request['color'] = $color;
    $request['year'] = $year;

    $response = $client->send_request($request);

    if (is_array($response) && isset($response["status"])) {
        if ($response["status"] === "ok") {
echo 'Registration successful.
<br><br><a href="index.html">Back to Login</a>';
            
            exit();
        }

        if ($response["status"] === "exists") {
echo 'Username already exists.
<br><br><a href="index.html">Back to Login</a>';
            exit();
        }
    }

echo 'Registration failed.
<br><br><a href="index.html">Back to Login</a>';
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register User</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <div class="card" style="max-width: 600px; margin: 40px auto;">
        <h2>RecallShield Register</h2>
        <p>Create your account and add your first vehicle to your garage.</p>

        <form method="POST">
            Username:<br><br>
            <input type="text" name="username"><br><br>

            Password (minimum 6):<br><br>
            <input type="password" name="password"><br><br>

            Confirm Password:<br><br>
            <input type="password" name="confirm"><br><br>

            VIN:<br><br>
            <input type="text" name="vin"><br><br>

            Car Make:<br><br>
            <input type="text" name="car_make"><br><br>

            Model:<br><br>
            <input type="text" name="model"><br><br>

            Trim Type:<br><br>
            <input type="text" name="trim"><br><br>

            Color:<br><br>
            <input type="text" name="color"><br><br>

            Year:<br><br>
            <input type="text" name="year"><br><br>

            <input class="btn" type="submit" value="Register">
        </form>

        <br>
        <a href="index.html">Back to Login</a>
    </div>
</div>

</body>
</html>
