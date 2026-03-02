<?php
session_start();

if (!isset($_SESSION["username"])) {
    header("Location: index.html");
    exit(0);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Home Page</title>
</head>
<body>

<h2>It works, Welcome <?php echo $_SESSION["username"]; ?></h2>


</body>
</html>
