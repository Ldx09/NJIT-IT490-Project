<?php
session_start();

// session check
if (!isset($_SESSION["username"]) || !isset($_SESSION["session_key"])) {
    header("Location: index.html");
    exit(0);
}

$username = $_SESSION["username"];
$session_key = $_SESSION["session_key"];
session_write_close();

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

$request = array();
$request['type'] = "validate_session";
$request['session_key'] = $session_key;

$response = $client->send_request($request);

// if session invalid then kick out
if (!is_array($response) || !isset($response["status"]) || $response["status"] !== "ok") {
    session_start();
    session_destroy();
    header("Location: index.html");
    exit(0);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Home Page</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <div class="card">
        <h2>RecallShield Dashboard</h2>
        <p>Welcome <?php echo $username; ?></p>

        <div class="nav-links">
            <a class="btn" href="garage.php">My Garage</a>
            <a class="btn" href="recall.php">My Recalls</a>
            <a class="btn" href="all_recalls.php">All Recalls</a>
            <a class="btn" href="logout.php">Logout</a>
            <a class="btn" herf="appointments.php"> My appointments</a>
            <a class="btn" href="help_me_fix.php">Help me fix it</a>
        </div>
    </div>

    <div class="card">
        <h3>Quick Overview</h3>
        <p>Use the dashboard links above to view your saved vehicles, check your recalls, and browse all recalls in the system.</p>
    </div>
</div>

</body>
</html>
