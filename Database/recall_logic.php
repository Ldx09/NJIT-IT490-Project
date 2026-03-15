<?php
// handles recall batch ingestion

//takes recall records
function ingestRecall($batch){

    echo"ingestRecall started\n";

  $connection= connectDB();
  if ($connection===null){
    return["status"=>"error"];
  }  


 foreach  ($batch as $row){

$nhtsa_id=$row ['nhtsa_id']??'';
$make= $row['make']?? '';
$model= $row['model']?? '';
$year= $row['year']?? '';
$component= $row['component']?? '';
$summary= $row['summary']?? '';
$recall_date= $row['recall_date']?? '';
$stmt=$connection->prepare("SELECT id FROM recalls WHERE nhtsa_id = ?");
if (!$stmt) return ["status" => "error"];

$stmt->bind_param("s",$nhtsa_id);
$stmt->execute();
$result= $stmt->get_result();

// if greater than zero. recall exists else insert
if ($result->num_rows >0){
    $existing= $result->fetch_assoc();
    $recall_id= $existing["id"];

} else{
$stmt= $connection-> prepare(" INSERT INTO recalls 
(nhtsa_id, make, model, year, component, summary, recall_date)
VALUES (?, ?, ?, ?, ?, ?, ?)");

$stmt->bind_param("sssisss", $nhtsa_id, $make, $model, 
$year, $component, $summary,
$recall_date );

$stmt->execute();
$recall_id= $connection->insert_id;


}

// find matching vehicles

$stmt= $connection-> prepare("SELECT id FROM vehicles
WHERE car_make = ? AND model = ? AND year = ?");

$stmt->bind_param("ssi", $make, $model, $year);
$stmt->execute();
$vehicleResult= $stmt->get_result();

while($vehicle= $vehicleResult->fetch_assoc()) {

    $vehicle_id= $vehicle["id"];
     //check if this vehicle has already this recall
     
     $check= $connection->prepare("SELECT id FROM vehicle_recalls 
     WHERE vehicle_id = ? AND recall_id = ?");

     $check->bind_param("ii", $vehicle_id, $recall_id);
     $check->execute();
     $checkResult = $check->get_result();
     if ($checkResult->num_rows>0){
         // do nothing
     }
else{
    // else insert it
    $insert= $connection->prepare("INSERT INTO vehicle_recalls (vehicle_id, recall_id, status)
    VALUES (?, ?, 'open') ");
   $insert->bind_param("ii", $vehicle_id, $recall_id);
    $insert->execute();
}

}
}
return ["status" => "ok"];


}

function getUserRecalls($username)
{
    $connection = connectDB();
    if ($connection === null) {
        return ["status" => "error"];
    }

    $stmt= $connection->prepare("SELECT vehicle_recalls.id AS vehicle_recall_id,
    vehicles.id AS vehicle_id, vehicles.vin,
    vehicles.car_make, vehicles.model, vehicles.`trim`, vehicles.color,
    vehicles.year, recalls.nhtsa_id, recalls.component, recalls.summary,
    recalls.recall_date, vehicle_recalls.status
    FROM users
    JOIN vehicles ON users.id = vehicles.user_id
    JOIN vehicle_recalls ON vehicles.id = vehicle_recalls.vehicle_id
    JOIN recalls ON vehicle_recalls.recall_id = recalls.id
    WHERE users.username = ?
    ORDER BY vehicles.id, recalls.recall_date DESC");

    if ($stmt === false) {
        $connection->close();
        return ["status" => "error"];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $stmt->close();
    $connection->close();

    return [
        "status" => "ok",
        "recalls" => $rows
    ];
}

function getAllRecalls(){

    $connection = connectDB();
    if ($connection === null){
        return ["status" => "error"];
    }

    $stmt = $connection->prepare("SELECT nhtsa_id, make, model, year, component, summary, recall_date
                                  FROM recalls
                                  ORDER BY recall_date DESC");

    if ($stmt === false){
        $connection->close();
        return ["status" => "error"];
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while($row = $result->fetch_assoc()){
        $rows[] = $row;
    }

    $stmt->close();
    $connection->close();

    return [
        "status" => "ok",
        "recalls" => $rows
    ];
}

function markRecallComplete($username, $vehicle_recall_id)
{
    $connection = connectDB();
    if ($connection === null) {
        return ["status" => "error"];
    }

    if ($username === '' || $vehicle_recall_id === '') {
        $connection->close();
        return ["status" => "error"];
    }

    
    $stmt = $connection->prepare("
        SELECT vehicle_recalls.id
        FROM vehicle_recalls
        JOIN vehicles ON vehicle_recalls.vehicle_id = vehicles.id
        JOIN users ON vehicles.user_id = users.id
        WHERE vehicle_recalls.id = ? AND users.username = ?
        LIMIT 1
    ");

    if ($stmt === false) {
        $connection->close();
        return ["status" => "error"];
    }

    $stmt->bind_param("is", $vehicle_recall_id, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        $connection->close();
        return ["status" => "error"];
    }

    //update status to complete
    $stmt = $connection->prepare("
        UPDATE vehicle_recalls
        SET status = 'completed'
        WHERE id = ?
    ");

    if ($stmt === false) {
        $connection->close();
        return ["status" => "error"];
    }

    $stmt->bind_param("i", $vehicle_recall_id);
    $ok = $stmt->execute();
    $stmt->close();
    $connection->close();

    if ($ok) {
        return ["status" => "ok"];
    } else {
        return ["status" => "error"];
    }
}
