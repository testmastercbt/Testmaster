<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['exam_id']) || !isset($_GET['title'])) {
    die("Exam information missing.");
}

$name = $_SESSION['name'];
$matric_num = $_SESSION['matric_number']; // or email if stored
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$exam_id = $_GET['exam_id'];
$course_title = htmlspecialchars($_GET['title']);

$attempts_today = 0;
if ($role === 'free') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE user_id = ? AND type = 'normal' AND DATE(time_started) = CURDATE()");
    $stmt->execute([$user_id]);
    $attempts_today = $stmt->fetchColumn();
}
?>


<!DOCTYPE html><html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Exam Type | TestMaster</title>
  <link rel="stylesheet" href="takeexam.css">
  <style>
    .exam-type-options {
      display: flex;
      gap: 20px;
      margin-bottom: 20px;
    }
    .exam-type {
      border: 2px solid #ccc;
      padding: 15px;
      border-radius: 8px;
      width: 48%;
      cursor: pointer;
    }
    .exam-type.selected {
      border-color: #3498db;
      background-color: #ecf7ff;
    }
    .setting-group {
      margin-bottom: 15px;
    }
    .setting-label {
      display: block;
      font-weight: bold;
      margin-bottom: 5px;
    }
    .setting-input {
      width: 100%;
      padding: 8px;
      border-radius: 4px;
      border: 1px solid #ccc;
    }
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
<body>

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
                            <a href="examsetup1.php" class="nav-link active">
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
  <div class="exam-setup">
  <h1 style="color: #004; margin-bottom: 30px;">Exam Setup - <?= $course_title ?></h1>  <?php if ($role === 'free'): ?><div style="font-weight: bold; color: #d35400;">Attempts: <?= $attempts_today ?>/3</div>

  <?php endif; ?>  <form id="examSetupForm" method="POST" action="take-exam.php">
    <input type="hidden" name="exam_id" value="<?= $exam_id ?>">
    <input type="hidden" name="exam_type" id="examTypeInput" value="normal">
    <input type="hidden" name="question_count" id="questionCountInput">
    <input type="hidden" name="time_limit" id="timeLimitInput">
    <input type="hidden" name="difficulty" id="difficultyInput"><div class="setup-section">
  <div class="setup-title">Select Exam Type:</div>
  <div class="exam-type-options">
    <div class="exam-type normal selected" onclick="selectExamType('normal')">
      <h3>Normal Exam</h3>
      <p>Standard exam with default settings</p>
      <small>• Random Questions<br>• Standard time limit<br>• Default difficulty</small>
    </div>
    <div class="exam-type custom" onclick="selectExamType('custom')">
      <h3>Custom Exam</h3>
      <p>Customize your settings</p>
      <small>• Choose number of questions<br>• Set time limit<br>• Select difficulty level</small>
    </div>
  </div>

  <div class="custom-settings" id="customSettings" style="display: none;">
    <div class="setting-group">
      <label class="setting-label">Number of Questions</label>
      <input type="number" class="setting-input" id="qCount" min="10" max="50" value="20">
    </div>
    <div class="setting-group">
      <label class="setting-label">Time Limit (minutes)</label>
      <input type="number" class="setting-input" id="qTime" min="15" max="120" value="30">
    </div>
    <div class="setting-group">
      <label class="setting-label">Difficulty</label>
      <select class="setting-input" id="qDiff">
        <option value="easy">Easy</option>
        <option value="medium" selected>Medium</option>
        <option value="hard">Hard</option>
        <option value="green">Tick</option>
      </select>
    </div>
  </div>
</div>

<button type="submit" class="start-exam-btn" onclick="prepareExamData(event)">Continue to Instructions</button>

  </form>
</div><script>
let selectedExamType = 'normal';
let userRole = "<?= $role ?>";
let attemptCount = <?= $attempts_today ?>;

function selectExamType(type) {
  selectedExamType = type;
  document.getElementById('examTypeInput').value = type;

  document.querySelectorAll('.exam-type').forEach(el => el.classList.remove('selected'));
  document.querySelector(`.exam-type.${type}`).classList.add('selected');

  const customSettings = document.getElementById('customSettings');
  customSettings.style.display = (type === 'custom') ? 'block' : 'none';
}

function prepareExamData(e) {
  e.preventDefault();

  if (userRole === 'free' && selectedExamType === 'custom') {
    alert("Custom exams are only for premium users.");
    return;
  }
  if (userRole === 'free' && selectedExamType === 'normal' && attemptCount >= 3) {
    alert("You have reached your daily limit of 3 normal exams.");
    return;
  }

  if (selectedExamType === 'custom') {
    document.getElementById('questionCountInput').value = document.getElementById('qCount').value;
    document.getElementById('timeLimitInput').value = document.getElementById('qTime').value;
    document.getElementById('difficultyInput').value = document.getElementById('qDiff').value;
  } else {
    document.getElementById('questionCountInput').value = 0;
    document.getElementById('timeLimitInput').value = 0;
    document.getElementById('difficultyInput').value = '';
  }

  document.getElementById('examSetupForm').submit();
}
</script>
</body><br><br>
<?php include('footer.php'); ?>
</html>
