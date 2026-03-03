<?php

// Database setting
define('DB_HOST', 'localhost');
define('DB_USER', 'testUser');
define('DB_PASS', '12345');
define('DB_NAME', 'testdb');


// Connect to MySQL
function auth_db()
{
    // create new MySQL connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    // if connection fails, return null
    if ($conn->connect_error) {
        return null;
    }

    return $conn;
}


// Generate Session Token
function make_session_key()
{
    // secure session token
    return bin2hex(random_bytes(32));
}


// Register User
function auth_register($username, $password)
{
    // validation
    if ($username === '' || strlen($password) < 6) {
        return ["status" => "error"];
    }

    $conn = auth_db();
    if ($conn === null) {
        return ["status" => "error"];
    }

    // Check if username already exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    if ($stmt === false) {
        return ["status" => "error"];
    }

    // Bind username into the ? placeholder
    $stmt->bind_param("s", $username);

    // Run query
    $stmt->execute();

    // Store result into $count
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    // if username already exists
    if ($count > 0) {
        return ["status" => "exists"];
    }

    // Hash password
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // insert new user into database
    $stmt = $conn->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
    if ($stmt === false) {
        return ["status" => "error"];
    }


    $stmt->bind_param("ss", $username, $hash);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {

    return ["status" => "ok"];
}
 else {

    return ["status" => "error"];
}
}


// Login User
function auth_login($username, $password)
{
    if ($username === '' || $password === '') {
        return ["status" => "error"];
    }

    $conn = auth_db();
    if ($conn === null) {
        return ["status" => "error"];
    }

    // Get user ID and hashed password from the database
    $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE username = ?");
    if ($stmt === false) {
        return ["status" => "error"];
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();

    // Get result
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    // If user not found
    if (!$row) {
        return ["status" => "error"];
    }

    // Verify password matches hashed password
    if (!password_verify($password, $row["password_hash"])) {
        return ["status" => "error"];
    }

    // Create session token
    $session_key = make_session_key();
    $user_id = (int)$row["id"];

    // Store session token in sessions table
    $stmt = $conn->prepare("INSERT INTO sessions (session_key, user_id) VALUES (?, ?)");
    if ($stmt === false) {
        return ["status" => "error"];
    }

    $stmt->bind_param("si", $session_key, $user_id);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ["status" => "error"];
    }

    // return session key
    return ["status" => "ok", "session_key" => $session_key];
}


// Validate Session
function auth_validate_session($session_key)
{
    if ($session_key === '') {
        return ["status" => "invalid"];
    }

    $conn = auth_db();
    if ($conn === null) {
        return ["status" => "invalid"];
    }

    // Check if session exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM sessions WHERE session_key = ?");
    if ($stmt === false) {
        return ["status" => "invalid"];
    }

    $stmt->bind_param("s", $session_key);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    // if session exists it's valid
if ($count > 0) {
    return ["status" => "ok"];

} else {
    return ["status" => "invalid"];
}

}
