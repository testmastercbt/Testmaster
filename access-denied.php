<?php
session_start();
$name = $_SESSION['name'] ?? 'User';
$matric = $_SESSION['matric_number'];

if (stripos($matric, 'U24CO') === 0) {
    header("Location: apply-brainstorm.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Access Denied - TestMaster</title>
    <link rel="stylesheet" href="access-denied.css">
</head>
<body>

<div class="lock-container">
    <div class="lock-emoji">🔒</div>
    <h2>Access Denied</h2>
    <p>Sorry <?= htmlspecialchars($name) ?>, this page is only available to <strong>100 Level Computer Engineering students</strong> (U24CO).</p>
    <a href="dashboard.php" class="btn">Back to Dashboard</a>
</div>

</body>
</html>