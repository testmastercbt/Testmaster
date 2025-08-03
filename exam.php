<?php
require_once 'config.php';
session_start();

// Check required session variables
if (!isset($_SESSION['user_id'], $_SESSION['questions'], $_SESSION['exam_session_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$questions = $_SESSION['questions'];
$exam_id = $_SESSION['exam_session_id'];
$time_limit = $_SESSION['time_limit'] ?? 45;
$exam_title = $_SESSION['exam_title'] ?? 'Exam';
$name = $_SESSION['name'] ?? 'Student';
$initials = strtoupper(substr($name, 0, 1) . substr($name, strpos($name, ' ') + 1, 1));
$role = $_SESSION['role'] ?? 'free';
$matric_num = $_SESSION['matric_number'] ?? '';

$total_questions = count($questions);

// Handle exam submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam'])) {
    try {
        $answers = $_POST['answers'] ?? [];
        $total_correct = 0;
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Save responses and calculate score
        foreach ($questions as $question) {
            $question_id = $question['id'];
            $user_answer = isset($answers[$question_id]) ? (int)$answers[$question_id] : null;
            $correct_option = (int)$question['correct_option'];
            $is_correct = ($user_answer === $correct_option) ? 1 : 0;
            
            if ($is_correct) $total_correct++;
            
            // Insert response
            $stmt = $pdo->prepare("INSERT INTO responses (user_id, exam_id, question_id, user_answer, is_correct) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $exam_id, $question_id, $user_answer, $is_correct]);
        }
        
        // Calculate percentage score
        $score = $total_questions > 0 ? round(($total_correct / $total_questions) * 100, 2) : 0;
        
        // Update exam record
        $stmt = $pdo->prepare("UPDATE exams SET score = ?, time_ended = NOW() WHERE id = ?");
        $stmt->execute([$score, $exam_id]);
        
        $pdo->commit();
        
        // Clear session data
        unset($_SESSION['questions'], $_SESSION['exam_session_id'], $_SESSION['time_limit'], $_SESSION['exam_title']);
        
        // Redirect to results
        header("Location: result.php?exam_id=" . $exam_id);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = "Error submitting exam: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1024">
    <title>Exam | TestMaster</title>
    <link rel="stylesheet" href="takeexam.css">
    <link rel="stylesheet" href="exam.css">
    <style>
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
                <a href="dashboard.php" style="text-decoration: none; background: #004; color: white; border: none; padding: 7px 14px; border-radius: 8px; cursor: pointer; font-size: 0.9rem;">Dashboard</a>
            </div>
        </header>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Sidebar -->
            <aside class="sidebar">
                <a href="profile3.php" style="text-decoration: none; color: #444;">
                    <div class="profile-section">
                        <div style="overflow: hidden" class="profile-pic <?= $role === 'premium' ? '' : 'locked' ?> <?= $role === 'premium' ? 'paid-plan2' : 'free-plan2' ?>">
        <img style="width: 80px" src="uploads/<?= htmlspecialchars($matric_num) ?>.jpeg" alt="Profile Picture">
    </div>
                        <div class="user-name"><?= htmlspecialchars($name) ?></div>
                        <div class="user-email" style="font-weight: bold"><?= htmlspecialchars($matric_num) ?></div>
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
                            <div class="nav-link" onclick="showPage('results')">
                                <span>📊</span> Results
                            </div>
                        </li>
                        <li class="nav-item">
                            <div class="nav-link" onclick="showPage('settings')">
                                <span>⚙️</span> Settings
                            </div>
                        </li>
                    </ul>
                </nav>
            </aside>

            <!-- Content Area -->
            <main class="content-area">
                <form method="POST" id="examForm">
                    <div class="exam-header">
                        <div class="exam-info">
                            <div class="user-avatar" style="overflow: hidden">
        <img style="width: 50px" src="https://portal.abu.edu.ng/pixx/<?= htmlspecialchars($matric_num) ?>.JPG" alt="Profile Picture">
                            </div>
                            <div>
                                <div><?= htmlspecialchars($name) ?></div>
                                <div style="font-size: 12px; opacity: 0.8;"><?= htmlspecialchars($exam_title) ?></div>
                            </div>
                        </div>
                        <div class="exam-timer" id="examTimer"><?= $time_limit ?>:00</div>
                    </div>

                    <div class="exam-body">
                        <div class="question-area">
                            <div class="question-header">
                                <div class="question-number">Question <span id="currentQuestionNumber">1</span> of <span id="totalQuestions"><?= $total_questions ?></span></div>
                                <div class="unanswered-count">&nbsp;&nbsp;&nbsp;Unanswered: <span id="unansweredCount"><?= $total_questions ?></span></div>
                            </div>

                            <?php foreach ($questions as $index => $q): ?>
                            <div class="question-block" id="question<?= $index ?>" style="display: <?= $index === 0 ? 'block' : 'none' ?>;">
                                <div class="question-id"><?= $q['question_id'] ?></div><br>

                                <?php if (!empty($q['question_image'])): ?>
                                <div class="question-image">
                                    <img src="uploads/<?= htmlspecialchars($q['question_image']) ?>" alt="Question Image" style="max-width: 100%; height: auto;">
                                </div>
                                <?php endif; ?>

                                <ul class="options-list">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php $opt = 'option' . $i; if (!empty($q[$opt])): ?>
                                    <li class="option-item">
                                        <label class="option-label">
                                            <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $i ?>" class="option-radio" onchange="handleAnswerChange(<?= $index ?>, <?= $q['id'] ?>)">
                                            <span><?= $q[$opt] ?></span>
                                        </label>
                                    </li>
                                    <?php endif; ?>
                                    <?php endfor; ?>
                                </ul>

                                <!-- Next/Prev buttons inside the same box -->
                                <div class="question-buttons">
                                    <button type="button" class="nav-btn prev-btn" onclick="goToPrevious()" <?= $index === 0 ? 'disabled' : '' ?>>⬅ Prev</button>
                                    <button type="button" class="nav-btn next-btn" onclick="goToNext()" <?= $index === $total_questions - 1 ? 'disabled' : '' ?>>Next ➡</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                <div class="navigation-panel">
                    <div class="nav-title">Question Navigation</div>
                    <div class="question-nav" id="questionNav">
                        <?php for ($i = 1; $i <= $total_questions; $i++): ?>
                        <button type="button" class="question-nav-btn <?= $i === 1 ? 'current' : '' ?>" id="nav<?= $i ?>" onclick="goToQuestion(<?= $i - 1 ?>)">
                            <?= $i ?>
                        </button>
                        <?php endfor; ?>
                    </div>
                    <button type="button" class="calculator-btn" onclick="openCalculator()">📱 Calculator</button>
                    <button type="button" class="submit-btn" onclick="submitExam()">Submit Exam</button>
                </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <!-- Calculator Modal -->
    <div id="calculatorModal" class="calculator-modal">
        <div class="calculator-content">
            <span class="close-calc" onclick="closeCalculator()">&times;</span>
            <div class="calculator">
                <input type="text" id="calcDisplay" class="calculator-display" readonly>
                <div class="calculator-buttons">
                    <button class="calc-btn" onclick="clearCalculator()">C</button>
                    <button class="calc-btn" onclick="deleteLast()">⌫</button>
                    <button class="calc-btn operator" onclick="appendToDisplay('/')">/</button>
                    <button class="calc-btn operator" onclick="appendToDisplay('*')">×</button>
                    
                    <button class="calc-btn" onclick="appendToDisplay('7')">7</button>
                    <button class="calc-btn" onclick="appendToDisplay('8')">8</button>
                    <button class="calc-btn" onclick="appendToDisplay('9')">9</button>
                    <button class="calc-btn operator" onclick="appendToDisplay('-')">-</button>
                    
                    <button class="calc-btn" onclick="appendToDisplay('4')">4</button>
                    <button class="calc-btn" onclick="appendToDisplay('5')">5</button>
                    <button class="calc-btn" onclick="appendToDisplay('6')">6</button>
                    <button class="calc-btn operator" onclick="appendToDisplay('+')">+</button>
                    
                    <button class="calc-btn" onclick="appendToDisplay('1')">1</button>
                    <button class="calc-btn" onclick="appendToDisplay('2')">2</button>
                    <button class="calc-btn" onclick="appendToDisplay('3')">3</button>
                    <button class="calc-btn equals" onclick="calculate()" rowspan="2">=</button>
                    
                    <button class="calc-btn" onclick="appendToDisplay('0')" style="grid-column: span 2;">0</button>
                    <button class="calc-btn" onclick="appendToDisplay('.')">.</button>
                </div>
            </div>
        </div>
    </div>

    <script>

// Global variables
let currentQuestion = 0;
let totalQuestions = <?= $total_questions ?>;
let timeLimit = <?= $time_limit ?> * 60; // Convert to seconds
let timeRemaining = timeLimit;
let answeredQuestions = new Set();
let examTimer;
let beepTimer;
let hasWarningStarted = false;
let isExamSubmitted = false;
let sessionCheckInterval;
let isAccidentalReload = false;

// Add this in your DOMContentLoaded event listener to detect accidental reload
document.addEventListener('DOMContentLoaded', function() {
    
    startTimer();
    updateNavigationButtons();
    startSessionMonitoring();

    // Prevent page reload with Ctrl+R, F5, etc.
    document.addEventListener('keydown', function(e) {
        // Prevent refresh keys
        if ((e.ctrlKey && e.key === 'r') || 
    (e.key === 'F5') || 
    (e.ctrlKey && e.shiftKey && e.key === 'R')) {
    e.preventDefault();
    
    let confirmed = confirm('Reloading this page will submit your exam. Do you want to continue?');
    if (!confirmed) {
        return false;
        e.preventDefault();
    } else {
        // If they confirm, show another warning
        let finalConfirm = confirm('This will reload your exam page. Are you absolutely sure you want to continue?');
        if (finalConfirm) {
            autoSubmitExam();
        } else {
            return false;
        }
    }
}
    });

    // Prevent right-click context menu
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    });
});

// Session monitoring function
function startSessionMonitoring() {
    sessionCheckInterval = setInterval(function() {
        checkSession();
    }, 30000); // Check every 30 seconds
}

// Check if session is still active
function checkSession() {
    if (isExamSubmitted) return;
    
    fetch('check_session.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({action: 'check_session'})
    })
    .then(response => response.json())
    .then(data => {
        if (!data.session_active && !isExamSubmitted) {
            alert('Your session has expired. The exam will be automatically submitted.');
            autoSubmitExam();
        }
    })
    .catch(error => {
        console.log('Session check failed:', error);
        // If we can't check session, auto-submit as safety measure
        if (!isExamSubmitted) {
            autoSubmitExam();
        }
    });
}

// Auto-submit exam function
function autoSubmitExam() {
    if (isExamSubmitted) return;
    
    isExamSubmitted = true;
    
    // Clear timers
    if (examTimer) clearInterval(examTimer);
    if (beepTimer) clearInterval(beepTimer);
    if (sessionCheckInterval) clearInterval(sessionCheckInterval);
    
    // Collect current answers
    let formData = new FormData();
    let form = document.getElementById('examForm');
    
    // Get all radio button answers
    let radioButtons = form.querySelectorAll('input[type="radio"]:checked');
    radioButtons.forEach(radio => {
        formData.append(radio.name, radio.value);
    });
    
    formData.append('submit_exam', '1');
    formData.append('auto_submit', '1'); // Flag to indicate auto-submission
    
    // Submit via AJAX to avoid page navigation issues
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (response.ok) {
            // Redirect to results or dashboard
            window.location.href = 'dashboard.php?exam_submitted=auto';
        }
    })
    .catch(error => {
        console.error('Auto-submit failed:', error);
        // Fallback: try regular form submission
        let submitInput = document.createElement('input');
        submitInput.type = 'hidden';
        submitInput.name = 'submit_exam';
        submitInput.value = '1';
        form.appendChild(submitInput);
        
        let autoSubmitInput = document.createElement('input');
        autoSubmitInput.type = 'hidden';
        autoSubmitInput.name = 'auto_submit';
        autoSubmitInput.value = '1';
        form.appendChild(autoSubmitInput);
        
        form.submit();
    });
}

// Timer functions
function startTimer() {
    examTimer = setInterval(function() {
        timeRemaining--;
        updateTimerDisplay();
        
        // Start beeping in last 2 minutes
        if (timeRemaining <= 120 && !hasWarningStarted) {
            hasWarningStarted = true;
            startBeeping();
        }
        
        // Auto-submit when time runs out
        if (timeRemaining <= 0) {
            clearInterval(examTimer);
            if (beepTimer) clearInterval(beepTimer);
            alert('Time is up! Your exam has been submitted automatically.');
            autoSubmitExam();
        }
    }, 1000);
}

function updateTimerDisplay() {
    let minutes = Math.floor(timeRemaining / 60);
    let seconds = timeRemaining % 60;
    document.getElementById('examTimer').textContent = 
        minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
        
    // Change color when time is running out
    if (timeRemaining <= 300) { // Last 5 minutes
        document.getElementById('examTimer').style.color = '#dc3545';
    }
}

function startBeeping() {
    beepTimer = setInterval(function() {
        // Create beep sound
        let audioContext = new (window.AudioContext || window.webkitAudioContext)();
        let oscillator = audioContext.createOscillator();
        let gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        gainNode.gain.value = 0.1;
        
        oscillator.start();
        oscillator.stop(audioContext.currentTime + 0.1);
    }, 2000);
}

// Navigation functions
function goToQuestion(questionIndex) {
    if (questionIndex < 0 || questionIndex >= totalQuestions) return;
    
    // Hide current question
    document.getElementById('question' + currentQuestion).style.display = 'none';
    
    // Show new question
    currentQuestion = questionIndex;
    document.getElementById('question' + currentQuestion).style.display = 'block';
    
    // Update question number
    document.getElementById('currentQuestionNumber').textContent = (currentQuestion + 1);
    
    // Update navigation buttons
    updateNavigationButtons();
    
    // Update prev/next button states
    updatePrevNextButtons();
}

function goToNext() {
    if (currentQuestion < totalQuestions - 1) {
        goToQuestion(currentQuestion + 1);
    }
}

function goToPrevious() {
    if (currentQuestion > 0) {
        goToQuestion(currentQuestion - 1);
    }
}

function updateNavigationButtons() {
    // Remove current class from all nav buttons
    document.querySelectorAll('.question-nav-btn').forEach(btn => {
        btn.classList.remove('current');
    });
    
    // Add current class to active question
    document.getElementById('nav' + (currentQuestion + 1)).classList.add('current');
}

function updatePrevNextButtons() {
    let prevBtn = document.querySelector('.prev-btn');
    let nextBtn = document.querySelector('.next-btn');
    
    if (prevBtn) prevBtn.disabled = (currentQuestion === 0);
    if (nextBtn) nextBtn.disabled = (currentQuestion === totalQuestions - 1);
}

// Answer handling
function handleAnswerChange(questionIndex, questionId) {
    // Mark question as answered
    answeredQuestions.add(questionId);
    
    // Update navigation button appearance
    let navBtn = document.getElementById('nav' + (questionIndex + 1));
    if (navBtn && !navBtn.classList.contains('answered')) {
        navBtn.classList.add('answered');
    }
    
    // Update unanswered count
    updateUnansweredCount();
}

function updateUnansweredCount() {
    let unanswered = totalQuestions - answeredQuestions.size;
    document.getElementById('unansweredCount').textContent = unanswered;
}

// Calculator functions
function openCalculator() {
    document.getElementById('calculatorModal').style.display = 'block';
}

function closeCalculator() {
    document.getElementById('calculatorModal').style.display = 'none';
}

function appendToDisplay(value) {
    document.getElementById('calcDisplay').value += value;
}

function clearCalculator() {
    document.getElementById('calcDisplay').value = '';
}

function deleteLast() {
    let display = document.getElementById('calcDisplay');
    display.value = display.value.slice(0, -1);
}

function calculate() {
    try {
        let expression = document.getElementById('calcDisplay').value;
        expression = expression.replace(/×/g, '*');
        let result = eval(expression);
        document.getElementById('calcDisplay').value = result;
    } catch (error) {
        document.getElementById('calcDisplay').value = 'Error';
    }
}

// Exam submission
function submitExam() {
    if (isExamSubmitted) return;
    
    if (answeredQuestions.size < totalQuestions) {
        let unanswered = totalQuestions - answeredQuestions.size;
        if (!confirm(`You have ${unanswered} unanswered questions. Are you sure you want to submit?`)) {
            return;
        }
    }
    
    if (!confirm('Are you sure you want to submit your exam? This action cannot be undone.')) {
        return;
    }
    
    isExamSubmitted = true;
    
    // Clear timers
    if (examTimer) clearInterval(examTimer);
    if (beepTimer) clearInterval(beepTimer);
    if (sessionCheckInterval) clearInterval(sessionCheckInterval);
    
    // Add hidden submit field and submit form
    let form = document.getElementById('examForm');
    let submitInput = document.createElement('input');
    submitInput.type = 'hidden';
    submitInput.name = 'submit_exam';
    submitInput.value = '1';
    form.appendChild(submitInput);
    
    form.submit();
}

// Dashboard navigation
function goToDashboard() {
    if (!isExamSubmitted && !confirm('Are you sure you want to leave the exam? Your exam will be automatically submitted.')) {
        return;
    }
    if (!isExamSubmitted) {
        autoSubmitExam();
    }
}

function showPage(page) {
    if (!isExamSubmitted && !confirm('Are you sure you want to leave the exam? Your exam will be automatically submitted.')) {
        return;
    }
    
    if (!isExamSubmitted) {
        autoSubmitExam();
        return;
    }
    
    // Handle page navigation based on page parameter
    switch(page) {
        case 'courses':
            window.location.href = 'courses.php';
            break;
        case 'course-selection':
            window.location.href = 'examsetup1.php';
            break;
        case 'results':
            window.location.href = 'results.php';
            break;
        case 'settings':
            window.location.href = 'settings.php';
            break;
        default:
            window.location.href = 'dashboard.php';
    }
}

// Close calculator when clicking outside
window.onclick = function(event) {
    let modal = document.getElementById('calculatorModal');
    if (event.target == modal) {
        closeCalculator();
    }
}

    </script>

    <?php if (isset($error_message)): ?>
    <script>
        alert('<?= htmlspecialchars($error_message) ?>');
    </script>
    <?php endif; ?>
</body>
<?php include('footer.php'); ?>
</html>