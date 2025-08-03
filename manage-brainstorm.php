<?php

require_once 'config.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role'])) {
    header('Location: admin-login.php');
    exit();
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_POST) {
    if (isset($_POST['add_question'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO brainstorm_questions (course_id, question_text, option1, option2, option3, option4, correct_option, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $_POST['course_id'],
                $_POST['question_text'],
                $_POST['option1'],
                $_POST['option2'],
                $_POST['option3'],
                $_POST['option4'],
                $_POST['correct_option']
            ]);
            $message = "Brainstorm question added successfully!";
            $messageType = "success";
        } catch(PDOException $e) {
            $message = "Error adding question: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    if (isset($_POST['delete_question'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM brainstorm_questions WHERE id = ?");
            $stmt->execute([$_POST['question_id']]);
            $message = "Question deleted successfully!";
            $messageType = "success";
        } catch(PDOException $e) {
            $message = "Error deleting question: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    if (isset($_POST['update_question'])) {
        try {
            $stmt = $pdo->prepare("UPDATE brainstorm_questions SET course_id = ?, question_text = ?, option1 = ?, option2 = ?, option3 = ?, option4 = ?, correct_option = ? WHERE id = ?");
            $stmt->execute([
                $_POST['course_id'],
                $_POST['question_text'],
                $_POST['option1'],
                $_POST['option2'],
                $_POST['option3'],
                $_POST['option4'],
                $_POST['correct_option'],
                $_POST['question_id']
            ]);
            $message = "Question updated successfully!";
            $messageType = "success";
        } catch(PDOException $e) {
            $message = "Error updating question: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    if (isset($_POST['add_course'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO brainstorm_courses (course_title, course_code, exam_date, exam_time, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['course_title'],
                $_POST['course_code'],
                $_POST['exam_date'] ?: null,
                $_POST['exam_time'] ?: null,
                $_POST['description']
            ]);
            $message = "Brainstorm course added successfully!";
            $messageType = "success";
        } catch(PDOException $e) {
            $message = "Error adding course: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get brainstorm courses for dropdown
$courses_stmt = $pdo->query("SELECT * FROM brainstorm_courses ORDER BY course_title");
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get brainstorm questions with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$course_filter = isset($_GET['course_filter']) ? $_GET['course_filter'] : '';

$where_clause = "WHERE 1=1";
$params = [];

if ($search) {
    $where_clause .= " AND bq.question_text LIKE ?";
    $params[] = "%$search%";
}

if ($course_filter) {
    $where_clause .= " AND bq.course_id = ?";
    $params[] = $course_filter;
}

// Get total count for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM brainstorm_questions bq $where_clause");
$count_stmt->execute($params);
$total_questions = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_questions / $limit);

// Get questions
$questions_stmt = $pdo->prepare("
    SELECT bq.*, bc.course_title 
    FROM brainstorm_questions bq 
    JOIN brainstorm_courses bc ON bq.course_id = bc.id 
    $where_clause 
    ORDER BY bq.created_at DESC 
    LIMIT $limit OFFSET $offset
");
$questions_stmt->execute($params);
$questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get brainstorm statistics
$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) as total_questions FROM brainstorm_questions");
$stats['total_questions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_questions'];

$stmt = $pdo->query("SELECT COUNT(*) as total_courses FROM brainstorm_courses");
$stats['total_courses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_courses'];

$stmt = $pdo->query("SELECT COUNT(*) as total_exams FROM brainstorm_exams");
$stats['total_exams'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_exams'];

$stmt = $pdo->query("SELECT COUNT(*) as active_sessions FROM brainstorm_exams WHERE status = 'active'");
$stats['active_sessions'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_sessions'];

$stmt = $pdo->query("SELECT AVG(score) as avg_score FROM brainstorm_scores WHERE score IS NOT NULL");
$avg_score = $stmt->fetch(PDO::FETCH_ASSOC)['avg_score'];
$stats['avg_score'] = $avg_score ? round($avg_score, 1) : 0;

// Get registrations count
$stmt = $pdo->query("SELECT COUNT(*) as total_registrations FROM brainstorm_registrations");
$stats['total_registrations'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_registrations'];

// Add this to your existing form submissions section (around line 25)

if (isset($_POST['grant_access'])) {
    try {
        $stmt = $pdo->prepare("UPDATE brainstorm_registrations SET access_granted = 1 WHERE id = ?");
        $stmt->execute([$_POST['registration_id']]);
        $message = "Access granted successfully!";
        $messageType = "success";
    } catch(PDOException $e) {
        $message = "Error granting access: " . $e->getMessage();
        $messageType = "error";
    }
}

if (isset($_POST['revoke_access'])) {
    try {
        $stmt = $pdo->prepare("UPDATE brainstorm_registrations SET access_granted = 0 WHERE id = ?");
        $stmt->execute([$_POST['registration_id']]);
        $message = "Access revoked successfully!";
        $messageType = "success";
    } catch(PDOException $e) {
        $message = "Error revoking access: " . $e->getMessage();
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TestMaster - Manage Brainstorm</title>
    <link rel="stylesheet" href="manage-brainstorm.css">
    <style>
        
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🧠 Manage Brainstorm</h1>
            <a href="admin-dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_questions']); ?></div>
                <div class="stat-label">Total Questions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_courses']); ?></div>
                <div class="stat-label">Brainstorm Courses</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_exams']); ?></div>
                <div class="stat-label">Exams Taken</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_sessions']; ?></div>
                <div class="stat-label">Active Sessions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['avg_score']; ?>%</div>
                <div class="stat-label">Average Score</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_registrations']); ?></div>
                <div class="stat-label">Registrations</div>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Main Section with Tabs -->
        <div class="section">
            <div class="tabs">
                <button class="tab active" onclick="showTab('questions')">📝 Questions</button>
                <button class="tab" onclick="showTab('courses')">📚 Courses</button>
                <button class="tab" onclick="showTab('registrations')">👥 Registrations</button>
                <button class="tab" onclick="showTab('results')">📊 Results</button>
            </div>

            <!-- Questions Tab -->
            <div id="questions" class="tab-content active">
                <h2>Add New Brainstorm Question</h2>
                <form method="POST">
                    <div class="form-group">
                        <label for="course_id">Brainstorm Course</label>
                        <select name="course_id" id="course_id" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo htmlspecialchars($course['course_title']); ?>
                                    <?php if ($course['course_code']): ?>
                                        (<?php echo htmlspecialchars($course['course_code']); ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="question_text">Question Text</label>
                        <textarea name="question_text" id="question_text" required placeholder="Enter the brainstorm question..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="option1">Option 1</label>
                            <input type="text" name="option1" id="option1" required>
                        </div>
                        <div class="form-group">
                            <label for="option2">Option 2</label>
                            <input type="text" name="option2" id="option2" required>
                        </div>
                        <div class="form-group">
                            <label for="option3">Option 3</label>
                            <input type="text" name="option3" id="option3" required>
                        </div>
                        <div class="form-group">
                            <label for="option4">Option 4</label>
                            <input type="text" name="option4" id="option4" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="correct_option">Correct Answer</label>
                        <select name="correct_option" id="correct_option" required>
                            <option value="">Select correct option</option>
                            <option value="1">Option 1</option>
                            <option value="2">Option 2</option>
                            <option value="3">Option 3</option>
                            <option value="4">Option 4</option>
                        </select>
                    </div>

                    <button type="submit" name="add_question" class="btn btn-primary">Add Question</button>
                </form>

                <hr style="margin: 30px 0;">

                <h2>Manage Questions (<?php echo number_format($total_questions); ?>)</h2>
                
                <!-- Filters -->
                <div class="filters">
                    <input type="text" id="search" placeholder="Search questions..." value="<?php echo htmlspecialchars($search); ?>">
                    <select id="course_filter">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo ($course_filter == $course['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['course_title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button onclick="applyFilters()" class="btn btn-primary">Filter</button>
                </div>

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Course</th>
                                <th>Question</th>
                                <th>Options</th>
                                <th>Correct</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($questions)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px; color: #666;">
                                        No brainstorm questions found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($questions as $question): ?>
                                    <tr>
                                        <td><?php echo $question['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($question['course_title']); ?></strong>
                                        </td>
                                        <td>
                                            <div class="question-text" title="<?php echo htmlspecialchars($question['question_text']); ?>">
                                                <?php echo htmlspecialchars($question['question_text']); ?>
                                            </div>
                                        </td>
                                        <td class="options">
                                            1: <?php echo htmlspecialchars(substr($question['option1'], 0, 20)); ?>...<br>
                                            2: <?php echo htmlspecialchars(substr($question['option2'], 0, 20)); ?>...<br>
                                            3: <?php echo htmlspecialchars(substr($question['option3'], 0, 20)); ?>...<br>
                                            4: <?php echo htmlspecialchars(substr($question['option4'], 0, 20)); ?>...
                                        </td>
                                        <td class="correct-answer">Option <?php echo $question['correct_option']; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($question['created_at'])); ?></td>
                                        <td>
                                            <button onclick="editQuestion(<?php echo htmlspecialchars(json_encode($question)); ?>)" class="btn btn-edit">Edit</button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this question?')">
                                                <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                                <button type="submit" name="delete_question" class="btn btn-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&course_filter=<?php echo $course_filter; ?>">← Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&course_filter=<?php echo $course_filter; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&course_filter=<?php echo $course_filter; ?>">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Courses Tab -->
            <div id="courses" class="tab-content">
                <h2>Add New Brainstorm Course</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="course_title">Course Title</label>
                            <input type="text" name="course_title" id="course_title" required>
                        </div>
                        <div class="form-group">
                            <label for="course_code">Course Code</label>
                            <input type="text" name="course_code" id="course_code">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="exam_date">Exam Date (Optional)</label>
                            <input type="date" name="exam_date" id="exam_date">
                        </div>
                        <div class="form-group">
                            <label for="exam_time">Exam Time (Optional)</label>
                            <input type="time" name="exam_time" id="exam_time">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea name="description" id="description" placeholder="Course description or notes..."></textarea>
                    </div>

                    <button type="submit" name="add_course" class="btn btn-primary">Add Course</button>
                </form>

                <hr style="margin: 30px 0;">

                <h2>Existing Brainstorm Courses</h2>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Course Title</th>
                                <th>Course Code</th>
                                <th>Exam Date</th>
                                <th>Exam Time</th>
                                <th>Questions</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <?php
                                $q_count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM brainstorm_questions WHERE course_id = ?");
                                $q_count_stmt->execute([$course['id']]);
                                $question_count = $q_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                ?>
                                <tr>
                                    <td><?php echo $course['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($course['course_title']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($course['course_code'] ?: 'N/A'); ?></td>
                                    <td><?php echo $course['exam_date'] ? date('M j, Y', strtotime($course['exam_date'])) : 'N/A'; ?></td>
                                    <td><?php echo $course['exam_time'] ? date('g:i A', strtotime($course['exam_time'])) : 'N/A'; ?></td>
                                    <td><span class="correct-answer"><?php echo $question_count; ?> questions</span></td>
                                    <td>
                                        <button class="btn btn-edit">Edit</button>
                                        <button class="btn btn-danger">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Registrations Tab -->
            <!-- Updated Registrations Tab with working buttons -->
<div id="registrations" class="tab-content">
    <h2>Brainstorm Registrations</h2>
    <?php
    $reg_stmt = $pdo->query("
        SELECT br.*, u.name, u.email, bc.course_title 
        FROM brainstorm_registrations br 
        JOIN users u ON br.user_id = u.id 
        JOIN brainstorm_courses bc ON br.course_id = bc.id 
        ORDER BY br.time_created DESC
    ");
    $registrations = $reg_stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Student</th>
                    <th>Email</th>
                    <th>Course</th>
                    <th>Access</th>
                    <th>Attempts</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($registrations)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px; color: #666;">
                            No registrations found
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($registrations as $reg): ?>
                        <tr>
                            <td><?php echo $reg['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($reg['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($reg['email']); ?></td>
                            <td><?php echo htmlspecialchars($reg['course_title']); ?></td>
                            <td>
                                <?php if ($reg['access_granted']): ?>
                                    <span style="color: #0d0; font-weight: 600;">✓ Granted</span>
                                <?php else: ?>
                                    <span style="color: #dc3545; font-weight: 600;">✗ Denied</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="correct-answer"><?php echo $reg['attempts_used']; ?></span> / <?php echo $reg['max_attempts']; ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($reg['time_created'])); ?></td>
                            <td>
                                <?php if (!$reg['access_granted']): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Grant access to this student?')">
                                        <input type="hidden" name="registration_id" value="<?php echo $reg['id']; ?>">
                                        <button type="submit" name="grant_access" class="btn btn-edit">Grant Access</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Revoke access for this student?')">
                                        <input type="hidden" name="registration_id" value="<?php echo $reg['id']; ?>">
                                        <button type="submit" name="revoke_access" class="btn btn-danger">Revoke</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

            <!-- Results Tab -->
            <div id="results" class="tab-content">
                <h2>Brainstorm Results & Analytics</h2>
                <?php
                $results_stmt = $pdo->query("
                    SELECT bs.*, u.name, bc.course_title 
                    FROM brainstorm_scores bs 
                    JOIN users u ON bs.user_id = u.id 
                    JOIN brainstorm_courses bc ON bs.course_id = bc.id 
                    ORDER BY bs.score DESC 
                    LIMIT 50
                ");
                $results = $results_stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get course performance
                $course_performance = $pdo->query("
                    SELECT bc.course_title, 
                           COUNT(*) as total_attempts,
                           AVG(bs.score) as avg_score,
                           MAX(bs.score) as highest_score,
                           MIN(bs.score) as lowest_score
                    FROM brainstorm_scores bs 
                    JOIN brainstorm_courses bc ON bs.course_id = bc.id 
                    WHERE bs.score IS NOT NULL
                    GROUP BY bc.id, bc.course_title
                    ORDER BY avg_score DESC
                ")->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                    <div>
                        <h3 style="margin-bottom: 15px; color: #333;">Course Performance</h3>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Attempts</th>
                                        <th>Avg Score</th>
                                        <th>Highest</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($course_performance as $perf): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($perf['course_title']); ?></td>
                                            <td><?php echo $perf['total_attempts']; ?></td>
                                            <td class="correct-answer"><?php echo round($perf['avg_score'], 1); ?>%</td>
                                            <td><?php echo round($perf['highest_score'], 1); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div>
                        <h3 style="margin-bottom: 15px; color: #333;">Recent High Scores</h3>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Course</th>
                                        <th>Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($results, 0, 10) as $result): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($result['name']); ?></td>
                                            <td><?php echo htmlspecialchars($result['course_title']); ?></td>
                                            <td class="correct-answer"><?php echo round($result['score'], 1); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <h3 style="margin-bottom: 15px; color: #333;">All Results</h3>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Score</th>
                                <th>Exam ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($results)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 30px; color: #666;">
                                        No results found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($results as $result): ?>
                                    <tr>
                                        <td><?php echo $result['id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($result['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($result['course_title']); ?></td>
                                        <td class="correct-answer"><?php echo round($result['score']/100*20, 1); ?>/20</td>
                                        <td><?php echo $result['exam_id']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Question Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Edit Brainstorm Question</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="question_id" id="edit_question_id">
                
                <div class="form-group">
                    <label for="edit_course_id">Course</label>
                    <select name="course_id" id="edit_course_id" required>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['course_title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_question_text">Question Text</label>
                    <textarea name="question_text" id="edit_question_text" required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_option1">Option 1</label>
                        <input type="text" name="option1" id="edit_option1" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_option2">Option 2</label>
                        <input type="text" name="option2" id="edit_option2" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_option3">Option 3</label>
                        <input type="text" name="option3" id="edit_option3" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_option4">Option 4</label>
                        <input type="text" name="option4" id="edit_option4" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit_correct_option">Correct Answer</label>
                    <select name="correct_option" id="edit_correct_option" required>
                        <option value="1">Option 1</option>
                        <option value="2">Option 2</option>
                        <option value="3">Option 3</option>
                        <option value="4">Option 4</option>
                    </select>
                </div>

                <button type="submit" name="update_question" class="btn btn-primary">Update Question</button>
            </form>
        </div>
    </div>

    <script src="manage-brainstorm.js"></script>
</body>
</html>