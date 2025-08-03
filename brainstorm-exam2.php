<?php

require_once 'config.php';
session_start();

// Security check - ensure user is logged in and exam is valid
if (!isset($_SESSION['user_id']) || !isset($_GET['exam_id'])) {
    header('Location: login.php');
    exit();
}

$exam_id = $_GET['exam_id'];
$user_id = $_SESSION['user_id'];

// Fetch exam details
$stmt = $pdo->prepare("SELECT be.*, bc.course_title FROM brainstorm_exams be 
                       JOIN brainstorm_courses bc ON be.course_id = bc.id 
                       WHERE be.id = ? AND be.user_id = ?");
$stmt->execute([$exam_id, $user_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    die("Invalid exam or access denied.");
}

// Check if exam has already been submitted
if ($exam['status'] == 'completed') {
    header('Location: registered-exams.php?msg=already_completed');
    exit();
}

// Fetch questions for this exam
$stmt = $pdo->prepare("SELECT * FROM brainstorm_questions WHERE course_id = ? ORDER BY id ASC");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    die("No questions found for this exam.");
}

// Fetch existing responses if any
$stmt = $pdo->prepare("SELECT question_id, user_answer FROM brainstorm_responses WHERE exam_id = ?");
$stmt->execute([$exam_id]);
$existing_responses = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $existing_responses[$row['question_id']] = $row['selected_option'];
}

// Calculate remaining time
$start_time = strtotime($exam['time_started']);
$duration = $exam['duration_minutes'] * 60; // Convert to seconds
$elapsed = time() - $start_time;
$remaining = max(0, $duration - $elapsed);

// If time is up, auto-submit
if ($remaining <= 0 && $exam['status'] != 'completed') {
    // Auto-submit logic here
    $stmt = $pdo->prepare("UPDATE brainstorm_exams SET status = 'completed', time_ended = NOW() WHERE id = ?");
    $stmt->execute([$exam_id]);
    header('Location: registered-exams.php?msg=time_up');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TestMaster - <?php echo htmlspecialchars($exam['course_name']); ?> Exam</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #004;
            color: #333;
            overflow-x: hidden;
        }

        /* Fixed Topbar */
        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .course-title {
            font-size: 20px;
            font-weight: 600;
            color: #004;
        }

        .timer {
            font-size: 24px;
            font-weight: bold;
            color: #d32f2f;
            background: #fff3e0;
            padding: 8px 16px;
            border-radius: 8px;
            border: 2px solid #ff9800;
        }

        .exit-btn {
            background: #f44336;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .exit-btn:hover {
            background: #d32f2f;
        }

        /* Main Container */
        .container {
            display: flex;
            margin-top: 70px;
            min-height: calc(100vh - 70px);
        }

        /* Left Sidebar */
        .sidebar {
            width: 25%;
            background: white;
            padding: 30px 20px;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 70px;
            height: calc(100vh - 70px);
            overflow-y: auto;
        }

        .sidebar h3 {
            color: #004;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .question-nav {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-bottom: 30px;
        }

        .question-btn {
            width: 40px;
            height: 40px;
            border: 2px solid #ddd;
            background: white;
            color: #666;
            border-radius: 50%;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .question-btn.current {
            background: #004;
            color: white;
            border-color: #004;
        }

        .question-btn.answered {
            background: #4caf50;
            color: white;
            border-color: #4caf50;
        }

        .question-btn.unanswered {
            background: #f44336;
            color: white;
            border-color: #f44336;
        }

        .progress-info {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .progress-info h4 {
            color: #004;
            margin-bottom: 10px;
        }

        .legend {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .legend-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
        }

        /* Main Content */
        .main-content {
            width: 75%;
            padding: 30px 40px;
            background: #f8f9fa;
        }

        .question-container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .question-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }

        .question-number {
            font-size: 18px;
            color: #004;
            font-weight: 600;
        }

        .question-text {
            font-size: 22px;
            line-height: 1.6;
            color: #333;
            margin-bottom: 30px;
        }

        .options-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .option {
            background: #f8f9fa;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .option:hover {
            background: #e3f2fd;
            border-color: #004;
        }

        .option.selected {
            background: #004;
            color: white;
            border-color: #004;
        }

        .option input[type="radio"] {
            display: none;
        }

        .option-label {
            font-weight: bold;
            font-size: 18px;
            min-width: 30px;
        }

        .option-text {
            font-size: 16px;
            line-height: 1.4;
        }

        /* Navigation Buttons */
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
        }

        .nav-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .prev-btn {
            background: transparent;
            color: #004;
            border: 2px solid #004;
        }

        .prev-btn:hover:not(:disabled) {
            background: #004;
            color: white;
        }

        .prev-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .next-btn, .submit-btn {
            background: #004;
            color: white;
        }

        .next-btn:hover, .submit-btn:hover {
            background: #002;
            transform: translateY(-2px);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 12px;
            width: 500px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .modal h2 {
            color: #004;
            margin-bottom: 20px;
        }

        .modal-btn {
            background: #004;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px;
        }

        .modal-btn.cancel {
            background: #666;
        }

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .sidebar {
                width: 30%;
            }
            .main-content {
                width: 70%;
                padding: 20px;
            }
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            .sidebar, .main-content {
                width: 100%;
            }
            .sidebar {
                position: static;
                height: auto;
            }
            .question-nav {
                grid-template-columns: repeat(10, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Fixed Topbar -->
    <div class="topbar">
        <div class="course-title"><?php echo htmlspecialchars($exam['course_name']); ?> - Exam</div>
        <div class="timer" id="timer"><?php echo sprintf('%02d:%02d', floor($remaining/60), $remaining%60); ?></div>
        <button class="exit-btn" onclick="exitExam()">Exit Exam</button>
    </div>

    <div class="container">
        <!-- Left Sidebar -->
        <div class="sidebar">
            <h3>Questions</h3>
            <div class="question-nav" id="questionNav">
                <?php for($i = 1; $i <= count($questions); $i++): ?>
                    <button class="question-btn <?php echo $i == 1 ? 'current' : ''; ?>" 
                            onclick="goToQuestion(<?php echo $i; ?>)" 
                            id="navBtn<?php echo $i; ?>">
                        <?php echo $i; ?>
                    </button>
                <?php endfor; ?>
            </div>

            <div class="progress-info">
                <h4>Progress</h4>
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #004;"></div>
                        <span>Current</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #4caf50;"></div>
                        <span>Answered</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot" style="background: #f44336;"></div>
                        <span>Unanswered</span>
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <strong>Answered: <span id="answeredCount">0</span> / <?php echo count($questions); ?></strong>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <form id="examForm" method="POST" action="submit-exam.php">
                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                
                <?php foreach($questions as $index => $question): ?>
                <div class="question-container" id="question<?php echo $index + 1; ?>" 
                     style="<?php echo $index == 0 ? '' : 'display: none;'; ?>">
                    
                    <div class="question-header">
                        <div class="question-number">Question <?php echo $index + 1; ?> of <?php echo count($questions); ?></div>
                    </div>

                    <div class="question-text">
                        <?php echo htmlspecialchars($question['question_text']); ?>
                    </div>

                    <div class="options-container">
                        <?php 
                        $options = ['A' => $question['option1'], 'B' => $question['option2'], 
                                   'C' => $question['option3'], 'D' => $question['option4']];
                        foreach($options as $letter => $option_text): 
                        ?>
                        <div class="option" onclick="selectOption(<?php echo $index + 1; ?>, '<?php echo $letter; ?>')">
                            <input type="radio" name="question_<?php echo $question['id']; ?>" 
                                   value="<?php echo $letter; ?>" id="q<?php echo $index + 1; ?>_<?php echo $letter; ?>"
                                   <?php echo (isset($existing_responses[$question['id']]) && $existing_responses[$question['id']] == $letter) ? 'checked' : ''; ?>>
                            <div class="option-label"><?php echo $letter; ?>.</div>
                            <div class="option-text"><?php echo htmlspecialchars($option_text); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="nav-buttons">
                        <button type="button" class="nav-btn prev-btn" onclick="previousQuestion()" 
                                id="prevBtn" <?php echo $index == 0 ? 'disabled' : ''; ?>>
                            ← Previous
                        </button>
                        
                        <?php if($index < count($questions) - 1): ?>
                            <button type="button" class="nav-btn next-btn" onclick="nextQuestion()">
                                Next →
                            </button>
                        <?php else: ?>
                            <button type="button" class="nav-btn submit-btn" onclick="showSubmitModal()">
                                Submit Exam
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </form>
        </div>
    </div>

    <!-- Modals -->
    <div id="submitModal" class="modal">
        <div class="modal-content">
            <h2>Submit Exam?</h2>
            <p>Are you sure you want to submit your exam? You cannot change your answers after submission.</p>
            <button class="modal-btn" onclick="submitExam()">Yes, Submit</button>
            <button class="modal-btn cancel" onclick="closeModal('submitModal')">Cancel</button>
        </div>
    </div>

    <div id="timeUpModal" class="modal">
        <div class="modal-content">
            <h2>Time's Up!</h2>
            <p>Your exam time has expired. Submitting your answers...</p>
        </div>
    </div>

    <div id="exitModal" class="modal">
        <div class="modal-content">
            <h2>Exit Exam?</h2>
            <p>Are you sure you want to exit? Your progress will be saved but the timer will continue.</p>
            <button class="modal-btn" onclick="window.location.href='registered-exams.php'">Yes, Exit</button>
            <button class="modal-btn cancel" onclick="closeModal('exitModal')">Cancel</button>
        </div>
    </div>

    <script>
        let currentQuestion = 1;
        const totalQuestions = <?php echo count($questions); ?>;
        let timeRemaining = <?php echo $remaining; ?>;
        let answers = {};
        
        // Initialize existing answers
        <?php foreach($existing_responses as $q_id => $response): ?>
            answers[<?php echo $q_id; ?>] = '<?php echo $response; ?>';
        <?php endforeach; ?>

        // Timer functionality
        function updateTimer() {
            if (timeRemaining <= 0) {
                document.getElementById('timeUpModal').style.display = 'block';
                setTimeout(() => {
                    document.getElementById('examForm').action = 'submit-exam.php?auto_submit=1';
                    document.getElementById('examForm').submit();
                }, 2000);
                return;
            }

            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            document.getElementById('timer').textContent = 
                String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            
            // Change color when time is running low
            if (timeRemaining <= 300) { // 5 minutes
                document.getElementById('timer').style.color = '#d32f2f';
                document.getElementById('timer').style.animation = 'blink 1s infinite';
            }
            
            timeRemaining--;
        }

        // Start timer
        setInterval(updateTimer, 1000);

        // Navigation functions
        function goToQuestion(questionNum) {
            if (questionNum < 1 || questionNum > totalQuestions) return;
            
            // Hide current question
            document.getElementById('question' + currentQuestion).style.display = 'none';
            document.getElementById('navBtn' + currentQuestion).classList.remove('current');
            
            // Show new question
            currentQuestion = questionNum;
            document.getElementById('question' + currentQuestion).style.display = 'block';
            document.getElementById('navBtn' + currentQuestion).classList.add('current');
            
            // Update navigation buttons
            document.getElementById('prevBtn').disabled = (currentQuestion === 1);
            
            updateProgress();
        }

        function nextQuestion() {
            if (currentQuestion < totalQuestions) {
                goToQuestion(currentQuestion + 1);
            }
        }

        function previousQuestion() {
            if (currentQuestion > 1) {
                goToQuestion(currentQuestion - 1);
            }
        }

        // Option selection
        function selectOption(questionNum, option) {
            const questionId = document.querySelector(`#question${questionNum} input[name*="question_"]`).name.split('_')[1];
            
            // Remove previous selection styling
            document.querySelectorAll(`#question${questionNum} .option`).forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Add selection styling
            event.currentTarget.classList.add('selected');
            
            // Update radio button
            document.getElementById(`q${questionNum}_${option}`).checked = true;
            
            // Store answer
            answers[questionId] = option;
            
            updateProgress();
        }

        // Progress tracking
        function updateProgress() {
            let answeredCount = Object.keys(answers).length;
            document.getElementById('answeredCount').textContent = answeredCount;
            
            // Update navigation buttons styling
            for (let i = 1; i <= totalQuestions; i++) {
                const btn = document.getElementById('navBtn' + i);
                const questionId = document.querySelector(`#question${i} input[name*="question_"]`).name.split('_')[1];
                
                btn.classList.remove('answered', 'unanswered');
                if (i !== currentQuestion) {
                    if (answers[questionId]) {
                        btn.classList.add('answered');
                    } else {
                        btn.classList.add('unanswered');
                    }
                }
            }
        }

        // Modal functions
        function showSubmitModal() {
            document.getElementById('submitModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function exitExam() {
            document.getElementById('exitModal').style.display = 'block';
        }

        function submitExam() {
            document.getElementById('examForm').submit();
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            updateProgress();
            
            // Mark existing answers as selected
            <?php foreach($existing_responses as $q_id => $response): ?>
                const questionNum = Array.from(document.querySelectorAll('input[name="question_<?php echo $q_id; ?>"]')).findIndex(el => el.value === '<?php echo $response; ?>') + 1;
                if (questionNum > 0) {
                    document.querySelector(`input[name="question_<?php echo $q_id; ?>"][value="<?php echo $response; ?>"]`).closest('.option').classList.add('selected');
                }
            <?php endforeach; ?>
        });

        // Prevent back button issues
        window.history.pushState(null, null, window.location.href);
        window.onpopstate = function() {
            window.history.pushState(null, null, window.location.href);
        };

        // Auto-save functionality (optional)
        setInterval(function() {
            // Could implement auto-save to database here
        }, 30000); // Every 30 seconds

        // Add CSS animation for timer blink
        const style = document.createElement('style');
        style.textContent = `
            @keyframes blink {
                0%, 50% { opacity: 1; }
                51%, 100% { opacity: 0.3; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>