<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["confirm"] ?? "";

    if ($username == "" || $password == "") {
        echo "All fields required.";
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

    require_once('path.inc');
    require_once('get_host_info.inc');
    require_once('rabbitMQLib.inc');

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $request = array();
    $request['type'] = "register";
    $request['username'] = $username;
    $request['password'] = $password;

    $response = $client->send_request($request);

    if (is_array($response) && isset($response["status"])) {

        if ($response["status"] === "ok") {
            echo "Registration successful";
            exit();
        }

        if ($response["status"] === "exists") {
            echo "Username already exists.";
            exit();
        }
    }

    echo "Registration failed.";
    exit();
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Register User</title>
</head>
<body>

<h2>Register</h2>

<form method="POST">
    Username:<br>
    <input type="text" name="username"><br><br>

    Password (minimum 6):<br>
    <input type="password" name="password"><br><br>

    Confirm Password:<br>
    <input type="password" name="confirm"><br><br>

    <input type="submit" value="Register">
</form>

<br>
<a href="index.html">Back to Login</a>

</body>
</html>
