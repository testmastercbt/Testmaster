<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$name = $_SESSION['name'];
$matric_num = $_SESSION['matric_number'];
$role = $_SESSION['role'];
$initials = strtoupper(substr($name, 0, 1) . substr($name, strpos($name, ' ') + 1, 1));

// Get enrolled courses with progress
$stmt = $pdo->prepare("
    SELECT c.title, c.course_code, c.description, e.progress 
    FROM enrollments e
    JOIN courses c ON c.id = e.course_id
    WHERE e.user_id = ?
");
$stmt->execute([$userId]);
$myCourses = $stmt->fetchAll();

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
    <title>TestMaster Dashboard</title>
    <link rel="stylesheet" href="courses.css">
    <style>
  .coming-soon-section {
    background-color: #e0f7ff; /* light blue background */
    border-left: 5px solid #004;
    padding: 20px 25px;
    margin: 20px 0;
    border-radius: 10px;
    color: #003;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  }

  .coming-soon-section h2 {
    margin-bottom: 10px;
    font-size: 1.4rem;
    color: #004;
  }

  .coming-soon-section p {
    font-size: 0.95rem;
    line-height: 1.6;
  }
</style>

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
                            <img style="width: 80px;" src="https://portal.abu.edu.ng/pixx/<?= htmlspecialchars($matric_num) ?>.JPG" alt="Profile Picture">
                        </div>
                        <div class="user-name"><?= htmlspecialchars($name) ?></div>
                        <div class="user-email" style="font-weight: bold;"><?= htmlspecialchars($matric_num) ?></div><br>
                        <span class="subscription-badge <?= $role === 'premium' ? 'paid-plan' : 'free-plan' ?>">
                            <?= ucfirst($role) ?> Plan
                        </span>
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
                            <a href="courses.php" class="nav-link active">
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
                <?php if ($role === 'free'): ?>
                        <li class="nav-item">
                            <a href="upgrade.php" class="nav-link">
                                <span class="nav-icon">💎</span>
                                Upgrade Plan
                            </a>
                        </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a href="feedback.php" class="nav-link">
                                <span class="nav-icon">⚙️</span>
                                Feedback/Report an Issue
                            </a>
                        </li>
                    </ul>
                </nav>
            </aside>

            <!-- Content Area -->
            <main class="content-area">
                <!-- My Courses -->
                <div class="actions-section">
                    <h2 class="section-title" style="text-align: center;">My Courses</h2>
                </div>

                <!-- Coming Soon Section -->
<div class="coming-soon-section">
  <h2>🚧 Feature Coming Soon</h2>
  <p>
    This feature is currently in development and will be available in an upcoming update.  
    Stay tuned for new tools and enhancements to improve your experience!
  </p>
</div>
            </main>
    <script src="courses.js">
</body>
<?php include('footer.php'); ?>
</html>