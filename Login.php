<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Log In | TestMaster</title>
  <link rel="stylesheet" href="login.css">
</head>
<body>
  <form action="auth.php" method="post">
      <p>Matric Number:</p>
      <input type="text" name="matric_number" placeholder="e.g U24CO1000" required>
      <p>Password:</p>
      <input type="password" name="password" placeholder="Password" required>
      <h4 class="c-signuptext">Don't have an account? <a href="register.php">Sign Up</a></h4>
      <input type="submit" value="Log In" name="login" class="submit-btn">
      <?php if (isset($_GET['error'])) echo "<p style='color:red;'>".htmlspecialchars($_GET['error'])."</p>"; ?>
  </form>
</body>
</html>