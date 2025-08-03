<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId = $_SESSION['user_id'];
$name = $_SESSION['name'];
$matric_num = $_SESSION['matric_number']; // or email if stored
$matric = strtoupper($_SESSION['matric_number']);
$role = $_SESSION['role'];
$initials = strtoupper(substr($name, 0, 1) . substr($name, strpos($name, ' ') + 1, 1));

// 1. Get enrolled course count
$courseQuery = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ?");
$courseQuery->execute([$userId]);
$courseCount = $courseQuery->fetchColumn();

// 2. Get exam count
$examQuery = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE user_id = ? AND time_ended IS NOT NULL");
$examQuery->execute([$userId]);
$examCount = $examQuery->fetchColumn();

// 3. Average score
$avgQuery = $pdo->prepare("SELECT AVG(score) FROM exams WHERE user_id = ?");
$avgQuery->execute([$userId]);
$averageScore = round($avgQuery->fetchColumn() ?: 0);

// 4. Study hours (placeholder for now, unless you're tracking them)
$studyHours = "?";

// 5. My courses (titles + codes)
$coursesStmt = $pdo->prepare("
    SELECT c.title, c.course_code 
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE e.user_id = ?
    LIMIT 4
");
$coursesStmt->execute([$userId]);
$myCourses = $coursesStmt->fetchAll();

// 6. Recent activity
$activitiesStmt = $pdo->prepare("
    SELECT action, timestamp 
    FROM activities 
    WHERE user_id = ?
    ORDER BY timestamp DESC 
    LIMIT 5
");
$activitiesStmt->execute([$userId]);
$recentActivities = $activitiesStmt->fetchAll();

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

// Fetch event info (only one expected)
$eventStmt = $pdo->query("SELECT * FROM brainstorm_event LIMIT 1");
$event = $eventStmt->fetch();

// Fetch user registered courses
$user_id = $_SESSION['user_id'];
$regStmt = $pdo->prepare("
  SELECT r.*, c.course_title, c.course_code
  FROM brainstorm_registrations r
  JOIN brainstorm_courses c ON r.course_id = c.id
  WHERE r.user_id = ?
  LIMIT 2
");
$regStmt->execute([$userId]);
$registeredCourses = $regStmt->fetchAll();


$maxVisible = 2;
$totalCourses = count($registeredCourses);




$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = :uid OR user_id IS NULL 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute(['uid' => $user_id]);
$notifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1024">
    <title>TestMaster Dashboard</title>
    <link rel="stylesheet" href="maindashbord.css">
    <link rel="stylesheet" href="maindash2.css">
    <!-- SEO + Sharing Meta Tags -->
<meta name="description" content="TestMaster is an advanced CBT platform where you can take exams, track results, and level up your skills. Built for students, tutors, and testers.">
<meta name="keywords" content="CBT, online exam, TestMaster, mock exam, study, school, WAEC, JAMB, NECO">
<meta name="author" content="CodeWithMukhiteee">

<!-- Social Preview -->
<meta property="og:title" content="TestMaster CBT Platform" />
<meta property="og:description" content="Take free and premium mock exams, review results, and boost your academic game!" />
<meta property="og:image" content="https://yourdomain.com/images/banner.jpg" />
<meta property="og:url" content="https://yourdomain.com/" />
<meta name="twitter:card" content="summary_large_image">

<!-- Favicon (optional) -->
<link rel="icon" href="favicon.ico" type="image/x-icon">
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
                        <div class="notification-panel-trigger" onclick="openNotificationPanel()">
                              🔔 Notifications <span class="notif-badge"><?= count($notifications) ?></span>
                        </div>
                        <li class="nav-item">
                            <a href="#" class="nav-link active">
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

            <div id="notificationOverlay" class="notification-overlay">
  <div class="notification-box">
    <div class="notif-header">
      <strong>Notifications</strong>
      <span onclick="closeNotificationPanel()" class="notif-close">✖</span>
    </div>
    <div class="notif-list">
      <?php foreach ($notifications as $notif): ?>
        <div class="notif-item">
          <h4><?= htmlspecialchars($notif['title']) ?></h4>
          <p><?= nl2br(htmlspecialchars($notif['message'])) ?></p>
          <small><?= date('M j, Y h:i A', strtotime($notif['created_at'])) ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>


                <!-- Welcome Section -->
                <div class="welcome-section">
                    <h1 class="welcome-title">Welcome back, <?= htmlspecialchars($name) ?></h1>
                    <p class="welcome-subtitle">Ready to continue your learning journey?</p>
                </div>

                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?= $courseCount ?></div>
                        <div class="stat-label">Courses Enrolled</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $examCount ?></div>
                        <div class="stat-label">Exams Completed</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $averageScore ?>%</div>
                        <div class="stat-label">Average Score</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $studyHours ?></div>
                        <div class="stat-label">Hours Studied</div>
                    </div>
                </div>

                <!-- Free Plan Limitations Notice -->
                <?php if ($role === 'free'): ?>
                    <div class="limitation-notice">
                    <strong>Free Plan Limitations:</strong> You have access to 3 courses and can take up to 3 exams per month. 
                    Profile editing is locked. <a href="upgrade.php" style="color: #004; font-weight: bold;">Upgrade to unlock all features!</a>
                </div><br>    
                <?php endif; ?>

                <!-- My Courses -->
                <div class="actions-section">
                    <h2 class="section-title">My Courses</h2>
                    <!-- Coming Soon Section -->
<div class="coming-soon-section">
  <h2>🚧 Feature Coming Soon</h2>
  <p>
    This feature is currently in development and will be available in an upcoming update.  
    Stay tuned for new tools and enhancements to improve your experience!
  </p>
</div>
</div>


<?php if (strpos($matric, 'U24CO') == 0): ?>
   <!-- Upcoming Brainstorm Exams Section -->
   <section id="brainstorm-upcoming" style="margin-top: 40px;">
   <h2 style="color: #004;" class="section-title">🧠 Upcoming Brainstorm Exams</h2>

   <?php if (count($registeredCourses) === 0): ?>
       <p style="background: #e3f2fd; color: #004; padding: 15px; border-radius: 6px;">
           You haven't registered for any Brainstorm exam yet. <br>
           <a href="apply-brainstorm.php" style="color: #004; font-weight: bold;">Click here to apply</a>
       </p>
       <?php else: ?>
          <div class="courses-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
          <?php foreach ($registeredCourses as $course): ?>
             <a href="exam-preview.php?course_id=<?= $course['course_id'] ?>" style="text-decoration: none;">
             <div class="course-card" style="background: #fff; border: 2px solid #004; border-radius: 12px; padding: 20px; transition: 0.3s; color: #004;">
             <h3 style="margin-bottom: 10px;"><?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_title']) ?></h3>
            
             <p style="margin: 5px 0;"><strong>📅 Date:</strong> <?= htmlspecialchars($event['event_date']) ?></p>
             <p style="margin: 5px 0;"><strong>⏰ Time:</strong> <?= htmlspecialchars($event['event_time']) ?></p>
             <p style="margin: 5px 0;"><strong>📍 Venue:</strong> <?= htmlspecialchars($event['location']) ?></p>
            
             <p style="margin: 10px 0 0; font-size: 13px; color: <?= $course['access_granted'] ? '#2e7d32' : '#f57c00' ?>;">
              <strong>Status:</strong> <?= $course['access_granted'] ? 'Access Granted ✅' : 'Awaiting Access ⏳' ?>
             </p>
             </div>
             </a>
          <?php endforeach; ?>
          </div>
        <div style="margin-top: 25px; text-align: left; margin-bottom: 20px">
            <a href="registered-exams.php" style="text-decoration: none; padding: 10px 20px; background-color: #004; color: white; border: none; border-radius: 6px"> See All </a>
        </div>
    <?php endif; ?>
    </section>
<?php endif; ?><br>


                <!-- Quick Actions -->
                <div class="actions-section">
                    <h2 class="section-title">Quick Actions</h2>
                    <div class="action-buttons">
                        <div class="action-card" onclick="startCourse()">
                            <a href="courses.php" style="text-decoration: none;">
                                <div class="action-icon">📖</div>
                                <div class="action-title">Continue Course</div>
                                <div class="action-description">Resume your current chapter and keep progressing</div>
                            </a>
                        </div>
                        <div class="action-card" onclick="takeExam()">
                            <a href="examsetup1.php" style="text-decoration: none;">
                                <div class="action-icon">📝</div>
                                <div class="action-title">Take Practice Exam</div>
                                <div class="action-description">Test your knowledge with practice questions</div>
                            </a>
                        </div>
                        <div class="action-card" onclick="viewResults()">
                            <a href="results.php" style="text-decoration: none;">
                                <div class="action-icon">📊</div>
                                <div class="action-title">View Results</div>
                                <div class="action-description">Check your exam scores and progress</div>
                            </a>
                        </div>
                        <div class="action-card" onclick="browseCourses()">
                            <a href="courses.php" style="text-decoration: none;">
                                <div class="action-icon">🔍</div>
                                <div class="action-title">Browse Courses</div>
                                <div class="action-description">Discover new courses to expand your knowledge</div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="recent-activity">
                    <h2 class="section-title">Recent Activity</h2>
                    <?php foreach ($recentActivities as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">✅</div>
                        <div class="activity-content">
                            <div class="activity-title"><?= $activity['action'] ?></div>
                            <div class="activity-time"><?= date('M d, H:i a', strtotime($activity['timestamp'])) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script src="maindash.js"></script>

</body>

<?php include('footer.php'); ?>
</html>