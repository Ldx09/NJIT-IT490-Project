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
$request['type'] = "getUserRecalls";
$request['username'] = $username;

$response = $client->send_request($request);

$recalls = array();

if (is_array($response) && isset($response["status"]) && $response["status"] === "ok" && isset($response["recalls"])) {
    $recalls = $response["recalls"];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Recalls</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <div class="card">
        <h2>My Recalls</h2>
        <p>Welcome <?php echo $username; ?></p>

        <div class="nav-links">
            <a class="btn" href="home.php">Back to Home</a>
            <a class="btn" href="garage.php">My Garage</a>
            <a class="btn" href="all_recalls.php">All Recalls</a>
            <a class="btn" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="card">
        <?php if (count($recalls) == 0) { ?>
            <p class="empty-message">Recalls NOT FOUND for your vehicle.</p>
        <?php } else { ?>
            <table>
        <tr>
                    <th>VIN</th>
                    <th>Make</th>
                    <th>Model</th>
                    <th>Trim</th>
                    <th>Year</th>
                    <th>NHTSA ID</th>
                    <th>Component</th>
                    <th>Summary</th>
                    <th>Recall Date</th>
                    <th>Status</th>
                    <th>Action</th>
        </tr>

        <?php foreach ($recalls as $r) { ?>
                    <tr>
                        <td><?php echo $r["vin"]; ?></td>
                        <td><?php echo $r["car_make"]; ?></td>
                        <td><?php echo $r["model"]; ?></td>
                        <td><?php echo $r["trim"]; ?></td>
                        <td><?php echo $r["year"]; ?></td>
                        <td><?php echo $r["nhtsa_id"]; ?></td>
                        <td><?php echo $r["component"]; ?></td>
                        <td><?php echo $r["summary"]; ?></td>
                        <td><?php echo $r["recall_date"]; ?></td>

                <td>
                            <?php if ($r["status"] === "completed") { ?>
                                <span class="success">Completed</span>
                            <?php } else { ?>
                                <span class="danger">Open</span>
                            <?php } ?>
                </td>

                        <td>
                            <?php if ($r["status"] !== "completed") { ?>
                                <form method="POST" action="complete_recall.php" style="margin:0;">
                                    <input type="hidden" name="vehicle_recall_id" value="<?php echo $r["vehicle_recall_id"]; ?>">
                                    <input class="btn" type="submit" value="Mark Complete">
                                </form>
                            <?php } else { ?>
                                <span class="success">Done</span>
                            <?php } ?>
                        </td>

                    </tr>

                <?php } 
                ?>
            </table>

        <?php } 
        ?>
    </div>
</div>

</body>
</html>