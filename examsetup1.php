<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'];
$role = $_SESSION['role'];
$matric_num = $_SESSION['matric_number'];
$initials = strtoupper(substr($name, 0, 1) . substr($name, strpos($name, ' ') + 1, 1));

// Fetch available exams with course info
$stmt = $pdo->query("SELECT el.id AS exam_id, el.title AS exam_title, el.number_of_questions, el.time_limit, el.course_id,
                            c.title AS course_title, c.course_code
                     FROM examslist el
                     JOIN courses c ON c.id = el.course_id
                     WHERE el.visibility = 'public'");
$exams = $stmt->fetchAll();

// Get today's attempt count for free users
$attempts_today = 0;
if ($role === 'free') {
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE user_id = ? AND type = 'normal' AND DATE(time_started) = CURDATE()");
    $stmt2->execute([$user_id]);
    $attempts_today = $stmt2->fetchColumn();
}
?>

<!DOCTYPE html><html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Select Course | TestMaster</title>
    <link rel="stylesheet" href="takeexam.css">
    <style>
        .subscription-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .free-plan {
            background: #ffebee;
            color: #c62828;
        }

        .paid-plan {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .search-container {
            margin-bottom: 20px;
        }
        .search-container input {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .courses-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .course-card {
            background: #f4f4f4;
            padding: 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .course-card:hover {
            background: #e0ebff;
        }
        .course-details span {
            display: inline-block;
            margin-right: 15px;
            font-size: 14px;
            color: #333;
        }
        .free-limit-info {
            font-weight: bold;
            color: #d35400;
            margin: 10px 0 20px;
        }
        .profile-pic.locked::after {
            content: "🔒";
            position: absolute;
            font-size: 16px;
            background: #ff5722;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 60px;
            margin-top: -20px;
        }
        .profile-section {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .profile-pic {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #004;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 32px;
            font-weight: bold;
        }

        .free-plan2 {
            border: 3px solid #c62828;;
        }

        .paid-plan2 {
            border: 3px solid #2e7d32;
        }
    </style>
</head>
<body style="min-width: 1024px;">
  <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="logo">TestMaster</div>
            <div class="user-info">
                <span class="subscription-badge <?= $role === 'premium' ? 'paid-plan' : 'free-plan' ?>">
                        <?= ucfirst($role) ?> Plan
                </span>
                <a href="dashboard.php" style="text-decoration: none; background: #006; color: white; border: none; padding: 5px 10px; border-radius: 8px; cursor: pointer;">Dashboard</a>
            </div>
        </header>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Sidebar -->
            <aside class="sidebar">
                <a href="profile3.php" style="text-decoration: none; color: #444; ">
                    <div class="profile-section">
                        <div style="overflow: hidden;" class="profile-pic <?= $role === 'premium' ? '' : 'locked' ?> <?= $role === 'premium' ? 'paid-plan2' : 'free-plan2' ?>">
                            <img style="width: 80px;" src="https://portal.abu.edu.ng/pixx/<?= htmlspecialchars($matric_num) ?>.JPG" alt="Profile Picture">
                        </div>
                        <div class="user-name"><?= htmlspecialchars($name) ?></div>
                        <div class="user-email" style="font-weight: bold"><?= htmlspecialchars($matric_num) ?></div>
                    </div>
                </a>

                <nav>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <a href="dashboard.php" class="nav-link" onclick="goToDashboard()">
                                <span>🏠</span> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="courses.php" class="nav-link" onclick="showPage('courses')">
                                <span>📚</span> My Courses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="examsetup1.php" class="nav-link active" onclick="showPage('course-selection')">
                                <span>📝</span> Take Exam
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="results.php" class="nav-link">
                                <span>📊</span> Results
                            </a>
                        </li>
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
                <!-- Course Selection Page -->
                <section id="exam-setup">
                <h1 style="text-align: center; margin-bottom: 20px; color: #004;">Available Exams</h1>
                <div class="search-container">
                <input type="text" placeholder="Search for a course..." id="searchInput" style="border-radius: 10px; border: 1px solid #004;" onkeyup="filterCourses()">
                </div>

    <?php if ($role === 'free'): ?>
        <div class="free-limit-info" style="margin-left: 15px;">Attempts: <?= $attempts_today ?>/3</div>
    <?php endif; ?>

    <div class="courses-grid">
        <?php foreach ($exams as $exam): ?>
            <div class="course-card" data-title="<?= strtolower($exam['course_title']) ?>"
                 onclick="selectCourse(<?= $exam['exam_id'] ?>, '<?= htmlspecialchars($exam['course_title']) ?>')">
                <div class="course-title"> <?= htmlspecialchars($exam['course_title']) ?> (<?= $exam['course_code'] ?>)</div>
                <div class="course-description">📝 <?= htmlspecialchars($exam['exam_title']) ?></div>
                <div class="course-stats">
                    <span> <?= $exam['number_of_questions'] ?> Questions</span>
                    <span>⏱ <?= $exam['time_limit'] ?> Minutes</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<script>
    function filterCourses() {
        let input = document.getElementById("searchInput").value.toLowerCase();
        let cards = document.querySelectorAll(".course-card");
        cards.forEach(card => {
            const title = card.getAttribute("data-title");
            card.style.display = title.includes(input) ? "block" : "none";
        });
    }

    function selectCourse(examId, courseTitle) {
        // Redirect to exam type selection
        window.location.href = `examsetup2.php?exam_id=${examId}&title=${encodeURIComponent(courseTitle)}`;
    }
</script>
</body><br>
<?php include('footer.php'); ?>
</html>
