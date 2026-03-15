<?php
// connect to mysql
function connectDB() {

$connection = new mysqli('localhost',
'admin', '123456', 'vehicleRecall');
// if connection fails, show error
if ($connection-> connect_error) {
return null;
}
else{

return $connection;
}
}

// register User

function auth_register($username, $password, $vin, $carMake, $model, $trim, $color, $year)
{

$connection= connectDB();
if ($connection === null) {
    return ["status" => "error"];
}
// validating rules    
if (
    $username === '' ||
    strlen($password) < 6 ||
    $vin === '' ||
    $carMake === '' ||
    $model === '' ||
    $trim === '' ||
    $color === '' ||
    $year === ''
) {
    return ["status" => "error"];
}
// using prepared statements for security
$stmt = $connection->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
// store result to count
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

//if row count greater than 0, username exists
if ($count>0){
    return ["status" => "exists"];
}

// hash password
$hashPass= password_hash($password, PASSWORD_DEFAULT);
//insert username and pass
$stmt = $connection->prepare("INSERT INTO users 
(username, password_hash) VALUES (?, ?)");

$stmt->bind_param("ss", $username, $hashPass);
$userOk= $stmt-> execute();
$stmt->close();
// check if user insert works otherwise it will create mess
if (!$userOk){
    return ["status"=> "error"];
}
// get ID from last inseretd user and add vehicle to that id
$user_id= $connection->insert_id;

$stmt= $connection->prepare("INSERT INTO vehicles 
(user_id, vin, car_make, model, trim, color, year)
 VALUES (?, ?, ?, ?, ?, ?, ?)");
 $stmt->bind_param("isssssi", $user_id, $vin, $carMake,$model, $trim, $color, $year);
 $ok= $stmt->execute();
 $stmt->close();

 if ($ok){

    return ["status"=> "ok"];
 }
    else{

        return ["status"=>"error"];

    }
 
 }



// generate session token
function make_session_key(){
    return bin2hex(random_bytes(32));
}
// authenticate login
function auth_login($username, $password){
    if ($username === '' || $password === ''){ 
    return ["status" => "error"]; }

    $connection= connectDB();
    if ($connection === null) {
    return ["status" => "error"];
}
 
    $stmt= $connection-> prepare("SELECT id, password_hash FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();
// fetch row from table
    $row= $result->fetch_assoc();
    $stmt->close();
    
    // if username does not exis, deny request
     if (!$row){
        return ["status"=> "error"];
     }


    
// verify pass
if (!password_verify($password, $row["password_hash"])) {
    return ["status"=> "error"];
}
// create session token for user
$session_key= make_session_key();
$user_id= $row["id"];

// store session token in table
$stmt= $connection-> prepare("INSERT INTO sessions (session_key, user_id) VALUES (?, ?)");

$stmt-> bind_param("si", $session_key, $user_id);
$ok=$stmt->execute();
$stmt->close();

if ($ok){

    return ["status"=> "ok", "session_key" => $session_key];
 }
    else{

        return ["status"=>"error"];

    }



}
//validate session
function auth_validate_session($session_key) {


if ($session_key === '') {
        return ["status" => "invalid"];
    }
$connection= connectDB();
if ($connection === null) {
    return ["status" => "invalid"];
}


// check if session key exist
$stmt = $connection->prepare("SELECT COUNT(*) FROM sessions WHERE session_key = ?");

 $stmt->bind_param("s", $session_key);
 $stmt->execute();
 $stmt->bind_result($count);
 $stmt->fetch();
 $stmt->close();


if ($count > 0) {
    return ["status" => "ok"];

} else {
    return ["status" => "invalid"];
}

}
function auth_logout($session_key){
// check for empty key
if ($session_key === '') {
    return ["status" => "error"];
}

$connection= connectDB();
if ($connection === null) {
    return ["status" => "error"];
}
$stmt= $connection->prepare("DELETE FROM sessions WHERE session_key = ?");

$stmt->bind_param("s", $session_key);
    $ok= $stmt->execute();
    $stmt->close();

    if ($ok){

    return ["status"=> "ok"];
 }
    else{

        return ["status"=>"error"];

    }


}


function getUserVehicles($username)
{
    $connection = connectDB();
    if ($connection === null) {
        return ["status" => "error"];
    }

    $stmt = $connection->prepare("SELECT vehicles.id, vehicles.vin, vehicles.car_make, vehicles.model, vehicles.`trim`, vehicles.color, vehicles.year
                                  FROM users
                                  JOIN vehicles ON users.id = vehicles.user_id
                                  WHERE users.username = ?
                                  ORDER BY vehicles.id DESC");

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
        "vehicles" => $rows
    ];
}



