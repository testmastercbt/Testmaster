<?php
require_once 'config.php'; // uses PDO

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    $type = $_POST['type'] ?? 'feedback';

    if (empty($subject) || empty($message)) {
        $error = "Subject and message are required.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO general_feedback (name, email, subject, message, type) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $subject, $message, $type]);
        $success = "✅ Your message has been sent!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Contact Us | TestMaster</title>
  <style>
    body {
      background: #f4f4f4;
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 0;
    }
    .wrapper {
      max-width: 600px;
      background: white;
      margin: 3rem auto;
      padding: 2rem;
      border-radius: 8px;
      box-shadow: 0 0 15px rgba(0,0,0,0.15);
      border-top: 5px solid #004;
    }
    h2 {
      color: #004;
      text-align: center;
    }
    label {
      font-weight: bold;
      margin-top: 1rem;
      display: block;
    }
    input, textarea, select {
      width: 100%;
      padding: 0.8rem;
      margin-top: 0.4rem;
      border-radius: 5px;
      border: 1px solid #ccc;
    }
    button {
      margin-top: 1.5rem;
      width: 100%;
      padding: 1rem;
      border: none;
      background: #004;
      color: white;
      font-weight: bold;
      cursor: pointer;
      border-radius: 5px;
    }
    .msg {
      margin-top: 1rem;
      padding: 1rem;
      border-radius: 5px;
    }
    .success { background: #d4edda; color: #155724; }
    .error { background: #f8d7da; color: #721c24; }
  </style>
</head>
<body>

<div class="wrapper">
  <h2>Contact Us</h2>

  <?php if ($success): ?>
    <div class="msg success"><?= $success ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="msg error"><?= $error ?></div>
  <?php endif; ?>

  <form method="POST">
    <label for="name">Your Name (Optional)</label>
    <input type="text" name="name" id="name">

    <label for="email">Your Email (Optional)</label>
    <input type="email" name="email" id="email">

    <label for="subject">Subject</label>
    <input type="text" name="subject" id="subject" required>

    <label for="type">Type</label>
    <select name="type" id="type">
      <option value="feedback">General Feedback</option>
      <option value="issue">Report an Issue</option>
      <option value="review">Leave a Review</option>
    </select>

    <label for="message">Message</label>
    <textarea name="message" id="message" rows="5" required></textarea>

    <button type="submit">Send Message</button>
  </form>
</div>

</body>
</html>
