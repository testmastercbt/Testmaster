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
            $stmt = $pdo->prepare("INSERT INTO questions (course_id, question_id, option1, option2, option3, option4, option5, correct_option, difficulty, question_image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $_POST['course_id'],
                $_POST['question_text'],
                $_POST['option1'],
                $_POST['option2'],
                $_POST['option3'],
                $_POST['option4'],
                $_POST['option5'] ?: null,
                $_POST['correct_option'],
                $_POST['difficulty'],
                $_POST['question_image'] ?: null
            ]);
            $message = "Question added successfully!";
            $messageType = "success";
        } catch(PDOException $e) {
            $message = "Error adding question: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    if (isset($_POST['update_question'])) {
        try {
            $stmt = $pdo->prepare("UPDATE questions SET course_id = ?, question_id = ?, option1 = ?, option2 = ?, option3 = ?, option4 = ?, option5 = ?, correct_option = ?, difficulty = ?, question_image = ? WHERE id = ?");
            $stmt->execute([
                $_POST['course_id'],
                $_POST['question_text'],
                $_POST['option1'],
                $_POST['option2'],
                $_POST['option3'],
                $_POST['option4'],
                $_POST['option5'] ?: null,
                $_POST['correct_option'],
                $_POST['difficulty'],
                $_POST['question_image'] ?: null,
                $_POST['question_id']
            ]);
            $message = "Question updated successfully!";
            $messageType = "success";
        } catch(PDOException $e) {
            $message = "Error updating question: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    if (isset($_POST['delete_question'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
            $stmt->execute([$_POST['question_id']]);
            $message = "Question deleted successfully!";
            $messageType = "success";
        } catch(PDOException $e) {
            $message = "Error deleting question: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    if (isset($_POST['bulk_delete'])) {
        try {
            if (!empty($_POST['selected_questions'])) {
                $placeholders = str_repeat('?,', count($_POST['selected_questions']) - 1) . '?';
                $stmt = $pdo->prepare("DELETE FROM questions WHERE id IN ($placeholders)");
                $stmt->execute($_POST['selected_questions']);
                $count = count($_POST['selected_questions']);
                $message = "$count questions deleted successfully!";
                $messageType = "success";
            } else {
                $message = "No questions selected for deletion!";
                $messageType = "error";
            }
        } catch(PDOException $e) {
            $message = "Error deleting questions: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get courses for dropdown
$courses_stmt = $pdo->query("SELECT * FROM courses ORDER BY title");
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get questions with pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$course_filter = isset($_GET['course_filter']) ? $_GET['course_filter'] : '';
$difficulty_filter = isset($_GET['difficulty_filter']) ? $_GET['difficulty_filter'] : '';

$where_clause = "WHERE 1=1";
$params = [];

if ($search) {
    $where_clause .= " AND q.question_id LIKE ?";
    $params[] = "%$search%";
}

if ($course_filter) {
    $where_clause .= " AND q.course_id = ?";
    $params[] = $course_filter;
}

if ($difficulty_filter) {
    $where_clause .= " AND q.difficulty = ?";
    $params[] = $difficulty_filter;
}

// Get total count for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM questions q $where_clause");
$count_stmt->execute($params);
$total_questions = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_questions / $limit);

// Get questions
$questions_stmt = $pdo->prepare("
    SELECT q.*, c.title as course_title 
    FROM questions q 
    LEFT JOIN courses c ON q.course_id = c.id 
    $where_clause 
    ORDER BY q.created_at DESC 
    LIMIT $limit OFFSET $offset
");
$questions_stmt->execute($params);
$questions = $questions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) as total_questions FROM questions");
$stats['total_questions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_questions'];

$stmt = $pdo->query("SELECT COUNT(*) as questions_today FROM questions WHERE DATE(created_at) = CURDATE()");
$stats['questions_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['questions_today'];

$stmt = $pdo->query("SELECT COUNT(*) as with_images FROM questions WHERE question_image IS NOT NULL");
$stats['with_images'] = $stmt->fetch(PDO::FETCH_ASSOC)['with_images'];

// Difficulty distribution
$diff_stmt = $pdo->query("SELECT difficulty, COUNT(*) as count FROM questions WHERE difficulty IS NOT NULL GROUP BY difficulty");
$difficulty_stats = $diff_stmt->fetchAll(PDO::FETCH_ASSOC);

// Course distribution  
$course_stmt = $pdo->query("SELECT c.title, COUNT(q.id) as count FROM courses c LEFT JOIN questions q ON c.id = q.course_id GROUP BY c.id ORDER BY count DESC LIMIT 5");
$course_stats = $course_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TestMaster - Manage Questions</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #0d0;
            font-size: 2.2em;
            font-weight: 700;
        }

        .back-btn {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .back-btn:hover {
            background: #5a6268;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-number {
            font-size: 2em;
            font-weight: 700;
            color: #0d0;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
        }

        .section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .section h2 {
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid #0d0;
        }

        .tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
        }

        .tab {
            padding: 15px 25px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: #0d0;
            border-bottom-color: #0d0;
        }

        .tab:hover {
            color: #0d0;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0d0;
        }

        .form-group textarea {
            resize: vertical;
            height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-primary {
            background: #0d0;
            color: white;
        }

        .btn-primary:hover {
            background: #0a0;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
            font-size: 12px;
            padding: 6px 12px;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-edit {
            background: #007bff;
            color: white;
            font-size: 12px;
            padding: 6px 12px;
            margin-right: 5px;
        }

        .btn-edit:hover {
            background: #0056b3;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
            font-size: 12px;
            padding: 8px 16px;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .filters {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto auto;
            gap: 15px;
            margin-bottom: 20px;
            align-items: end;
        }

        .filters input,
        .filters select {
            padding: 8px 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
        }

        .question-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .question-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
        }

        .question-card:hover {
            border-color: #0d0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .question-card.selected {
            border-color: #0d0;
            background: #f0fff0;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .question-id {
            font-size: 12px;
            color: #666;
            font-weight: 600;
        }

        .difficulty-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .difficulty-easy {
            background: #d4edda;
            color: #155724;
        }

        .difficulty-medium {
            background: #fff3cd;
            color: #856404;
        }

        .difficulty-hard {
            background: #f8d7da;
            color: #721c24;
        }

        .question-text {
            font-weight: 600;
            margin-bottom: 15px;
            line-height: 1.4;
            color: #333;
        }

        .options-list {
            margin-bottom: 15px;
        }

        .option {
            padding: 8px 12px;
            margin: 5px 0;
            border-radius: 6px;
            font-size: 13px;
            background: white;
            border: 1px solid #dee2e6;
        }

        .option.correct {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
            font-weight: 600;
        }

        .question-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #666;
            margin-bottom: 15px;
        }

        .question-actions {
            display: flex;
            gap: 5px;
        }

        .select-checkbox {
            position: absolute;
            top: 10px;
            left: 10px;
        }

        .bulk-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }

        .bulk-actions button {
            display: none;
        }

        .bulk-actions.show button {
            display: inline-block;
        }

        .question-image {
            max-width: 100%;
            height: auto;
            border-radius: 6px;
            margin-bottom: 10px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }

        .pagination .current {
            background: #0d0;
            color: white;
            border-color: #0d0;
        }

        .pagination a:hover {
            background: #f8f9fa;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 2% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #000;
        }

                .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #0d0;
        }

        .form-group textarea {
            resize: vertical;
            height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-primary {
            background: #0d0;
            color: white;
        }

        .btn-primary:hover {
            background: #0a0;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .chart-title {
            font-size: 1.2em;
            font-weight: 600;
            margin-bottom: 15px;
            color: #333;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: #666;
        }

        .stat-value {
            font-weight: 600;
            color: #0d0;
        }

        .progress-bar {
            background: #f0f0f0;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0d0, #0a0);
            transition: width 0.3s ease;
        }

        .image-preview {
            max-width: 200px;
            max-height: 150px;
            margin-top: 10px;
            border-radius: 6px;
            border: 2px solid #ddd;
        }

        .form-help {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .quick-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .recent-activity {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 12px;
        }

        .activity-success {
            background: #d4edda;
            color: #155724;
        }

        .activity-info {
            flex: 1;
            font-size: 14px;
        }

        .activity-time {
            font-size: 12px;
            color: #666;
        }


        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .filters {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .question-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-wrap: wrap;
            }

            .bulk-actions {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>❓ Manage Questions</h1>
            <a href="admin-dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_questions']); ?></div>
                <div class="stat-label">Total Questions</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['questions_today']; ?></div>
                <div class="stat-label">Added Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['with_images']; ?></div>
                <div class="stat-label">With Images</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($courses); ?></div>
                <div class="stat-label">Courses</div>
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
                <button class="tab active" onclick="showTab('questions')">📝 All Questions</button>
                <button class="tab" onclick="showTab('add-question')">➕ Add Question</button>
                <button class="tab" onclick="showTab('analytics')">📊 Analytics</button>
            </div>

            <!-- Questions List Tab -->
            <div id="questions" class="tab-content active">
                <h2>Question Bank (<?php echo number_format($total_questions); ?> questions)</h2>
                
                <!-- Filters -->
                <div class="filters">
                    <input type="text" id="search" placeholder="Search questions..." value="<?php echo htmlspecialchars($search); ?>">
                    <select id="course_filter">
                        <option value="">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" <?php echo ($course_filter == $course['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="difficulty_filter">
                        <option value="">All Difficulties</option>
                        <option value="easy" <?php echo ($difficulty_filter == 'easy') ? 'selected' : ''; ?>>Easy</option>
                        <option value="medium" <?php echo ($difficulty_filter == 'medium') ? 'selected' : ''; ?>>Medium</option>
                        <option value="hard" <?php echo ($difficulty_filter == 'hard') ? 'selected' : ''; ?>>Hard</option>
                        <option value="green" <?php echo ($difficulty_filter == 'hard') ? 'selected' : ''; ?>>Green Tick</option>
                    </select>
                    <button onclick="applyFilters()" class="btn btn-primary">Filter</button>
                    <button onclick="toggleSelectAll()" class="btn btn-warning" id="selectAllBtn">Select All</button>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <span id="selectedCount">0 questions selected</span>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete selected questions?')">
                        <input type="hidden" name="selected_questions" id="selectedQuestions">
                        <button type="submit" name="bulk_delete" class="btn btn-danger">Delete Selected</button>
                    </form>
                </div>

                <!-- Questions Grid -->
                <div class="question-grid">
                    <?php if (empty($questions)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 50px; color: #666;">
                            <h3>No questions found</h3>
                            <p>Try adjusting your filters or add some questions.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($questions as $question): ?>
                            <div class="question-card" data-question-id="<?php echo $question['id']; ?>">
                                <input type="checkbox" class="select-checkbox" onchange="updateSelection()">
                                
                                <div class="question-header">
                                    <span class="question-id">ID: <?php echo $question['id']; ?></span>
                                    <?php if ($question['difficulty']): ?>
                                        <span class="difficulty-badge difficulty-<?php echo $question['difficulty']; ?>">
                                            <?php echo strtoupper($question['difficulty']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($question['question_image']): ?>
                                    <img src="<?php echo htmlspecialchars($question['question_image']); ?>" alt="Question Image" class="question-image">
                                <?php endif; ?>

                                <div class="question-text">
                                    <?php echo htmlspecialchars(substr($question['question_id'], 0, 200)); ?>
                                    <?php echo strlen($question['question_id']) > 200 ? '...' : ''; ?>
                                </div>

                                <div class="options-list">
                                    <div class="option <?php echo ($question['correct_option'] == '1') ? 'correct' : ''; ?>">
                                        A) <?php echo htmlspecialchars(substr($question['option1'], 0, 50)); ?>
                                        <?php echo strlen($question['option1']) > 50 ? '...' : ''; ?>
                                    </div>
                                    <div class="option <?php echo ($question['correct_option'] == '2') ? 'correct' : ''; ?>">
                                        B) <?php echo htmlspecialchars(substr($question['option2'], 0, 50)); ?>
                                        <?php echo strlen($question['option2']) > 50 ? '...' : ''; ?>
                                    </div>
                                    <div class="option <?php echo ($question['correct_option'] == '3') ? 'correct' : ''; ?>">
                                        C) <?php echo htmlspecialchars(substr($question['option3'], 0, 50)); ?>
                                        <?php echo strlen($question['option3']) > 50 ? '...' : ''; ?>
                                    </div>
                                    <div class="option <?php echo ($question['correct_option'] == '4') ? 'correct' : ''; ?>">
                                        D) <?php echo htmlspecialchars(substr($question['option4'], 0, 50)); ?>
                                        <?php echo strlen($question['option4']) > 50 ? '...' : ''; ?>
                                    </div>
                                    <?php if ($question['option5']): ?>
                                        <div class="option <?php echo ($question['correct_option'] == '5') ? 'correct' : ''; ?>">
                                            E) <?php echo htmlspecialchars(substr($question['option5'], 0, 50)); ?>
                                            <?php echo strlen($question['option5']) > 50 ? '...' : ''; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="question-meta">
                                    <span><strong>Course:</strong> <?php echo htmlspecialchars($question['course_title'] ?: 'No Course'); ?></span>
                                    <span><?php echo date('M j, Y', strtotime($question['created_at'])); ?></span>
                                </div>

                                <div class="question-actions">
                                    <button onclick="editQuestion(<?php echo htmlspecialchars(json_encode($question)); ?>)" class="btn btn-edit">Edit</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this question?')">
                                        <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                        <button type="submit" name="delete_question" class="btn btn-danger">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&course_filter=<?php echo $course_filter; ?>&difficulty_filter=<?php echo $difficulty_filter; ?>">← Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&course_filter=<?php echo $course_filter; ?>&difficulty_filter=<?php echo $difficulty_filter; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&course_filter=<?php echo $course_filter; ?>&difficulty_filter=<?php echo $difficulty_filter; ?>">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>`


            <!-- Add Question Tab Content -->
    <div id="add-question" class="tab-content">
        <h2>➕ Add New Question</h2>
        
        <div class="quick-actions">
            <button type="button" onclick="clearForm()" class="btn btn-secondary">Clear Form</button>
            <button type="button" onclick="loadDrafts()" class="btn btn-secondary">Load Draft</button>
            <button type="button" onclick="previewQuestion()" class="btn btn-secondary">Preview</button>
        </div>

        <form method="POST" id="addQuestionForm">
            <input type="hidden" name="add_question" value="1">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="course_id">Course *</label>
                    <select name="course_id" id="course_id" required>
                        <option value="">Select Course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>">
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-help">Choose the course this question belongs to</div>
                </div>
                
                <div class="form-group">
                    <label for="difficulty">Difficulty Level</label>
                    <select name="difficulty" id="difficulty">
                        <option value="">Select Difficulty</option>
                        <option value="easy">Easy</option>
                        <option value="medium">Medium</option>
                        <option value="hard">Hard</option>
                        <option value="green">Green Tick</option>
                    </select>
                    <div class="form-help">Optional: Set question difficulty</div>
                </div>
            </div>

            <div class="form-group">
                <label for="question_text">Question Text *</label>
                <textarea name="question_text" id="question_text" required 
                          placeholder="Enter your question here. Be clear and specific..."></textarea>
                <div class="form-help">Write a clear, unambiguous question</div>
            </div>

            <div class="form-group">
                <label for="question_image">Question Image URL (Optional)</label>
                <input type="url" name="question_image" id="question_image" 
                       placeholder="https://example.com/image.jpg"
                       onblur="previewImage(this)">
                <div class="form-help">Add an image to support your question (optional)</div>
                <div id="image_preview_container"></div>
            </div>

            <h3 style="margin: 30px 0 15px 0; color: #333; border-bottom: 2px solid #0d0; padding-bottom: 5px;">
                Answer Options
            </h3>

            <div class="form-row">
                <div class="form-group">
                    <label for="option1">Option A *</label>
                    <input type="text" name="option1" id="option1" required placeholder="First answer option">
                </div>
                <div class="form-group">
                    <label for="option2">Option B *</label>
                    <input type="text" name="option2" id="option2" required placeholder="Second answer option">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="option3">Option C *</label>
                    <input type="text" name="option3" id="option3" required placeholder="Third answer option">
                </div>
                <div class="form-group">
                    <label for="option4">Option D *</label>
                    <input type="text" name="option4" id="option4" required placeholder="Fourth answer option">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="option5">Option E (Optional)</label>
                    <input type="text" name="option5" id="option5" placeholder="Fifth answer option (optional)">
                    <div class="form-help">Leave blank if you only need 4 options</div>
                </div>
                <div class="form-group">
                    <label for="correct_option">Correct Answer *</label>
                    <select name="correct_option" id="correct_option" required>
                        <option value="">Select Correct Answer</option>
                        <option value="1">A</option>
                        <option value="2">B</option>
                        <option value="3">C</option>
                        <option value="4">D</option>
                        <option value="5">E</option>
                    </select>
                    <div class="form-help">Choose which option is correct</div>
                </div>
            </div>

            <div style="margin-top: 30px; display: flex; gap: 15px; justify-content: flex-end;">
                <button type="button" onclick="clearForm()" class="btn btn-secondary">Clear Form</button>
                <button type="submit" class="btn btn-primary" onclick="return validateQuestionForm('addQuestionForm')">
                    ➕ Add Question
                </button>
            </div>
        </form>

        <!-- Recent Activity -->
        <div class="recent-activity">
            <h3 style="margin-bottom: 15px;">Recent Questions Added</h3>
            <?php 
            $recent_stmt = $pdo->query("SELECT q.question_id, c.title as course_title, q.created_at 
                                      FROM questions q 
                                      LEFT JOIN courses c ON q.course_id = c.id 
                                      ORDER BY q.created_at DESC LIMIT 5");
            $recent_questions = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($recent_questions)): ?>
                <p style="color: #666; text-align: center; padding: 20px;">No questions added yet.</p>
            <?php else: ?>
                <?php foreach ($recent_questions as $recent): ?>
                    <div class="activity-item">
                        <div class="activity-icon activity-success">✓</div>
                        <div class="activity-info">
                            <div><?php echo htmlspecialchars(substr($recent['question_id'], 0, 60)); ?>...</div>
                            <small style="color: #666;">Course: <?php echo htmlspecialchars($recent['course_title'] ?: 'No Course'); ?></small>
                        </div>
                        <div class="activity-time">
                            <?php echo date('M j, g:i A', strtotime($recent['created_at'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Analytics Tab Content -->
    <div id="analytics" class="tab-content">
        <h2>📊 Question Analytics</h2>

        <div class="analytics-grid">

        
        <!-- Quick Stats Summary -->
        <div class="chart-container" style="margin-top: 20px;">
            <div class="chart-title">Summary Statistics</div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 2em; font-weight: bold; color: #0d0;">
                        <?php echo number_format($stats['total_questions']); ?>
                    </div>
                    <div style="color: #666;">Total Questions</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 2em; font-weight: bold; color: #0d0;">
                        <?php echo count($courses); ?>
                    </div>
                    <div style="color: #666;">Active Courses</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 2em; font-weight: bold; color: #0d0;">
                        <?php echo $stats['questions_today']; ?>
                    </div>
                    <div style="color: #666;">Added Today</div>
                </div>
                <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                    <div style="font-size: 2em; font-weight: bold; color: #0d0;">
                        <?php 
                        $avg_per_course = count($courses) > 0 ? round($stats['total_questions'] / count($courses), 1) : 0;
                        echo $avg_per_course; 
                        ?>
                    </div>
                    <div style="color: #666;">Avg per Course</div>
                </div>
            </div>
        </div>
        
            <!-- Difficulty Distribution -->
            <div class="chart-container">
                <div class="chart-title">Questions by Difficulty</div>
                <?php
                $difficulty_counts = ['easy' => 0, 'medium' => 0, 'hard' => 0, 'unset' => 0];
                foreach ($difficulty_stats as $stat) {
                    $difficulty_counts[$stat['difficulty'] ?: 'unset'] = $stat['count'];
                }
                $total_with_difficulty = array_sum($difficulty_counts);
                ?>
                
                <?php foreach (['easy', 'medium', 'hard', 'unset'] as $diff): ?>
                    <div class="stat-item">
                        <span class="stat-label">
                            <?php echo $diff === 'unset' ? 'Not Set' : ucfirst($diff); ?>
                        </span>
                        <span class="stat-value"><?php echo $difficulty_counts[$diff]; ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php 
                            echo $total_with_difficulty > 0 ? 
                            ($difficulty_counts[$diff] / $total_with_difficulty * 100) : 0; 
                        ?>%"></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Top Courses by Question Count -->
            <div class="chart-container">
                <div class="chart-title">Questions by Course</div>
                <?php if (empty($course_stats)): ?>
                    <p style="color: #666; text-align: center; padding: 20px;">No data available</p>
                <?php else: ?>
                    <?php 
                    $max_count = max(array_column($course_stats, 'count'));
                    foreach ($course_stats as $stat): 
                    ?>
                        <div class="stat-item">
                            <span class="stat-label">
                                <?php echo htmlspecialchars(substr($stat['title'], 0, 20)); ?>
                                <?php echo strlen($stat['title']) > 20 ? '...' : ''; ?>
                            </span>
                            <span class="stat-value"><?php echo $stat['count']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php 
                                echo $max_count > 0 ? ($stat['count'] / $max_count * 100) : 0; 
                            ?>%"></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Monthly Question Trends -->
            <div class="chart-container">
                <div class="chart-title">Questions Added This Month</div>
                <?php
                $monthly_stmt = $pdo->query("
                    SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as count 
                    FROM questions 
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date DESC
                    LIMIT 10
                ");
                $monthly_data = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <?php if (empty($monthly_data)): ?>
                    <p style="color: #666; text-align: center; padding: 20px;">No questions added in the last 30 days</p>
                <?php else: ?>
                    <?php 
                    $max_daily = max(array_column($monthly_data, 'count'));
                    foreach ($monthly_data as $day): 
                    ?>
                        <div class="stat-item">
                            <span class="stat-label"><?php echo date('M j', strtotime($day['date'])); ?></span>
                            <span class="stat-value"><?php echo $day['count']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php 
                                echo $max_daily > 0 ? ($day['count'] / $max_daily * 100) : 0; 
                            ?>%"></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Question Quality Metrics -->
            <div class="chart-container">
                <div class="chart-title">Question Quality</div>
                <?php
                $quality_stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN question_image IS NOT NULL THEN 1 ELSE 0 END) as with_images,
                        SUM(CASE WHEN difficulty IS NOT NULL THEN 1 ELSE 0 END) as with_difficulty,
                        SUM(CASE WHEN option5 IS NOT NULL AND option5 != '' THEN 1 ELSE 0 END) as with_five_options,
                        SUM(CASE WHEN CHAR_LENGTH(question_id) > 50 THEN 1 ELSE 0 END) as detailed_questions
                    FROM questions
                ");
                $quality = $quality_stmt->fetch(PDO::FETCH_ASSOC);
                $total = $quality['total'] ?: 1; // Prevent division by zero
                ?>
                
                <div class="stat-item">
                    <span class="stat-label">With Images</span>
                    <span class="stat-value"><?php echo round(($quality['with_images'] / $total) * 100, 1); ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo ($quality['with_images'] / $total) * 100; ?>%"></div>
                </div>

                <div class="stat-item">
                    <span class="stat-label">With Difficulty Set</span>
                    <span class="stat-value"><?php echo round(($quality['with_difficulty'] / $total) * 100, 1); ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo ($quality['with_difficulty'] / $total) * 100; ?>%"></div>
                </div>

                <div class="stat-item">
                    <span class="stat-label">5 Answer Options</span>
                    <span class="stat-value"><?php echo round(($quality['with_five_options'] / $total) * 100, 1); ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo ($quality['with_five_options'] / $total) * 100; ?>%"></div>
                </div>

                <div class="stat-item">
                    <span class="stat-label">Detailed Questions</span>
                    <span class="stat-value"><?php echo round(($quality['detailed_questions'] / $total) * 100, 1); ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo ($quality['detailed_questions'] / $total) * 100; ?>%"></div>
                </div>
            </div>
        </div>
    </div>
        </div>
    </div>
</body>
<script>
    // Global variables
let selectedQuestions = new Set();
let allSelected = false;

// Tab functionality
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active class from all tabs
    const tabs = document.querySelectorAll('.tab');
    tabs.forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab content
    document.getElementById(tabName).classList.add('active');
    
    // Add active class to clicked tab
    event.target.classList.add('active');
}

// Filter functionality
function applyFilters() {
    const search = document.getElementById('search').value;
    const courseFilter = document.getElementById('course_filter').value;
    const difficultyFilter = document.getElementById('difficulty_filter').value;
    
    // Build URL with filters
    const params = new URLSearchParams();
    if (search) params.append('search', search);
    if (courseFilter) params.append('course_filter', courseFilter);
    if (difficultyFilter) params.append('difficulty_filter', difficultyFilter);
    params.append('page', '1'); // Reset to first page
    
    window.location.href = '?' + params.toString();
}

// Apply filters on Enter key press in search
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    }
});

// Selection functionality
function updateSelection() {
    const checkboxes = document.querySelectorAll('.select-checkbox');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    const selectedQuestionsInput = document.getElementById('selectedQuestions');
    const selectAllBtn = document.getElementById('selectAllBtn');
    
    selectedQuestions.clear();
    
    checkboxes.forEach(checkbox => {
        const questionCard = checkbox.closest('.question-card');
        const questionId = questionCard.dataset.questionId;
        
        if (checkbox.checked) {
            selectedQuestions.add(questionId);
            questionCard.classList.add('selected');
        } else {
            questionCard.classList.remove('selected');
        }
    });
    
    const count = selectedQuestions.size;
    selectedCount.textContent = `${count} question${count !== 1 ? 's' : ''} selected`;
    selectedQuestionsInput.value = JSON.stringify([...selectedQuestions]);
    
    if (count > 0) {
        bulkActions.classList.add('show');
    } else {
        bulkActions.classList.remove('show');
    }
    
    // Update select all button text
    allSelected = count === checkboxes.length && count > 0;
    selectAllBtn.textContent = allSelected ? 'Deselect All' : 'Select All';
}

function toggleSelectAll() {
    const checkboxes = document.querySelectorAll('.select-checkbox');
    
    if (allSelected) {
        // Deselect all
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        allSelected = false;
    } else {
        // Select all
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        allSelected = true;
    }
    
    updateSelection();
}

// Edit question modal functionality
let editModal = null;

function createEditModal() {
    // Create modal HTML
    const modalHTML = `
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeEditModal()">&times;</span>
                <h2>Edit Question</h2>
                <form method="POST" id="editQuestionForm">
                    <input type="hidden" name="question_id" id="edit_question_id">
                    <input type="hidden" name="update_question" value="1">
                    
                    <div class="form-group">
                        <label for="edit_course_id">Course</label>
                        <select name="course_id" id="edit_course_id" required>
                            <option value="">Select Course</option>
                            ${getCourseOptions()}
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_question_text">Question Text</label>
                        <textarea name="question_text" id="edit_question_text" required placeholder="Enter the question text..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_option1">Option A</label>
                            <input type="text" name="option1" id="edit_option1" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_option2">Option B</label>
                            <input type="text" name="option2" id="edit_option2" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_option3">Option C</label>
                            <input type="text" name="option3" id="edit_option3" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_option4">Option D</label>
                            <input type="text" name="option4" id="edit_option4" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_option5">Option E (Optional)</label>
                            <input type="text" name="option5" id="edit_option5">
                        </div>
                        <div class="form-group">
                            <label for="edit_correct_option">Correct Answer</label>
                            <select name="correct_option" id="edit_correct_option" required>
                                <option value="">Select Correct Answer</option>
                                <option value="1">A</option>
                                <option value="2">B</option>
                                <option value="3">C</option>
                                <option value="4">D</option>
                                <option value="5">E</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_difficulty">Difficulty</label>
                            <select name="difficulty" id="edit_difficulty">
                                <option value="">Select Difficulty</option>
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                                <option value="green">Green Tick</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="edit_question_image">Question Image URL (Optional)</label>
                            <input type="url" name="question_image" id="edit_question_image">
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">Update Question</button>
                        <button type="button" onclick="closeEditModal()" class="btn" style="background: #6c757d; color: white;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    `;
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    editModal = document.getElementById('editModal');
    
    // Close modal when clicking outside
    editModal.addEventListener('click', function(e) {
        if (e.target === editModal) {
            closeEditModal();
        }
    });
}

function getCourseOptions() {
    // This function should return the course options HTML
    // You might want to populate this from PHP or make an AJAX call
    const courseSelect = document.getElementById('course_filter');
    if (courseSelect) {
        return courseSelect.innerHTML;
    }
    return '<option value="">No courses available</option>';
}

function editQuestion(questionData) {
    // Create modal if it doesn't exist
    if (!editModal) {
        createEditModal();
    }
    
    // Populate form with question data
    document.getElementById('edit_question_id').value = questionData.id;
    document.getElementById('edit_course_id').value = questionData.course_id || '';
    document.getElementById('edit_question_text').value = questionData.question_id || '';
    document.getElementById('edit_option1').value = questionData.option1 || '';
    document.getElementById('edit_option2').value = questionData.option2 || '';
    document.getElementById('edit_option3').value = questionData.option3 || '';
    document.getElementById('edit_option4').value = questionData.option4 || '';
    document.getElementById('edit_option5').value = questionData.option5 || '';
    document.getElementById('edit_correct_option').value = questionData.correct_option || '';
    document.getElementById('edit_difficulty').value = questionData.difficulty || '';
    document.getElementById('edit_question_image').value = questionData.question_image || '';
    
    // Show modal
    editModal.style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeEditModal() {
    if (editModal) {
        editModal.style.display = 'none';
        document.body.style.overflow = 'auto'; // Restore scrolling
    }
}

// Form validation for add question form
function validateQuestionForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = '#dc3545';
            isValid = false;
        } else {
            field.style.borderColor = '#ddd';
        }
    });
    
    // Check if at least 4 options are filled
    const options = [
        form.querySelector('[name="option1"]'),
        form.querySelector('[name="option2"]'),
        form.querySelector('[name="option3"]'),
        form.querySelector('[name="option4"]')
    ];
    
    const filledOptions = options.filter(option => option && option.value.trim()).length;
    if (filledOptions < 4) {
        alert('Please fill in at least 4 answer options (A, B, C, D).');
        isValid = false;
    }
    
    // Validate correct answer selection
    const correctOption = form.querySelector('[name="correct_option"]');
    if (correctOption && correctOption.value) {
        const optionIndex = parseInt(correctOption.value);
        if (optionIndex > filledOptions) {
            alert('The correct answer must correspond to a filled option.');
            isValid = false;
        }
    }
    
    return isValid;
}

// Real-time search functionality (optional enhancement)
function setupLiveSearch() {
    const searchInput = document.getElementById('search');
    if (!searchInput) return;
    
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (searchInput.value.length >= 3 || searchInput.value.length === 0) {
                applyFilters();
            }
        }, 500); // Wait 500ms after user stops typing
    });
}

// Image preview functionality
function setupImagePreview() {
    const imageInputs = document.querySelectorAll('input[type="url"][name*="image"]');
    
    imageInputs.forEach(input => {
        input.addEventListener('blur', function() {
            const url = this.value.trim();
            if (url) {
                // Create or update preview
                let preview = this.parentNode.querySelector('.image-preview');
                if (!preview) {
                    preview = document.createElement('img');
                    preview.className = 'image-preview';
                    preview.style.cssText = 'max-width: 200px; max-height: 150px; margin-top: 10px; border-radius: 6px; display: block;';
                    this.parentNode.appendChild(preview);
                }
                
                preview.src = url;
                preview.onerror = function() {
                    this.style.display = 'none';
                };
                preview.onload = function() {
                    this.style.display = 'block';
                };
            }
        });
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + A to select all questions
    if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !e.target.matches('input, textarea')) {
        e.preventDefault();
        toggleSelectAll();
    }
    
    // Escape to close modal
    if (e.key === 'Escape' && editModal && editModal.style.display === 'block') {
        closeEditModal();
    }
});

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Setup image preview
    setupImagePreview();
    
    // Setup live search (optional)
    // setupLiveSearch(); // Uncomment if you want live search
    
    // Add form validation to add question form
    const addQuestionForm = document.querySelector('#add-question form');
    if (addQuestionForm) {
        addQuestionForm.addEventListener('submit', function(e) {
            if (!validateQuestionForm('add-question')) {
                e.preventDefault();
            }
        });
    }
    
    // Initialize selection state
    updateSelection();
    
    // Smooth scrolling for pagination
    const paginationLinks = document.querySelectorAll('.pagination a');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });
});

// Utility function to show loading state
function showLoading(element, text = 'Loading...') {
    const originalText = element.textContent;
    element.textContent = text;
    element.disabled = true;
    
    return function hideLoading() {
        element.textContent = originalText;
        element.disabled = false;
    };
}

// Enhanced bulk delete with confirmation
function confirmBulkDelete() {
    const count = selectedQuestions.size;
    if (count === 0) {
        alert('No questions selected.');
        return false;
    }
    
    return confirm(`Are you sure you want to delete ${count} selected question${count !== 1 ? 's' : ''}? This action cannot be undone.`);
}

// Auto-save functionality for drafts (optional)
function setupAutoSave() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                // Save to localStorage with a unique key
                const formId = form.id || 'default';
                const key = `draft_${formId}_${input.name}`;
                localStorage.setItem(key, input.value);
            });
        });
    });
}

// Load drafts on page load
function loadDrafts() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            const formId = form.id || 'default';
            const key = `draft_${formId}_${input.name}`;
            const savedValue = localStorage.getItem(key);
            
            if (savedValue && !input.value) {
                input.value = savedValue;
            }
        });
    });
}

// Clear drafts after successful submission
function clearDrafts(formId) {
    const form = document.getElementById(formId);
    if (!form) return;
    
    const inputs = form.querySelectorAll('input, textarea, select');
    inputs.forEach(input => {
        const key = `draft_${formId}_${input.name}`;
        localStorage.removeItem(key);
    });
}
</script>