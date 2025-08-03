<?php
require_once 'config.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_role'])) {
    header('Location: admin-login.php');
    exit();
}

// Get admin info
$admin_stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$admin_stmt->execute([$_SESSION['admin_id']]);
$admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);

// Get dashboard statistics
$stats = [];

// Total users
$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

// Total exams taken
$stmt = $pdo->query("SELECT COUNT(*) as total_exams FROM exams");
$stats['total_exams'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_exams'];

// Questions added today
$stmt = $pdo->query("SELECT COUNT(*) as questions_today FROM questions WHERE DATE(created_at) = CURDATE()");
$stats['questions_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['questions_today'];

// Pending feedback
$stmt = $pdo->query("SELECT COUNT(*) as pending_feedback FROM feedback WHERE status = 'pending'");
$stats['pending_feedback'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_feedback'];

// Recent feedback (last 5)
$recent_feedback_stmt = $pdo->query("
    SELECT f.*, u.name, q.question_id
    FROM feedback f 
    JOIN users u ON f.user_id = u.id 
    LEFT JOIN questions q ON f.message = q.id 
    ORDER BY f.created_at DESC 
    LIMIT 10
");
$recent_feedback = $recent_feedback_stmt->fetchAll(PDO::FETCH_ASSOC);

// Recently added questions (last 5)
$recent_questions_stmt = $pdo->query("
    SELECT q.*, c.title 
    FROM questions q 
    JOIN courses c ON q.course_id = c.id 
    ORDER BY q.created_at DESC 
    LIMIT 10
");
$recent_questions = $recent_questions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Active users (users who took exams in last 24 hours)
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT user_id) as active_users 
    FROM exams 
    WHERE time_started >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");
$stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_users'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Testmaster</title>
    <link rel="stylesheet" href="admin-dashboard.css">
    <style>
        .superadmin-only {
            display: <?php echo ($_SESSION['admin_role'] === 'superadmin') ? 'block' : 'none'; ?>;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <h1>TestMaster Dashboard</h1>
            </div>
            <div class="admin-info">
                <h3>Welcome, <?php echo htmlspecialchars($admin['name']); ?>!</h3>
                <span class="admin-role"><?php echo htmlspecialchars($admin['role']); ?></span>
                <br><br>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_exams']); ?></div>
                <div class="stat-label">Exams Taken</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['questions_today']; ?></div>
                <div class="stat-label">Questions Added Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_users']; ?></div>
                <div class="stat-label">Active Users (24h)</div>
            </div>
        </div>

        <!-- Navigation Cards -->
        <div class="nav-grid">
            <a href="manage-courses.php" class="nav-card">
                <div class="nav-icon">📚</div>
                <div class="nav-title">Manage Courses</div>
                <div class="nav-desc">Add, edit, and organize courses</div>
            </a>
            
            <a href="manage-questions.php" class="nav-card">
                <div class="nav-icon">❓</div>
                <div class="nav-title">Manage Questions</div>
                <div class="nav-desc">Add and edit exam questions</div>
            </a>
            
            <a href="manage-users.php" class="nav-card">
                <div class="nav-icon">👥</div>
                <div class="nav-title">Manage Users</div>
                <div class="nav-desc">Monitor student accounts</div>
            </a>
            
            <a href="view-results.php" class="nav-card">
                <div class="nav-icon">📊</div>
                <div class="nav-title">View Results</div>
                <div class="nav-desc">Analyze exam performance</div>
            </a>
            
            <a href="feedback-center.php" class="nav-card">
                <div class="nav-icon">💬</div>
                <div class="nav-title">Feedback Center</div>
                <div class="nav-desc">Review student feedback</div>
            </a>
            
            <a href="settings.php" class="nav-card">
                <div class="nav-icon">⚙️</div>
                <div class="nav-title">Settings</div>
                <div class="nav-desc">Platform configuration</div>
            </a>
            
            <a href="manage-admins.php" class="nav-card superadmin-only">
                <div class="nav-icon">👑</div>
                <div class="nav-title">Manage Admins</div>
                <div class="nav-desc">Add/remove admin accounts</div>
            </a>

            
            <a href="manage-brainstorm.php" class="nav-card superadmin-only">
                <div class="nav-icon">📊</div>
                <div class="nav-title">Manage Brainstorm</div>
                <div class="nav-desc">Manage The Brainstorm Event</div>
            </a>
        </div>

        <!-- Widgets -->
        <div class="widgets-grid">
            <!-- Recent Feedback Widget -->
            <div class="widget">
                <h3>Recent Feedback (<?php echo $stats['pending_feedback']; ?> pending)</h3>
                <?php if (empty($recent_feedback)): ?>
                    <p style="color: #666; text-align: center; padding: 20px;">No recent feedback</p>
                <?php else: ?>
                    <?php foreach ($recent_feedback as $feedback): ?>
                        <div class="feedback-item">
                            <div class="feedback-user">
                                <?php echo htmlspecialchars($feedback['name']); ?>
                                <span style="float: right; color: #999; font-size: 0.8em;">
                                    <?php echo date('M j, g:i A', strtotime($feedback['created_at'])); ?>
                                </span>
                            </div>
                            <div class="feedback-text">
                                <?php echo htmlspecialchars(substr($feedback['message'], 0, 100)); ?>
                                <?php echo strlen($feedback['message']) > 100 ? '...' : ''; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recently Added Questions Widget -->
            <div class="widget">
                <h3>Recently Added Questions</h3>
                <?php if (empty($recent_questions)): ?>
                    <p style="color: #666; text-align: center; padding: 20px;">No recent questions</p>
                <?php else: ?>
                    <?php foreach ($recent_questions as $question): ?>
                        <div class="question-item">
                            <div class="question-text">
                                <?php echo htmlspecialchars(substr($question['question_id'], 0, 80)); ?>
                                <?php echo strlen($question['question_id']) > 80 ? '...' : ''; ?>
                            </div>
                            <div class="question-course">
                                <?php echo htmlspecialchars($question['title']); ?>
                                <span style="float: right; color: #999; font-size: 0.8em;">
                                    <?php echo date('M j', strtotime($question['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh statistics every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);

        // Add click animation to nav cards
        document.querySelectorAll('.nav-card').forEach(card => {
            card.addEventListener('click', function(e) {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = '';
                }, 150);
            });
        });
    </script>
</body>
</html>