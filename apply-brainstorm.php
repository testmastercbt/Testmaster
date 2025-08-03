<?php
require_once 'config.php';
session_start();

// ✅ Ensure user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['matric_number'])) {
    header("Location: login.php");
    exit;
}

$name = $_SESSION['name'] ?? 'Student';
$matric = strtoupper($_SESSION['matric_number']);
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'free';
$initials = strtoupper(substr($name, 0, 1) . substr(strrchr($name, ' '), 1, 1));

// ❌ Block non-Computer Engineering students
if (strpos($matric, 'U24CO') !== 0) {
    header("Location: access-denied.php");
    exit;
}

// ✅ Fetch courses not yet registered by the user
$query = $pdo->prepare("
    SELECT c.id, c.course_title, c.course_code
    FROM brainstorm_courses c
    WHERE c.id NOT IN (
        SELECT course_id FROM brainstorm_registrations WHERE user_id = ?
    )
");
$query->execute([$user_id]);
$courses = $query->fetchAll(PDO::FETCH_ASSOC);

// Check how many brainstorm courses user is already registered for
$checkLimit = $pdo->prepare("SELECT COUNT(*) FROM brainstorm_registrations WHERE user_id = ?");
$checkLimit->execute([$user_id]);
$enrolledCount = $checkLimit->fetchColumn();
 

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['courses'])) {
    $selectedCourses = $_POST['courses'];
    $inserted = 0;

    foreach ($selectedCourses as $course_id) {
        // Prevent duplicates (just in case)
        $check = $pdo->prepare("SELECT 1 FROM brainstorm_registrations WHERE user_id = ? AND course_id = ?");
        $check->execute([$user_id, $course_id]);

        if (!$check->fetch()) {
            $insert = $pdo->prepare("INSERT INTO brainstorm_registrations (user_id, course_id) VALUES (?, ?)");
            if ($insert->execute([$user_id, $course_id])) {
                $inserted++;
            }
        }
    }

    $message = $inserted > 0 
        ? "<div class='note' style='color: green;'>✅ Successfully registered for $inserted course(s).</div>"
        : "<div class='note'>⚠️ You’ve already registered for the selected course(s).</div>";

    // Reload course list after registration
    $query->execute([$user_id]);
    $courses = $query->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Application | Computer Engineering Brainstorm</title>
    <link rel="stylesheet" href="maindashbord.css">
    <link rel="stylesheet" href="apply.css">
</head>
<body>
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
        <img style="width: 80px;" src="https://portal.abu.edu.ng/pixx/<?= htmlspecialchars($matric) ?>.JPG" alt="Profile Picture"></div>
                        <div class="user-name"><?= htmlspecialchars($name) ?></div>
                        <div class="user-email" style="font-weight: bold"><?= htmlspecialchars($matric) ?></div>
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
                            <a href="examsetup1.php" class="nav-link active">
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
                <div class="brainstorm-header">
      <h1>COMPUTER ENGINEERING BRAINSTORM (100 LEVEL)</h1>
      <p>Mock exam event designed to prepare Computer Engineering students for upcoming tests.</p>
    </div>

    <div class="brainstorm-details">
      <div class="detail-item"><strong>📅 Date:</strong> Sunday, August 2, 2025</div>
      <div class="detail-item"><strong>⏰ Time:</strong> TBD </div>
      <div class="detail-item"><strong>&nbsp;📍 &nbsp;Venue:</strong> TBD</div>
      <div class="detail-item"><strong>📌 Instructions:</strong> ___ </div>
    </div>

    <p style="text-align: center"><?php if (isset($message)) echo $message; ?></p>

    <div class="courses-section">
  <h3>Select the Courses You Want to Register For:</h3>
  <form action="apply-brainstorm.php" method="POST">
    <div class="courses-list">

      <?php if (count($courses) === 0): ?>
        <p class="note">✅ You've registered for all available courses.</p>
      <?php else: ?>
        <?php foreach ($courses as $course): ?>
          <label class="course-card">
            <input type="checkbox" name="courses[]" value="<?= $course['id'] ?>">
            <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_title']) ?>
          </label>
        <?php endforeach; ?>
      <?php endif; ?>

    </div>

    <?php if (count($courses) > 0): ?>
      <button type="submit" class="apply-btn">Apply Now</button>
    <?php endif; ?>
  </form>
      <!-- Optional Note -->
      <p class="note">* This event is only available to Computer Engineering students (<strong>U24CO</strong>).</p>
    </div>
  </div>
</body>
<script>
  const maxAllowed = 4;
  const checkboxes = document.querySelectorAll('input[type="checkbox"][name="courses[]"][value="<?= $course['id'] ?>]');

  checkboxes.forEach(cb => {
    cb.addEventListener('change', () => {
      const checkedCount = document.querySelectorAll('input[type="checkbox"][name="selected_courses[]"]:checked').length;

      if (checkedCount >= maxAllowed) {
        checkboxes.forEach(box => {
          if (!box.checked) box.disabled = true;
        });
      } else {
        checkboxes.forEach(box => box.disabled = false);
      }
    });
  });
</script>
<br>
<?php include('footer.php'); ?>
</html>