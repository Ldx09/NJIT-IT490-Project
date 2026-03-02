<?php

session_start();

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: index.html");
    exit(0);
}

$username = $_POST["username"] ?? "";
 $password = $_POST["password"] ?? "";

//  validation
if ($username == "" || $password == "") {
    echo "Username and Password required.";
    exit(0);
}

// Temporary for testing 
if ($username == "admin" && $password == "123456") {
 $_SESSION["username"] = $username;
   header("Location: home.php");
    exit(0);
} else {
    echo "Login Failed.";
}

?>
