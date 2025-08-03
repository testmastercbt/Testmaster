<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_GET['course_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$course_id = (int)$_GET['course_id'];
$userId = $_SESSION['user_id'];
$name = $_SESSION['name'];
$matric_num = $_SESSION['matric_number']; // or email if stored
$matric = strtoupper($_SESSION['matric_number']);
$role = $_SESSION['role'];
$initials = strtoupper(substr($name, 0, 1) . substr($name, strpos($name, ' ') + 1, 1));

// Check if user is registered for this course
$stmt = $pdo->prepare("SELECT br.*, bc.course_title, bc.course_code, bc.exam_date, bc.exam_time, be.location
                        FROM brainstorm_registrations br
                        JOIN brainstorm_courses bc ON br.course_id = bc.id
                        JOIN brainstorm_event be ON 1=1
                        WHERE br.user_id = ? AND br.course_id = ?");
$stmt->execute([$user_id, $course_id]);
$registration = $stmt->fetch();

if (!$registration) {
    header("Location: accessdenied1.php");
    exit;
}

$attempts_used = $registration['attempts_used'];
$max_attempts = $registration['max_attempts'];
$can_start = $attempts_used < $max_attempts && $registration['access_granted'];
$now = new DateTime();
$event_time = new DateTime($registration['exam_date'] . ' ' . $registration['exam_time']);
$exam_available = $now >= $event_time;

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

<!DOCTYPE html><html lang="en">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1024">
    <title>Brainstorm Exam Instructions</title>
    <link rel="stylesheet" href="maindashbord.css">
    <link rel="stylesheet" href="b-instructions.css">
</head>
<body style="width: 1024">
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
    <div class="container">
        <h2 class="instructions-title"><?= htmlspecialchars($registration['course_code']) ?> - <?= htmlspecialchars($registration['course_title']) ?></h2><p><strong>📅 Date:</strong> <?= htmlspecialchars($registration['exam_date']) ?> &nbsp;&nbsp;&nbsp;<br>
       <strong>⏰ Time:</strong> <?= htmlspecialchars($registration['exam_time']) ?> &nbsp;&nbsp;&nbsp;<br>
       <strong>📍 Venue:</strong> <?= htmlspecialchars($registration['location']) ?></p><br>

    <p class="attempts">🔁 Attempt: <?= $attempts_used ?> / <?= $max_attempts ?></p>

    <h3>📝 Exam Instructions</h3>
    <ol class="instructions-list">
        <li>Do not refresh the page. Refreshing will result in auto-submission.</li>
        <li>Avoid switching tabs/windows. Doing this more than 3 times will auto-submit the exam.</li>
        <li>Ensure a stable internet connection throughout.</li>
        <li>Use the on-screen calculator if needed.</li>
        <li>There are 20 questions to answer within 30 minutes.</li>
        <li>All questions carry equal marks.</li>
        <li>Submit when you're done, or it will auto-submit after the time ends.</li>
    </ol>

    <?php if (!$exam_available): ?>
        <p class="notice">⏳ Exam time has not been reached yet.</p>
    <?php elseif (!$registration['access_granted']): ?>
        <p class="notice">🔒 Access not granted. Please wait for admin approval.</p>
    <?php elseif ($attempts_used >= $max_attempts): ?>
        <p class="notice">❌ You have used all your allowed attempts for this exam.</p>
    <?php endif; ?>

        <br>

    <form action="b-take-exam.php?<?= $course_id ?>" method="GET">
        <input type="hidden" name="course_id" value="<?= $course_id ?>">
        <button type="submit" class="start-btn" <?= !$can_start || !$exam_available ? 'disabled' : '' ?>>Start Exam</button>
    </form>
</div>

</body>
<?php include('footer.php'); ?>
</html>
