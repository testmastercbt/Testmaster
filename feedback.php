<?php
session_start();
require 'config.php'; // PDO connection

if (!isset($_SESSION['user_id'])) {
    die("Please log in to submit feedback.");
}

$name = $_SESSION['name'];
$matric_num = $_SESSION['matric_number']; // or email if stored
$role = $_SESSION['role'];
$initials = strtoupper(substr($name, 0, 1) . substr($name, strpos($name, ' ') + 1, 1));

$user_id = $_SESSION['user_id'];
$type = $_POST['type'] ?? 'feedback';
$subject = $_POST['subject'] ?? '';
$message = $_POST['message'] ?? '';

if ($subject && $message) {
    $stmt = $pdo->prepare("INSERT INTO feedback (user_id, type, subject, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $type, $subject, $message]);

    echo "✅ Feedback sent successfully!";
    // header("Location: settings.php?feedback=sent"); // if you want to redirect
}

// Check if logout button is clicked
if (isset($_POST['logout'])) {
    // Unset all session variables
    session_unset();

    // Destroy the session
    session_destroy();

    // Redirect to login page
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1024">
    <title>Feedback | TestMaster</title>
    <link rel="stylesheet" href="maindashbord.css">
    <link rel="stylesheet" href="feedback.css">
</head>
<body style="min-width: 1024px;">
    <div class="dashboard-container">
        <!-- Header -->
        <header class="header">
            <div class="logo">TestMaster</div>
            <div class="user-info">
                <span class="subscription-badge <?= $role === 'premium' ? 'paid-plan' : 'free-plan' ?>">
                        <?= ucfirst($role) ?> Plan
                </span>
                <form action="" method="post">
                    <input type="submit" class="logout-btn" name="logout" value="Logout">
                </form>
            </div>
        </header>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Sidebar -->
            <aside class="sidebar">
                <a href="profile3.php" style="text-decoration: none; color: #444; ">
                    <div class="profile-section">
                        <div style="overflow: hidden" class="profile-pic <?= $role === 'premium' ? '' : 'locked' ?> <?= $role === 'premium' ? 'paid-plan2' : 'free-plan2' ?>">
        <img style="width: 80px;" src="https://portal.abu.edu.ng/pixx/<?= htmlspecialchars($matric_num) ?>.JPG" alt="Profile Picture"></div>
                        <div class="user-name"><?= htmlspecialchars($name) ?></div>
                        <div class="user-email" style="font-weight: bold"><?= htmlspecialchars($matric_num) ?></div>
                    </div>
                </a>

                <nav>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link">
                                <span class="nav-icon">🏠</span>
                                Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="courses.php" class="nav-link">
                                <span class="nav-icon">📚</span>
                                My Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="examsetup1.php" class="nav-link">
                                <span class="nav-icon">📝</span>
                                Take Exam
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="results.php" class="nav-link">
                                <span class="nav-icon">📊</span>
                                Results
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="settings.php" class="nav-link">
                                <span class="nav-icon">⚙️</span>
                                Settings
                            </a>
                        </li>
                <?php if ($role === 'free'): ?>
                        <li class="nav-item">
                            <a href="upgrade.php" class="nav-link">
                                <span class="nav-icon">💎</span>
                                Upgrade Plan
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>

                    
                </nav>
            </aside>

            <!-- Content Area -->
            <main class="content-area">
<div class="feedback-wrapper">
  <form class="feedback-form" action="submit-feedback.php" method="POST" onsubmit=" return clearFormAfterSubmit()">
    <h2>💬 Feedback / Report Issue</h2>

    <label for="type">Type</label>
    <select name="type" required>
      <option value="feedback">General Feedback</option>
      <option value="issue">Report an Issue</option>
    </select>

    <label for="subject">Subject</label>
    <input type="text" name="subject" placeholder="Short title (e.g. Bug in exam page)" id="subject" required>

    <label for="message">Message</label>
    <textarea name="message" rows="6" placeholder="Describe your feedback or the issue in detail..." id="message" required></textarea>

    <button id="submit" type="submit">🚀 Submit</button>
  </form>
</div>
</main>

<script>
function clearFormAfterSubmit() {
  // Delay clearing to let PHP submission process
  setTimeout(() => {
    document.getElementById("feedbackForm").reset();
  }, 100); // Adjust delay if needed

  return true; // Allow form to submit
}
</script>