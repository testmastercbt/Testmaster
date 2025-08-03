<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['questions']) || !isset($_SESSION['exam_session_id'])) {
    die("Unauthorized access.");
}

$user_id = $_SESSION['user_id'];
$questions = $_SESSION['questions'];
$exam_id = $_SESSION['exam_session_id'];
$exam_title = $_SESSION['exam_title'] ?? "Exam";
$time_limit = $_SESSION['time_limit'] ?? 30;
$name = $_SESSION['name'] ?? "Student";
$matric_number = $_SESSION['matric_number'] ?? "N/A";
$initials = strtoupper(substr($name, 0, 1) . substr(strstr($name, " "), 1, 1));
$role = $_SESSION['role'] ?? "free";
$total_questions = count($questions);

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['answers'])) {
    $answers = $_POST['answers'];
    $score = 0;

    foreach ($questions as $q) {
        $qid = $q['id'];
        $correct = $q['correct_option'];
        $user_answer = $answers[$qid] ?? null;
        $is_correct = $user_answer == $correct ? 1 : 0;
        if ($is_correct) $score++;

        // Insert response
        $stmt = $conn->prepare("INSERT INTO responses (user_id, exam_id, question_id, user_answer, is_correct)
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisi", $user_id, $exam_id, $qid, $user_answer, $is_correct);
        $stmt->execute();
    }

    // Update exam session
    $percent = ($total_questions > 0) ? round(($score / $total_questions) * 100, 2) : 0;
    $stmt = $conn->prepare("UPDATE exams SET score = ?, time_ended = NOW() WHERE id = ?");
    $stmt->bind_param("di", $percent, $exam_id);
    $stmt->execute();

    // Redirect
    header("Location: result.php?exam_id=$exam_id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Take Exam | TestMaster</title>
  <link rel="stylesheet" href="takeexam.css">
  <style>
    .question-nav-btn.current {
        border-color: #004;
        background: #004;
        color: white;
    }
    .question-nav-btn.answered {
        background: #4caf50;
        color: white;
        border-color: #4caf50;
    }
    .question-nav-btn.answered::after {
        content: '✓';
        font-size: 10px;
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

        .free-plan2 {
            border: 3px solid #c62828;;
        }

        .paid-plan2 {
            border: 3px solid #2e7d32;
        }

        .user-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
            color: #004;
        }

        .user-email {
            color: #666;
            font-size: 14px;
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
                <a href="dashboard.php" style="text-decoration: none; background: #004; color: white; border: none; padding: 7px 14px; border-radius: 8px; cursor: pointer; font-size: 0.9rem;">Dashboard</a>
            </div>
        </header>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Sidebar -->
            <aside class="sidebar">
              <a href="profile.php" style="text-decoration: none; color: #444; ">
                    <div class="profile-section">
                        <div class="profile-pic <?= $role === 'premium' ? '' : 'locked' ?> <?= $role === 'premium' ? 'paid-plan2' : 'free-plan2' ?>"><?= $initials ?></div>
                        <div class="user-name"><?= htmlspecialchars($name) ?></div>
                        <div class="user-email" style="font-weight: bold"><?= htmlspecialchars($matric_number) ?></div>
                    </div>
                </a>
                <nav>
                    <ul class="nav-menu">
                        <li class="nav-item">
                            <div class="nav-link" onclick="goToDashboard()">
                                <span>🏠</span> Dashboard
                            </div>
                        </li>
                        <li class="nav-item">
                            <div class="nav-link" onclick="showPage('courses')">
                                <span>📚</span> My Courses
                            </div>
                        </li>
                        <li class="nav-item">
                            <div class="nav-link active" onclick="showPage('course-selection')">
                                <span>📝</span> Take Exam
                            </div>
                        </li>
                        <li class="nav-item">
                            <div class="nav-link">
                                <span>📊</span> Results
                            </div>
                        </li>
                        <li class="nav-item">
                            <div class="nav-link">
                                <span>⚙️</span> Settings
                            </div>
                        </li>
                    </ul>
                </nav>
            </aside>

            <!-- Content Area -->
            <main class="content-area">
<form method="post">
<div id="exam-interface">
    <div class="exam-header">
        <div class="exam-info">
            <div class="user-avatar"><?= $initials ?></div>
            <div>
                <div><?= $name ?></div>
                <div style="font-size: 12px; opacity: 0.8;"><?= $exam_title ?></div>
            </div>
        </div>
        <div class="exam-timer" id="examTimer"><?= $time_limit ?>:00</div>
    </div>

    <div class="exam-body">
        <div class="question-area">
            <div class="question-header">
                <div class="question-number">Question <span id="currentQuestionNumber">1</span> of <span id="totalQuestions"><?= $total_questions ?></span></div>
                <div class="unanswered-count">Unanswered: <span id="unansweredCount"><?= $total_questions ?></span></div>
            </div>

            <?php foreach ($questions as $index => $q): ?>
            <div class="question-block" id="question<?= $index ?>" style="display: <?= $index === 0 ? 'block' : 'none' ?>;">
                <div class="question-id"><?= htmlspecialchars($q['question_id']) ?></div>

                <?php if (!empty($q['question_image'])): ?>
                    <div class="question-image"><img src="uploads/<?= $q['question_image'] ?>" alt="Image"></div>
                <?php endif; ?>
                  <br>
                <ul class="options-list">
                    <?php for ($i = 1; $i <= 5; $i++):
                        $opt = 'option' . $i;
                        if (!empty($q[$opt])): ?>
                            <li class="option-item">
                                <label class="option-label">
                                    <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $i ?>" class="option-radio">
                                    <span><?= htmlspecialchars($q[$opt]) ?></span>
                                </label>
                            </li>
                    <?php endif; endfor; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="navigation-panel">
            <div class="nav-title">Question Navigation</div>
            <div class="question-nav" id="questionNav">
                <?php for ($i = 1; $i <= $total_questions; $i++): ?>
                    <button type="button" class="question-nav-btn" onclick="goToQuestion(<?= $i - 1 ?>)" id="nav<?= $i ?>"><?= $i ?></button>
                <?php endfor; ?>
            </div>
            <div style="margin-top: 10px;">
                <button type="button" onclick="goPrev()">⬅️ Prev</button>
                <button type="button" onclick="goNext()">Next ➡️</button>
            </div>
            <button type="button" onclick="openCalculator()">📱 Calculator</button>
            <button type="submit" class="submit-btn" name="submit_exam" onclick="return confirm('Submit exam?')">Submit Exam</button>
        </div>
    </div>
</div>
</form>

<!-- Calculator Modal -->
<div class="calculator-modal" id="calculatorModal" style="display:none">
    <div class="calculator">
        <input type="text" class="calc-display" id="calcDisplay" readonly>
        <div class="calc-buttons">
            <button onclick="clearCalculator()">C</button>
            <button onclick="deleteLast()">⌫</button>
            <button onclick="appendToDisplay('/')">/</button>
            <button onclick="appendToDisplay('*')">×</button>
            <button onclick="appendToDisplay('7')">7</button>
            <button onclick="appendToDisplay('8')">8</button>
            <button onclick="appendToDisplay('9')">9</button>
            <button onclick="appendToDisplay('-')">-</button>
            <button onclick="appendToDisplay('4')">4</button>
            <button onclick="appendToDisplay('5')">5</button>
            <button onclick="appendToDisplay('6')">6</button>
            <button onclick="appendToDisplay('+')">+</button>
            <button onclick="appendToDisplay('1')">1</button>
            <button onclick="appendToDisplay('2')">2</button>
            <button onclick="appendToDisplay('3')">3</button>
            <button onclick="calculate()">=</button>
            <button onclick="appendToDisplay('0')">0</button>
            <button onclick="appendToDisplay('.')">.</button>
        </div>
        <button onclick="closeCalculator()">Close</button>
    </div>
</div>
                </main>

<script>
let currentIndex = 0;
const totalQuestions = <?= $total_questions ?>;
const blocks = document.querySelectorAll('.question-block');

function goToQuestion(index) {
    blocks[currentIndex].style.display = 'none';
    blocks[index].style.display = 'block';
    document.getElementById('currentQuestionNumber').textContent = index + 1;
    document.querySelectorAll('.question-nav-btn').forEach(btn => btn.classList.remove('current'));
    document.getElementById('nav' + (index + 1)).classList.add('current');
    currentIndex = index;
}
function goNext() {
    if (currentIndex < totalQuestions - 1) goToQuestion(currentIndex + 1);
}
function goPrev() {
    if (currentIndex > 0) goToQuestion(currentIndex - 1);
}
function openCalculator() {
    document.getElementById('calculatorModal').style.display = 'block';
}
function closeCalculator() {
    document.getElementById('calculatorModal').style.display = 'none';
}
function clearCalculator() {
    document.getElementById('calcDisplay').value = '';
}
function deleteLast() {
    const d = document.getElementById('calcDisplay');
    d.value = d.value.slice(0, -1);
}
function appendToDisplay(val) {
    document.getElementById('calcDisplay').value += val;
}
function calculate() {
    try {
        const d = document.getElementById('calcDisplay');
        d.value = eval(d.value);
    } catch {
        alert("Invalid input");
    }
}
document.querySelectorAll('input[type=radio]').forEach(radio => {
    radio.addEventListener('change', () => {
        const qid = radio.name.match(/\d+/)[0];
        const btn = document.getElementById('nav' + qid);
        if (btn) btn.classList.add('answered');
        updateUnanswered();
    });
});
function updateUnanswered() {
    let unanswered = 0;
    blocks.forEach(block => {
        if (!block.querySelector('input[type="radio"]:checked')) unanswered++;
    });
    document.getElementById('unansweredCount').textContent = unanswered;
}
updateUnanswered();
goToQuestion(0);
</script>
</body>
</html>
