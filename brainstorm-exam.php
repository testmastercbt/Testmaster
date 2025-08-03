<?php
// brainstorm-exam.php - FIXED VERSION
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$name = $_SESSION['name'];
$matric_num = $_SESSION['matric_number'];
$matric = strtoupper($_SESSION['matric_number']);
$role = $_SESSION['role'];
$initials = strtoupper(substr($name, 0, 1) . substr($name, strpos($name, ' ') + 1, 1));

if (!$exam_id) {
    die("Invalid exam ID");
}

// Get exam details
$stmt = $pdo->prepare("
    SELECT be.*, bc.course_title, bc.course_code 
    FROM brainstorm_exams be 
    JOIN brainstorm_courses bc ON bc.id = be.course_id 
    WHERE be.id = ? AND be.user_id = ? AND be.status = 'active'
");
$stmt->execute([$exam_id, $user_id]);
$exam = $stmt->fetch();

if (!$exam) {
    die("Exam not found or already completed");
}

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get 20 random questions
$stmt = $pdo->prepare("SELECT * FROM brainstorm_questions WHERE course_id = ? ORDER BY RAND() LIMIT 20");
$stmt->execute([$exam['course_id']]);
$questions = $stmt->fetchAll();

if (count($questions) < 20) {
    die("Not enough questions available");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_exam'])) {
    try {
        $pdo->beginTransaction();
        
        // Enhanced debugging
        error_log("=== EXAM SUBMISSION DEBUG ===");
        error_log("Raw POST data: " . json_encode($_POST, JSON_PRETTY_PRINT));
        error_log("Expected question IDs: " . implode(',', array_column($questions, 'id')));
        
        // FIXED: Better answer collection with multiple fallback methods
        $answers = [];
        
        // Method 1: Check standard answers array
        if (isset($_POST['answers']) && is_array($_POST['answers'])) {
            foreach ($_POST['answers'] as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $answers[$key] = $value;
                }
            }
        }
        
        // Method 2: Check for individual answer fields (fallback)
        foreach ($questions as $question) {
            $qid = $question['id'];
            $fieldName = "answers[{$qid}]";
            
            if (isset($_POST[$fieldName]) && $_POST[$fieldName] !== '' && $_POST[$fieldName] !== null) {
                $answers[$qid] = $_POST[$fieldName];
            }
            
            // Additional check for direct field names
            if (isset($_POST["question_{$qid}"]) && $_POST["question_{$qid}"] !== '') {
                $answers[$qid] = $_POST["question_{$qid}"];
            }
        }
        
        // Method 3: Parse all POST fields that look like answers
        foreach ($_POST as $key => $value) {
            if (preg_match('/^answers\[(\d+)\]$/', $key, $matches)) {
                $questionId = (int)$matches[1];
                if ($value !== '' && $value !== null) {
                    $answers[$questionId] = $value;
                }
            }
        }
        
        $isAutoSubmit = isset($_POST['auto_submit']);
        $isAjaxSubmit = isset($_POST['ajax_submit']);
        
        // Enhanced debugging
        error_log("Processed answers: " . json_encode($answers, JSON_PRETTY_PRINT));
        error_log("Number of answers collected: " . count($answers));
        error_log("Number of questions expected: " . count($questions));
        
        // Initialize counters
        $correctAnswers = 0;
        $totalQuestions = count($questions);
        $answeredQuestions = 0;
        $unansweredQuestions = 0;
        
        // FIXED: Clear existing responses first
        $stmt = $pdo->prepare("DELETE FROM brainstorm_responses WHERE exam_id = ? AND user_id = ?");
        $stmt->execute([$exam_id, $user_id]);
        error_log("Cleared existing responses for exam_id: $exam_id, user_id: $user_id");
        
        // Process each question
        foreach ($questions as $questionIndex => $question) {
            $questionId = (int)$question['id'];
            $correctOption = (int)$question['correct_option'];
            
            // Check if question was answered
            $userAnswer = null;
            $isCorrect = 0;
            
            // FIXED: More thorough answer checking
            if (isset($answers[$questionId])) {
                $userAnswerRaw = $answers[$questionId];
                $userAnswer = (int)$userAnswerRaw;
                
                // Validate answer is in valid range (1-4)
                if ($userAnswer >= 1 && $userAnswer <= 4) {
                    $answeredQuestions++;
                    
                    // Check if correct
                    if ($userAnswer === $correctOption) {
                        $isCorrect = 1;
                        $correctAnswers++;
                    }
                    
                    error_log("Question {$questionId}: User answered {$userAnswer}, Correct: {$correctOption}, Match: " . ($isCorrect ? 'YES' : 'NO'));
                } else {
                    error_log("Question {$questionId}: Invalid answer value {$userAnswer}");
                    $userAnswer = null; // Treat as unanswered
                    $unansweredQuestions++;
                }
            } else {
                $unansweredQuestions++;
                error_log("Question {$questionId}: No answer provided");
            }
            
            // FIXED: Proper NULL handling for database insertion
            $stmt = $pdo->prepare("
                INSERT INTO brainstorm_responses 
                (exam_id, user_id, course_id, question_id, user_answer, is_correct, time_submitted) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $insertResult = $stmt->execute([
                $exam_id, 
                $user_id, 
                $exam['course_id'], 
                $questionId, 
                $userAnswer, // Can be NULL for unanswered questions
                $isCorrect
            ]);
            
            if (!$insertResult) {
                error_log("FAILED to insert response for question {$questionId}");
                error_log("PDO Error Info: " . print_r($stmt->errorInfo(), true));
                throw new Exception("Failed to insert response for question {$questionId}");
            }
            
            error_log("SUCCESS: Inserted response - Question: {$questionId}, Answer: " . 
                     ($userAnswer ?? 'NULL') . ", Correct: {$isCorrect}");
        }
        
        // Verify all responses were inserted
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM brainstorm_responses WHERE exam_id = ? AND user_id = ?");
        $stmt->execute([$exam_id, $user_id]);
        $insertedCount = $stmt->fetchColumn();
        
        if ($insertedCount != $totalQuestions) {
            throw new Exception("Response insertion verification failed. Expected: {$totalQuestions}, Found: {$insertedCount}");
        }
        
        // Calculate score
        $scorePercentage = ($totalQuestions > 0) ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;
        
        // Enhanced logging
        error_log("=== FINAL SUBMISSION SUMMARY ===");
        error_log("Total Questions: {$totalQuestions}");
        error_log("Answered Questions: {$answeredQuestions}");
        error_log("Unanswered Questions: {$unansweredQuestions}");
        error_log("Correct Answers: {$correctAnswers}");
        error_log("Score Percentage: {$scorePercentage}%");
        error_log("Inserted Responses: {$insertedCount}");
        
        // Update exam status
        $stmt = $pdo->prepare("
            UPDATE brainstorm_exams 
            SET time_ended = NOW(), status = 'inactive' 
            WHERE id = ? AND user_id = ?
        ");
        $updateResult = $stmt->execute([$exam_id, $user_id]);
        
        if (!$updateResult) {
            throw new Exception("Failed to update exam status");
        }
        
        // Insert/Update score
        $stmt = $pdo->prepare("
            INSERT INTO brainstorm_scores (user_id, course_id, exam_id, score, date_taken) 
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE score = VALUES(score), date_taken = VALUES(date_taken)
        ");
        $scoreResult = $stmt->execute([$user_id, $exam['course_id'], $exam_id, $scorePercentage]);
        
        if (!$scoreResult) {
            throw new Exception("Failed to insert/update score");
        }
        
        // Log activity
        $action = $isAutoSubmit ? 
            "Auto-submitted brainstorm exam: {$exam['course_code']} - {$exam['course_title']} (Score: {$scorePercentage}%, {$correctAnswers}/{$totalQuestions})" :
            "Submitted brainstorm exam: {$exam['course_code']} - {$exam['course_title']} (Score: {$scorePercentage}%, {$correctAnswers}/{$totalQuestions})";
            
        $stmt = $pdo->prepare("INSERT INTO activities (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $action]);
        
        $pdo->commit();
        error_log("=== SUBMISSION COMPLETED SUCCESSFULLY ===");
        
        // Handle response
        if ($isAjaxSubmit) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'score' => $scorePercentage,
                'correct_answers' => $correctAnswers,
                'total_questions' => $totalQuestions,
                'answered_questions' => $answeredQuestions,
                'unanswered_questions' => $unansweredQuestions,
                'message' => 'Exam submitted successfully!'
            ]);
            exit;
        }
        
        header("Location: registered-exams.php?submitted=1&score=" . $scorePercentage);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        
        error_log("=== EXAM SUBMISSION ERROR ===");
        error_log("Error message: " . $e->getMessage());
        error_log("Error trace: " . $e->getTraceAsString());
        
        if ($isAjaxSubmit) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Failed to submit exam. Please try again.',
                'debug_error' => $e->getMessage()
            ]);
            exit;
        }
        
        die("Error submitting exam. Please contact support. Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=1024">
    <title>Brainstorm Exam | TestMaster</title>
    <link rel="stylesheet" href="takeexam.css">
    <link rel="stylesheet" href="exam.css">
    <style>
        .navigation-panel {
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            width: 250px;
            margin-left: 0px;
        }
        .nav-title {
            font-weight: bold;
            margin-bottom: 10px;
        }
        .question-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .question-nav-btn {
            width: 40px;
            height: 40px;
            background: #eee;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            position: relative;
        }
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
            position: absolute;
            top: 2px;
            right: 5px;
            font-size: 10px;
            color: white;
        }
        .submit-btn {
            width: 100%;
            margin-top: 10px;
            padding: 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            background: #28a745;
            color: white;
            font-weight: bold;
        }
        .submit-btn:hover {
            background: #218838;
        }
        .success-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .success-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 30px;
            border: 1px solid #888;
            width: 400px;
            border-radius: 10px;
            text-align: center;
        }
        .success-icon {
            font-size: 48px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .success-title {
            font-size: 24px;
            font-weight: bold;
            color: #004;
            margin-bottom: 15px;
        }
        .success-score {
            font-size: 20px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .success-btn {
            background: #004;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
    </style>
</head>
<body style="min-width: 1024px;">
    <div class="container">
        <header class="header">
            <div class="logo">TestMaster</div>
            <div class="user-info">
                <span class="subscription-badge <?= $role === 'premium' ? 'paid-plan' : 'free-plan' ?>">
                    <?= ucfirst($role) ?> Plan
                </span>
                <a href="dashboard.php" style="text-decoration: none; background: #004; color: white; border: none; padding: 7px 14px; border-radius: 8px; cursor: pointer; font-size: 0.9rem;">Dashboard</a>
            </div>
        </header>

        <div class="main-content">
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

            <main class="content-area">
                <div class="event-h" style="padding: 10px 20px; width: 100%; text-align: center; color: #004; font-weight: 500;">
                    <p>COMPUTER ENGINEERING BRAINSTORM</p>
                </div>
                
                <!-- FIXED: Improved form structure with better data collection -->
                <form method="POST" id="examForm" onsubmit="return validateForm()">
                    <!-- Hidden fields for validation -->
                    <input type="hidden" name="total_questions" value="<?= count($questions) ?>">
                    <input type="hidden" name="question_ids" value="<?= implode(',', array_column($questions, 'id')) ?>">
                    
                    <div class="exam-header">
                        <div class="exam-info">
                            <div style="overflow: hidden" class="user-avatar">
                                <img style="width: 50px" src="https://portal.abu.edu.ng/pixx/<?= htmlspecialchars($matric_num) ?>.JPG" alt="Profile Picture">
                            </div>
                            <div>
                                <div><?= htmlspecialchars($name) ?></div>
                                <div style="font-size: 12px; opacity: 0.8;"><?= htmlspecialchars($exam['course_code']) ?> - <?= htmlspecialchars($exam['course_title']) ?></div>
                            </div>
                        </div>
                        <div style="color: #fff;" class="exam-timer" id="examTimer">30:00</div>
                    </div>

                    <div class="exam-body">
                        <div class="question-area">
                            <div class="question-header">
                                <div class="question-number">Question <span id="currentQuestionNumber">1</span> of <span id="totalQuestions"><?= count($questions) ?></span></div>
                                <div class="unanswered-count">&nbsp;&nbsp;&nbsp;Unanswered: <span id="unansweredCount"><?= count($questions) ?></span></div>
                            </div>

                            <?php foreach ($questions as $index => $q): ?>
                            <div class="question-block" id="question<?= $index ?>" style="display: <?= $index === 0 ? 'block' : 'none' ?>;">
                                <div class="question-id"><?= htmlspecialchars($q['question_text']) ?></div><br>

                                <ul class="options-list">
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <?php $opt = 'option' . $i; if (!empty($q[$opt])): ?>
                                    <li class="option-item">
                                        <label class="option-label">
                                            <!-- FIXED: Improved radio button structure with better data handling -->
                                            <input 
                                                type="radio" 
                                                name="answers[<?= $q['id'] ?>]" 
                                                value="<?= $i ?>" 
                                                class="option-radio" 
                                                data-question-id="<?= $q['id'] ?>"
                                                data-question-index="<?= $index ?>"
                                                onchange="handleAnswerChange(<?= $index ?>, <?= $q['id'] ?>)"
                                                required="false">
                                            <span><?= htmlspecialchars($q[$opt]) ?></span>
                                        </label>
                                    </li>
                                    <?php endif; ?>
                                    <?php endfor; ?>
                                </ul>

                                <div class="question-buttons">
                                    <button type="button" class="nav-btn prev-btn" onclick="goToPrevious()" <?= $index === 0 ? 'disabled' : '' ?>>⬅ Prev</button>
                                    <button type="button" class="nav-btn next-btn" onclick="goToNext()" <?= $index === count($questions) - 1 ? 'disabled' : '' ?>>Next ➡</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="navigation-panel">
                            <div class="nav-title">Question Navigation</div>
                            <div class="question-nav" id="questionNav">
                                <?php for ($i = 1; $i <= count($questions); $i++): ?>
                                <button type="button" class="question-nav-btn <?= $i === 1 ? 'current' : '' ?>" id="nav<?= $i ?>" onclick="goToQuestion(<?= $i - 1 ?>)">
                                    <?= $i ?>
                                </button>
                                <?php endfor; ?>
                            </div>
                            <button type="button" class="calculator-btn" onclick="openCalculator()">📱 Calculator</button>
                            <button type="submit" class="submit-btn" name="submit_exam" onclick="return submitExam()">Submit Exam</button>
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

    <!-- Success Modal -->
    <div id="successModal" class="success-modal">
        <div class="success-content">
            <div class="success-icon">✅</div>
            <div class="success-title">Exam Submitted Successfully!</div>
            <button class="success-btn" onclick="goToResults()">View Results</button>
        </div>
    </div>

<script>
// ENHANCED JAVASCRIPT WITH BETTER ANSWER COLLECTION

let currentQuestion = 0;
let totalQuestions = <?= count($questions) ?>;
let timeRemaining = 30 * 60;
let answeredQuestions = new Set();
let isSubmitted = false;
let examTimer;
let warningShown = false;
let questionIds = [<?= implode(',', array_column($questions, 'id')) ?>];

document.addEventListener('DOMContentLoaded', function() {
    startTimer();
    setupEventListeners();
    preventCheating();
    updateTimerDisplay();
    checkExistingAnswers();
    
    // FIXED: Add form validation
    setupFormValidation();
});

// FIXED: Enhanced form validation
function setupFormValidation() {
    let form = document.getElementById('examForm');
    form.addEventListener('submit', function(e) {
        if (!validateAnswers()) {
            e.preventDefault();
            return false;
        }
    });
}

// FIXED: Comprehensive answer validation
function validateAnswers() {
    let allAnswers = {};
    let collectedCount = 0;
    
    // Collect all answers using multiple methods
    questionIds.forEach(questionId => {
        // Method 1: Standard radio button check
        let selectedOption = document.querySelector(`input[name="answers[${questionId}]"]:checked`);
        if (selectedOption) {
            allAnswers[questionId] = selectedOption.value;
            collectedCount++;
        }
        
        // Method 2: Alternative field name check
        let altOption = document.querySelector(`input[name="question_${questionId}"]:checked`);
        if (altOption && !allAnswers[questionId]) {
            allAnswers[questionId] = altOption.value;
            collectedCount++;
        }
    });
    
    console.log('Validation - Collected answers:', allAnswers);
    console.log('Validation - Answer count:', collectedCount);
    console.log('Validation - Total questions:', totalQuestions);
    
    return true; // Allow submission regardless of answer count
}

function checkExistingAnswers() {
    questionIds.forEach((questionId, index) => {
        let answeredOption = document.querySelector(`input[name="answers[${questionId}]"]:checked`);
        if (answeredOption) {
            handleAnswerChange(index, questionId);
        }
    });
}

function startTimer() {
    examTimer = setInterval(function() {
        if (timeRemaining > 0 && !isSubmitted) {
            timeRemaining--;
            updateTimerDisplay();
            
            if (timeRemaining <= 300 && !warningShown) {
                warningShown = true;
                showTimeWarning();
            }
            
            if (timeRemaining <= 0) {
                clearInterval(examTimer);
                showTimeUpAlert();
                autoSubmitExam();
            }
        }
    }, 1000);
}

function updateTimerDisplay() {
    let minutes = Math.floor(timeRemaining / 60);
    let seconds = timeRemaining % 60;
    let timerText = minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
    
    let timerElement = document.getElementById('examTimer');
    if (timerElement) {
        timerElement.innerText = timerText;
        
        if (timeRemaining <= 300) {
            timerElement.style.color = '#dc3545';
            timerElement.style.fontWeight = 'bold';
            timerElement.style.animation = 'pulse 1s infinite';
        } else if (timeRemaining <= 600) {
            timerElement.style.color = '#fd7e14';
            timerElement.style.fontWeight = 'bold';
        } else {
            timerElement.style.color = '#fff';
            timerElement.style.fontWeight = 'normal';
            timerElement.style.animation = 'none';
        }
    }
}

function showTimeWarning() {
    let warningDiv = document.createElement('div');
    warningDiv.innerHTML = `
        <div style="position: fixed; top: 20px; right: 20px; background: #dc3545; color: white; 
                    padding: 15px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                    z-index: 1000; font-weight: bold; border-left: 5px solid #fff;">
            ⚠️ Warning: Only 5 minutes remaining!
            <button onclick="this.parentElement.remove()" style="background: none; border: none; 
                    color: white; float: right; font-size: 18px; cursor: pointer; margin-left: 10px;">×</button>
        </div>
    `;
    document.body.appendChild(warningDiv);
    
    setTimeout(() => {
        if (warningDiv.parentElement) {
            warningDiv.remove();
        }
    }, 5000);
}

function showTimeUpAlert() {
    alert('⏰ Time is up! Your exam is being submitted automatically.');
}

function goToQuestion(index) {
    if (index < 0 || index >= totalQuestions || isSubmitted) return;
    
    let currentQuestionElement = document.getElementById('question' + currentQuestion);
    if (currentQuestionElement) {
        currentQuestionElement.style.display = 'none';
    }
    
    currentQuestion = index;
    let newQuestionElement = document.getElementById('question' + currentQuestion);
    if (newQuestionElement) {
        newQuestionElement.style.display = 'block';
    }
    
    let questionNumberElement = document.getElementById('currentQuestionNumber');
    if (questionNumberElement) {
        questionNumberElement.textContent = index + 1;
    }
    
    updateNavigationButtons();
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
    document.querySelectorAll('.question-nav-btn').forEach(btn => {
        btn.classList.remove('current');
    });
    
    let currentBtn = document.getElementById('nav' + (currentQuestion + 1));
    if (currentBtn) {
        currentBtn.classList.add('current');
    }
}

function updatePrevNextButtons() {
    let prevBtn = document.querySelector('.prev-btn');
    let nextBtn = document.querySelector('.next-btn');
    
    if (prevBtn) prevBtn.disabled = (currentQuestion === 0);
    if (nextBtn) nextBtn.disabled = (currentQuestion === totalQuestions - 1);
}

// FIXED: Enhanced answer change handling with better validation
function handleAnswerChange(questionIndex, questionId) {
    console.log(`Answer changed for question ${questionId} (index ${questionIndex})`);
    
    // Verify the answer was actually selected
    let selectedOption = document.querySelector(`input[name="answers[${questionId}]"]:checked`);
    if (selectedOption) {
        console.log(`Question ${questionId} answered with option ${selectedOption.value}`);
        answeredQuestions.add(questionId);
        
        // Update navigation button appearance
        let navBtn = document.getElementById('nav' + (questionIndex + 1));
        if (navBtn && !navBtn.classList.contains('answered')) {
            navBtn.classList.add('answered');
            navBtn.style.transform = 'scale(1.1)';
            setTimeout(() => {
                navBtn.style.transform = 'scale(1)';
            }, 200);
        }
    } else {
        console.log(`No answer found for question ${questionId}`);
        answeredQuestions.delete(questionId);
        
        // Remove answered class if no answer
        let navBtn = document.getElementById('nav' + (questionIndex + 1));
        if (navBtn) {
            navBtn.classList.remove('answered');
        }
    }
    
    updateUnansweredCount();
}

function updateUnansweredCount() {
    let unanswered = totalQuestions - answeredQuestions.size;
    let countElement = document.getElementById('unansweredCount');
    if (countElement) {
        countElement.textContent = unanswered;
        
        if (unanswered === 0) {
            countElement.style.color = '#28a745';
            countElement.style.fontWeight = 'bold';
        } else if (unanswered <= 5) {
            countElement.style.color = '#fd7e14';
        } else {
            countElement.style.color = '#dc3545';
        }
    }
}

// FIXED: Enhanced submit function with comprehensive answer collection
function submitExam() {
    if (isSubmitted) return false;
    
    console.log('=== SUBMIT EXAM DEBUG ===');
    
    // Collect all answers before validation
    let collectedAnswers = {};
    let answerCount = 0;
    
    questionIds.forEach(questionId => {
        let selectedOption = document.querySelector(`input[name="answers[${questionId}]"]:checked`);
        if (selectedOption) {
            collectedAnswers[questionId] = selectedOption.value;
            answerCount++;
            console.log(`Question ${questionId}: Answer ${selectedOption.value}`);
        } else {
            console.log(`Question ${questionId}: No answer selected`);
        }
    });
    
    console.log('Total answers collected:', answerCount);
    console.log('Total questions:', totalQuestions);
    console.log('Collected answers object:', collectedAnswers);
    
    let submitBtn = document.querySelector('.submit-btn');
    if (submitBtn) {
        submitBtn.disabled = true;
    }
    
    let unanswered = totalQuestions - answerCount;
    if (unanswered > 0) {
        let confirmMessage = `You have ${unanswered} unanswered question(s). ` + 
                           `Unanswered questions will be marked as incorrect.\n\n` +
                           `Are you sure you want to submit your exam?`;
        
        if (!confirm(confirmMessage)) {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
            return false;
        }
    }
    
    if (!confirm('Are you sure you want to submit your exam? This action cannot be undone.')) {
        if (submitBtn) {
            submitBtn.disabled = false;
        }
        return false;
    }
    
    performSubmission(false);
    return true;
}

function autoSubmitExam() {
    if (isSubmitted) return;
    performSubmission(true);
}

// FIXED: Enhanced submission with better error handling
function performSubmission(isAutoSubmit = false) {
    if (isSubmitted) return;
    
    isSubmitted = true;
    
    if (examTimer) {
        clearInterval(examTimer);
    }
    
    let submitBtn = document.querySelector('.submit-btn');
    if (submitBtn) {
        submitBtn.innerHTML = '<span>⏳ Submitting...</span>';
        submitBtn.disabled = true;
        submitBtn.style.background = '#6c757d';
    }
    
    showLoadingOverlay();
    
    // FIXED: Enhanced form data collection with debugging
    let formData = new FormData();
    
    // Add basic form data
    formData.append('submit_exam', '1');
    formData.append('ajax_submit', '1');
    formData.append('total_questions', totalQuestions);
    
    if (isAutoSubmit) {
        formData.append('auto_submit', '1');
    }
    
    // FIXED: Comprehensive answer collection
    let answersCollected = 0;
    questionIds.forEach(questionId => {
        let selectedOption = document.querySelector(`input[name="answers[${questionId}]"]:checked`);
        if (selectedOption) {
            formData.append(`answers[${questionId}]`, selectedOption.value);
            answersCollected++;
            console.log(`Added to FormData: answers[${questionId}] = ${selectedOption.value}`);
        } else {
            console.log(`No answer for question ${questionId} - will be marked as unanswered`);
        }
    });
    
    formData.append('answered_count', answersCollected);
    
    console.log('FormData summary:');
    console.log('- Total questions:', totalQuestions);
    console.log('- Answers collected:', answersCollected);
    console.log('- Is auto submit:', isAutoSubmit);
    
    // Submit with timeout
    let controller = new AbortController();
    let timeoutId = setTimeout(() => controller.abort(), 30000);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        signal: controller.signal
    })
    .then(response => {
        clearTimeout(timeoutId);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return response.json();
    })
    .then(data => {
        hideLoadingOverlay();
        
        if (data.success) {
            showSuccessModal(data.score, data.correct_answers, data.total_questions);
        } else {
            throw new Error(data.error || 'Submission failed');
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        hideLoadingOverlay();
        
        console.error('AJAX submission failed:', error);
        alert('There was an error submitting your exam. Attempting alternative submission method...');
        
        performFallbackSubmission(isAutoSubmit);
    });
}

function performFallbackSubmission(isAutoSubmit) {
    let form = document.getElementById('examForm');
    
    let submitInput = document.createElement('input');
    submitInput.type = 'hidden';
    submitInput.name = 'submit_exam';
    submitInput.value = '1';
    form.appendChild(submitInput);
    
    if (isAutoSubmit) {
        let autoInput = document.createElement('input');
        autoInput.type = 'hidden';
        autoInput.name = 'auto_submit';
        autoInput.value = '1';
        form.appendChild(autoInput);
    }
    
    form.submit();
}

function showLoadingOverlay() {
    let overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                    background: rgba(0,0,0,0.7); z-index: 2000; display: flex; 
                    justify-content: center; align-items: center;">
            <div style="background: white; padding: 30px; border-radius: 10px; text-align: center;">
                <div style="font-size: 24px; margin-bottom: 20px;">⏳</div>
                <div style="font-size: 18px; font-weight: bold; color: #004;">Submitting your exam...</div>
                <div style="font-size: 14px; color: #666; margin-top: 10px;">Please do not close this window</div>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
}

function hideLoadingOverlay() {
    let overlay = document.getElementById('loadingOverlay');
    if (overlay) {
        overlay.remove();
    }
}

function showSuccessModal(score, correct, total) {
    let modal = document.getElementById('successModal');
    let modalContent = modal.querySelector('.success-content');
    
    let percentage = Math.round((correct / total) * 100);
    let grade = getGradeFromScore(percentage);
    
    modalContent.innerHTML = `
        <div class="success-icon">✅</div>
        <div class="success-title">Exam Submitted Successfully!</div>
        <div style="margin: 20px 0;">
            <div style="font-size: 24px; font-weight: bold; color: ${getScoreColor(percentage)};">
                ${percentage}% (${grade})
            </div>
            <div style="font-size: 16px; color: #666; margin-top: 10px;">
                ${correct} out of ${total} questions correct
            </div>
        </div>
        <div style="margin-top: 15px;">
            <button onclick="goToResults()" style="background: #28a745; color: white; padding: 10px 20px; 
                    border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">View Results</button>
            <button onclick="goToDashboard()" style="background: #6c757d; color: white; padding: 10px 20px; 
                    border: none; border-radius: 5px; cursor: pointer; margin-right: 10px;">Dashboard</button>
        </div>
    `;
    
    modal.style.display = 'block';
    
    if (percentage >= 80) {
        celebrateHighScore();
    }
}

function getGradeFromScore(percentage) {
    if (percentage >= 90) return 'A';
    if (percentage >= 80) return 'B';
    if (percentage >= 70) return 'C';
    if (percentage >= 60) return 'D';
    if (percentage >= 50) return 'E';
    return 'F';
}

function getScoreColor(percentage) {
    if (percentage >= 80) return '#28a745';
    if (percentage >= 70) return '#17a2b8';
    if (percentage >= 60) return '#ffc107';
    return '#dc3545';
}

function celebrateHighScore() {
    for (let i = 0; i < 20; i++) {
        setTimeout(() => {
            createConfetti();
        }, i * 100);
    }
}

function createConfetti() {
    let confetti = document.createElement('div');
    confetti.style.cssText = `
        position: fixed;
        top: -10px;
        left: ${Math.random() * 100}%;
        width: 10px;
        height: 10px;
        background: ${['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#feca57'][Math.floor(Math.random() * 5)]};
        animation: fall 3s linear forwards;
        z-index: 3000;
    `;
    
    document.body.appendChild(confetti);
    
    setTimeout(() => {
        confetti.remove();
    }, 3000);
}

function goToResults() {
    window.location.href = 'registered-exams.php?submitted=1';
}

function goToDashboard() {
    window.location.href = 'dashboard.php';
}

function setupEventListeners() {
    window.addEventListener('beforeunload', function(e) {
        if (!isSubmitted) {
            e.preventDefault();
            e.returnValue = 'Your exam is still in progress. Are you sure you want to leave?';
            return e.returnValue;
        }
    });
    
    window.addEventListener('popstate', function(e) {
        if (!isSubmitted) {
            history.pushState(null, null, location.href);
            alert('Navigation is disabled during the exam. Please use the submit button when finished.');
        }
    });
    
    history.pushState(null, null, location.href);
    
    document.addEventListener('visibilitychange', function() {
        if (document.hidden && !isSubmitted) {
            console.log('User switched tabs during exam');
        }
    });
}

function preventCheating() {
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
        return false;
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'F5' || (e.ctrlKey && e.key === 'r') || (e.ctrlKey && e.shiftKey && e.key === 'R')) {
            e.preventDefault();
            alert('Page refresh is disabled during the exam.');
            return false;
        }
        
        if ((e.ctrlKey && e.shiftKey && e.key === 'I') || 
            (e.ctrlKey && e.shiftKey && e.key === 'C') ||
            (e.ctrlKey && e.shiftKey && e.key === 'J') ||
            e.key === 'F12') {
            e.preventDefault();
            return false;
        }
        
        if (e.ctrlKey && e.key === 'a') {
            e.preventDefault();
            return false;
        }
        
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            return false;
        }
    });
    
    document.addEventListener('selectstart', function(e) {
        e.preventDefault();
        return false;
    });
    
    document.addEventListener('dragstart', function(e) {
        e.preventDefault();
        return false;
    });
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
        let result = eval(document.getElementById('calcDisplay').value.replace('×', '*'));
        document.getElementById('calcDisplay').value = result;
    } catch (error) {
        document.getElementById('calcDisplay').value = 'Error';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    let successModal = document.getElementById('successModal');
    let calculatorModal = document.getElementById('calculatorModal');
    
    if (event.target === successModal) {
        goToResults();
    }
    
    if (event.target === calculatorModal) {
        closeCalculator();
    }
}

// FIXED: Add form validation function
function validateForm() {
    if (isSubmitted) return false;
    return submitExam();
}

// Add CSS animations
let style = document.createElement('style');
style.textContent = `
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    @keyframes fall {
        to {
            transform: translateY(100vh) rotate(360deg);
        }
    }
`;
document.head.appendChild(style);

</script>
</body>
</html>
    