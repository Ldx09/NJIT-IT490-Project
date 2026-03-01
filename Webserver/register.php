<?php
session_start();

$username = "";
$errors   = [];


if (isset($_POST['reg_user'])) {

  // receive input
  $username   = trim($_POST['username'] ?? '');
  $password_1 = $_POST['password_1'] ?? '';
  $password_2 = $_POST['password_2'] ?? '';

  // validation
  if ($username === '') { $errors[] = "Username is required"; }
  if ($password_1 === '') { $errors[] = "Password is required"; }
  if (strlen($password_1) < 6) { $errors[] = "Password must be at least 6 characters"; }
  if ($password_1 !== $password_2) { $errors[] = "Passwords do not match"; }

  // send to backend
  if (count($errors) === 0) {
    try {
      $client = new rabbitMQClient($iniFile, $serverKey);

      $request = [
        "type"     => "register",
        "username" => $username,
        "password" => $password_1
      ];

      $response = $client->send_request($request);

      if ($response === "ok" || $response === true) {
        $_SESSION['username'] = $username;
        $_SESSION['success']  = "Registered successfully";
        header('Location: login.php');
        exit;
      } else {
        $errors[] = "Registration failed (user may already exist)";
      }

      // in case if error

    } catch (Throwable $e) {
      $errors[] = "Service unavailable";
    }
  }
}
?>

<!doctype html>
<html>
<head><title>Register</title></head>
<body>

<h2>Register</h2>

<?php if ($errors): ?>
  <ul style="color:red;">
    <?php foreach ($errors as $e): ?>
      <li><?php echo $e; ?></li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<form method="post">
  Username:<br>
  <input type="text" name="username"><br><br>

  Password:<br>
  <input type="password" name="password_1"><br><br>

  Confirm Password:<br>
  <input type="password" name="password_2"><br><br>

  <button type="submit" name="reg_user">Register</button>
</form>

</body>
</html>
