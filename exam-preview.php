<?php
require_once 'config.php';
session_start();

// 1. User Login Check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['matric_number'])) {
    header("Location: login.php");
    exit;
}

// 2. Check if course_id is set
if (!isset($_GET['course_id'])) {
    header("Location: dashboard.php");
    exit;
}

$name = $_SESSION['name'];
$matric_num = $_SESSION['matric_number']; // or email if stored
$matric = strtoupper($_SESSION['matric_number']);
$role = $_SESSION['role'];
$initials = strtoupper(substr($name, 0, 1) . substr($name, strpos($name, ' ') + 1, 1));
$user_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'];

// 3. Check if the user registered for the course
$stmt = $pdo->prepare("
    SELECT r.access_granted, c.course_title, c.course_code, c.exam_date, c.exam_time, c.description
    FROM brainstorm_registrations r
    JOIN brainstorm_courses c ON r.course_id = c.id
    WHERE r.user_id = ? AND r.course_id = ?
");
$stmt->execute([$user_id, $course_id]);
$data = $stmt->fetch();

if (!$data) {
    header("Location: access-denied.php"); // Not registered
    exit;
}

// 4. Check exam time
date_default_timezone_set('Africa/Lagos'); // set your correct timezone
$currentDateTime = new DateTime();
$examDateTime = new DateTime($data['exam_date'] . ' ' . $data['exam_time']);

$canStart = false;
$reason = "";

if ($currentDateTime < $examDateTime) {
    $reason = "📌 Exam time has not been reached yet.";
} elseif (!$data['access_granted']) {
    $reason = "🔒 Access not yet granted by admin.";
} else {
    $canStart = true;
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
  <title><?= htmlspecialchars($data['course_code']) ?> - Preview</title>
  <link rel="stylesheet" href="maindashbord.css">
  <style>
    .preview-card {
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 8px 16px rgba(0,0,0,0.05);
      max-width: 700px;
      margin: auto;
    }

    .preview-card h1 {
      color: #004;
    }

    .preview-details {
      margin-top: 20px;
    }

    .preview-details p {
      margin-bottom: 10px;
      font-size: 16px;
    }

    .start-btn {
      margin-top: 30px;
      padding: 12px 30px;
      background: #004;
      color: white;
      font-size: 16px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
    }

    .start-btn:disabled {
      background: #ccc;
      cursor: not-allowed;
    }

    .msg-box {
      background: #fff3cd;
      padding: 15px;
      margin-top: 20px;
      border: 1px solid #ffeeba;
      color: #856404;
      border-radius: 8px;
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
  <div class="preview-container">
    <div class="preview-card">
      <h1><?= htmlspecialchars($data['course_code']) ?> - <?= htmlspecialchars($data['course_title']) ?></h1>

      <div class="preview-details">
        <p><strong>📅 Date:</strong> <?= htmlspecialchars($data['exam_date']) ?></p>
        <p><strong>⏰ Time:</strong> <?= htmlspecialchars($data['exam_time']) ?></p>
        <p><strong>&nbsp;❓ Questions:</strong> 20</p>
        <p><strong>🕒 Duration:</strong> 20 Minutes</p>
        <p><strong>📌 Description:</strong><br><?= nl2br(htmlspecialchars($data['description'])) ?></p>
      </div>

      <?php if (!$canStart): ?>
        <div class="msg-box"><?= $reason ?></div>
        <form action="b-instructions.php" method="GET">
        <button class="start-btn" disabled>Go To Instructions</button>
      <?php else: ?><br>
          <input type="hidden" name="course_id" value="<?= $course_id ?>">
          <a style="text-decoration: none;" href="b-instructions.php?course_id=<?= $course_id ?>" type="submit" class="start-btn">Go To Instructions</a>
        </form>
      <?php endif; ?>
    </div>
  </div>
      </main>
</body>
</html>
