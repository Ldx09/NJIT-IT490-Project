<?php
session_start();

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
$request['type'] = "getAllRecalls";

$response = $client->send_request($request);

$recalls = array();

if (is_array($response) && isset($response["status"]) && $response["status"] === "ok" && isset($response["recalls"])) {
    $recalls = $response["recalls"];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>All Recalls</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <div class="card">
        <h2>All Recalls</h2>
        <p>Welcome <?php echo $username; ?></p>

        <div class="nav-links">
            <a class="btn" href="home.php">Back to Home</a>
            <a class="btn" href="garage.php">My Garage</a>
            <a class="btn" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="card">
        <?php if (count($recalls) == 0) { ?>
            <p class="empty-message">No recalls found in system.</p>
        <?php } else { ?>
            <table>
                <tr>
                    <th>NHTSA ID</th>
                    <th>Make</th>
                    <th>Model</th>
                    <th>Year</th>
                    <th>Component</th>
                    <th>Summary</th>
                    <th>Recall Date</th>
                </tr>

                <?php foreach ($recalls as $r) { ?>
                    <tr>
                        <td><?php echo $r["nhtsa_id"]; ?></td>
                        <td><?php echo $r["make"]; ?></td>
                        <td><?php echo $r["model"]; ?></td>
                        <td><?php echo $r["year"]; ?></td>
                        <td><?php echo $r["component"]; ?></td>
                        <td><?php echo $r["summary"]; ?></td>
                        <td><?php echo $r["recall_date"]; ?></td>
                    </tr>
                <?php } ?>
            </table>
        <?php } ?>
    </div>
</div>

</body>
</html>