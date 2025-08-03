<?php

require_once 'config.php';
session_start();

// Security check - ensure user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}


// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_global':
                try {
                    $title = trim($_POST['global_title']);
                    $message = trim($_POST['global_message']);
                    
                    if (empty($title) || empty($message)) {
                        throw new Exception("Title and message are required.");
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO global_notifications (title, message) VALUES (?, ?)");
                    $stmt->execute([$title, $message]);
                    
                    $success_message = "Global notification created successfully!";
                } catch (Exception $e) {
                    $error_message = "Error creating global notification: " . $e->getMessage();
                }
                break;
                
            case 'create_individual':
                try {
                    $user_id = $_POST['user_id'];
                    $title = trim($_POST['individual_title']);
                    $message = trim($_POST['individual_message']);
                    
                    if (empty($user_id) || empty($title) || empty($message)) {
                        throw new Exception("All fields are required.");
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
                    $stmt->execute([$user_id, $title, $message]);
                    
                    $success_message = "Individual notification sent successfully!";
                } catch (Exception $e) {
                    $error_message = "Error sending individual notification: " . $e->getMessage();
                }
                break;
                
            case 'create_bulk':
                try {
                    $department = $_POST['department'];
                    $level = $_POST['level'];
                    $title = trim($_POST['bulk_title']);
                    $message = trim($_POST['bulk_message']);
                    
                    if (empty($title) || empty($message)) {
                        throw new Exception("Title and message are required.");
                    }
                    
                    // Build query based on filters
                    $where_conditions = [];
                    $params = [$title, $message];
                    
                    if (!empty($department) && $department !== 'all') {
                        $where_conditions[] = "department = ?";
                        $params[] = $department;
                    }
                    
                    if (!empty($level) && $level !== 'all') {
                        $where_conditions[] = "level = ?";
                        $params[] = $level;
                    }
                    
                    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message)
                        SELECT id, ?, ? FROM users $where_clause
                    ");
                    $stmt->execute($params);
                    
                    $affected_rows = $stmt->rowCount();
                    $success_message = "Bulk notification sent to $affected_rows users successfully!";
                } catch (Exception $e) {
                    $error_message = "Error sending bulk notification: " . $e->getMessage();
                }
                break;
                
            case 'delete_global':
                try {
                    $id = $_POST['notification_id'];
                    $stmt = $pdo->prepare("DELETE FROM global_notifications WHERE id = ?");
                    $stmt->execute([$id]);
                    $success_message = "Global notification deleted successfully!";
                } catch (Exception $e) {
                    $error_message = "Error deleting notification: " . $e->getMessage();
                }
                break;
                
            case 'delete_individual':
                try {
                    $id = $_POST['notification_id'];
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
                    $stmt->execute([$id]);
                    $success_message = "Individual notification deleted successfully!";
                } catch (Exception $e) {
                    $error_message = "Error deleting notification: " . $e->getMessage();
                }
                break;
                
            case 'mark_read':
                try {
                    $id = $_POST['notification_id'];
                    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
                    $stmt->execute([$id]);
                    $success_message = "Notification marked as read!";
                } catch (Exception $e) {
                    $error_message = "Error updating notification: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get statistics
$stats = [];

// Global notifications count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM global_notifications");
$stats['global_total'] = $stmt->fetch()['total'];

// Individual notifications count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM notifications");
$stats['individual_total'] = $stmt->fetch()['total'];

// Unread notifications count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM notifications WHERE is_read = 0");
$stats['unread_total'] = $stmt->fetch()['total'];

// Recent notifications this week
$stmt = $pdo->query("SELECT COUNT(*) as total FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['recent_total'] = $stmt->fetch()['total'];

// Get all users for dropdown
$stmt = $pdo->query("SELECT id, name, email, department, level FROM users ORDER BY name");
$users = $stmt->fetchAll();

// Get unique departments and levels
$stmt = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department");
$departments = $stmt->fetchAll();

$stmt = $pdo->query("SELECT DISTINCT level FROM users WHERE level IS NOT NULL ORDER BY level");
$levels = $stmt->fetchAll();

// Get recent global notifications
$stmt = $pdo->query("SELECT * FROM global_notifications ORDER BY created_at DESC LIMIT 10");
$global_notifications = $stmt->fetchAll();

// Get recent individual notifications
$stmt = $pdo->prepare("
    SELECT n.*, u.name as user_name, u.email as user_email 
    FROM notifications n 
    JOIN users u ON n.user_id = u.id 
    ORDER BY n.created_at DESC 
    LIMIT 20
");
$stmt->execute();
$individual_notifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TestMaster Admin - Manage Notifications</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #333;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .header h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #3498db, #9b59b6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        .back-btn {
            background: linear-gradient(45deg, #95a5a6, #7f8c8d);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
        }

        .back-btn:hover {
            background: linear-gradient(45deg, #7f8c8d, #6c7b7d);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(149, 165, 166, 0.4);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 1rem;
            font-weight: 600;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .card h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            font-size: 1.6rem;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ecf0f1;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-control.textarea {
            height: 120px;
            resize: vertical;
            font-family: inherit;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(45deg, #2980b9, #1f4e79);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        .btn-success {
            background: linear-gradient(45deg, #27ae60, #229954);
            color: white;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }

        .btn-success:hover {
            background: linear-gradient(45deg, #229954, #1e8449);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        }

        .btn-danger {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: white;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
            padding: 8px 16px;
            font-size: 0.9rem;
        }

        .btn-danger:hover {
            background: linear-gradient(45deg, #c0392b, #a93226);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .btn-warning {
            background: linear-gradient(45deg, #f39c12, #e67e22);
            color: white;
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);
            padding: 8px 16px;
            font-size: 0.9rem;
        }

        .btn-warning:hover {
            background: linear-gradient(45deg, #e67e22, #d68910);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(243, 156, 18, 0.4);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .alert-success {
            background: linear-gradient(45deg, #d5f4e6, #a3e9d0);
            color: #1e7e34;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: linear-gradient(45deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .tabs {
            display: flex;
            background: #ecf0f1;
            border-radius: 10px;
            padding: 5px;
            margin-bottom: 25px;
        }

        .tab {
            flex: 1;
            padding: 12px 20px;
            text-align: center;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            color: #7f8c8d;
        }

        .tab.active {
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .notification-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .notification-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            background: #e3f2fd;
            border-color: #3498db;
            transform: translateX(5px);
        }

        .notification-item.unread {
            background: linear-gradient(45deg, #fff3cd, #ffeaa7);
            border-color: #f39c12;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .notification-title {
            font-weight: bold;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .notification-date {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .notification-message {
            color: #5a6c7d;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-read {
            background: #d5f4e6;
            color: #1e7e34;
        }

        .status-unread {
            background: #fff3cd;
            color: #856404;
        }

        .row {
            display: flex;
            gap: 15px;
        }

        .col-half {
            flex: 1;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .card {
                padding: 20px;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .row {
                flex-direction: column;
            }
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #ecf0f1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(45deg, #3498db, #9b59b6);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(45deg, #2980b9, #8e44ad);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin-dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <div class="header">
            <h1>Manage Notifications</h1>
            <p>Create, send, and manage notifications for your users</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['global_total']; ?></div>
                <div class="stat-label">Global Notifications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['individual_total']; ?></div>
                <div class="stat-label">Individual Notifications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['unread_total']; ?></div>
                <div class="stat-label">Unread Notifications</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['recent_total']; ?></div>
                <div class="stat-label">This Week</div>
            </div>
        </div>

        <!-- Create Notifications Section -->
        <div class="content-grid">
            <!-- Left Column - Create Notifications -->
            <div class="card">
                <h2>Create New Notification</h2>
                
                <div class="tabs">
                    <button class="tab active" onclick="switchTab('global')">Global</button>
                    <button class="tab" onclick="switchTab('individual')">Individual</button>
                    <button class="tab" onclick="switchTab('bulk')">Bulk</button>
                </div>

                <!-- Global Notification Tab -->
                <div id="global" class="tab-content active">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_global">
                        
                        <div class="form-group">
                            <label for="global_title">Notification Title</label>
                            <input type="text" id="global_title" name="global_title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="global_message">Message</label>
                            <textarea id="global_message" name="global_message" class="form-control textarea" required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Send Global Notification</button>
                    </form>
                </div>

                <!-- Individual Notification Tab -->
                <div id="individual" class="tab-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_individual">
                        
                        <div class="form-group">
                            <label for="user_id">Select User</label>
                            <select id="user_id" name="user_id" class="form-control" required>
                                <option value="">Choose a user...</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="individual_title">Notification Title</label>
                            <input type="text" id="individual_title" name="individual_title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="individual_message">Message</label>
                            <textarea id="individual_message" name="individual_message" class="form-control textarea" required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-success">Send Individual Notification</button>
                    </form>
                </div>

                <!-- Bulk Notification Tab -->
                <div id="bulk" class="tab-content">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_bulk">
                        
                        <div class="row">
                            <div class="col-half">
                                <div class="form-group">
                                    <label for="department">Department Filter</label>
                                    <select id="department" name="department" class="form-control">
                                        <option value="all">All Departments</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?php echo htmlspecialchars($dept['department']); ?>">
                                                <?php echo htmlspecialchars($dept['department']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-half">
                                <div class="form-group">
                                    <label for="level">Level Filter</label>
                                    <select id="level" name="level" class="form-control">
                                        <option value="all">All Levels</option>
                                        <?php foreach ($levels as $lvl): ?>
                                            <option value="<?php echo htmlspecialchars($lvl['level']); ?>">
                                                <?php echo htmlspecialchars($lvl['level']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="bulk_title">Notification Title</label>
                            <input type="text" id="bulk_title" name="bulk_title" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="bulk_message">Message</label>
                            <textarea id="bulk_message" name="bulk_message" class="form-control textarea" required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Send Bulk Notification</button>
                    </form>
                </div>
            </div>

            <!-- Right Column - Recent Notifications -->
            <div class="card">
                <h2>Recent Notifications</h2>
                
                <div class="tabs">
                    <button class="tab active" onclick="switchNotificationTab('recent-global')">Global</button>
                    <button class="tab" onclick="switchNotificationTab('recent-individual')">Individual</button>
                </div>

                <!-- Recent Global Notifications -->
                <div id="recent-global" class="tab-content active">
                    <div class="notification-list">
                        <?php if (empty($global_notifications)): ?>
                            <p style="text-align: center; color: #7f8c8d; padding: 40px;">No global notifications yet.</p>
                        <?php else: ?>
                            <?php foreach ($global_notifications as $notification): ?>
                                <div class="notification-item">
                                    <div class="notification-header">
                                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                        <div class="notification-date"><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></div>
                                    </div>
                                    <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                    <div class="notification-meta">
                                        <span class="status-badge status-read">Global</span>
                                        <div class="notification-actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_global">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this notification?')">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Individual Notifications -->
                <div id="recent-individual" class="tab-content">
                    <div class="notification-list">
                        <?php if (empty($individual_notifications)): ?>
                            <p style="text-align: center; color: #7f8c8d; padding: 40px;">No individual notifications yet.</p>
                        <?php else: ?>
                            <?php foreach ($individual_notifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                                    <div class="notification-header">
                                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                        <div class="notification-date"><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></div>
                                    </div>
                                    <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                    <div class="notification-meta">
                                        <div>
                                            <strong>To:</strong> <?php echo htmlspecialchars($notification['user_name']); ?> (<?php echo htmlspecialchars($notification['user_email']); ?>)
                                            <span class="status-badge <?php echo $notification['is_read'] ? 'status-read' : 'status-unread'; ?>">
                                                <?php echo $notification['is_read'] ? 'Read' : 'Unread'; ?>
                                            </span>
                                        </div>
                                        <div class="notification-actions">
                                            <?php if (!$notification['is_read']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="mark_read">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit" class="btn btn-warning">Mark Read</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_individual">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this notification?')">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Section -->
        <div class="card">
            <h2>Notification Analytics</h2>
            
            <div class="tabs">
                <button class="tab active" onclick="switchAnalyticsTab('overview')">Overview</button>
                <button class="tab" onclick="switchAnalyticsTab('engagement')">Engagement</button>
                <button class="tab" onclick="switchAnalyticsTab('trends')">Trends</button>
            </div>

            <!-- Overview Tab -->
            <div id="overview" class="tab-content active">
                <div class="stats-grid">
                    <?php
                    // Get additional analytics data
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
                    $today_notifications = $stmt->fetch()['total'];
                    
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
                    $week_notifications = $stmt->fetch()['total'];
                    
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
                    $month_notifications = $stmt->fetch()['total'];
                    
                    $read_rate = $stats['individual_total'] > 0 ? round((($stats['individual_total'] - $stats['unread_total']) / $stats['individual_total']) * 100, 1) : 0;
                    ?>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $today_notifications; ?></div>
                        <div class="stat-label">Today</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $week_notifications; ?></div>
                        <div class="stat-label">This Week</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $month_notifications; ?></div>
                        <div class="stat-label">This Month</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $read_rate; ?>%</div>
                        <div class="stat-label">Read Rate</div>
                    </div>
                </div>
            </div>

            <!-- Engagement Tab -->
            <div id="engagement" class="tab-content">
                <?php
                // Get engagement statistics
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(CASE WHEN is_read = 1 THEN 1 END) as read_count,
                        COUNT(CASE WHEN is_read = 0 THEN 1 END) as unread_count,
                        AVG(CASE WHEN is_read = 1 THEN TIMESTAMPDIFF(HOUR, created_at, NOW()) END) as avg_read_time
                    FROM notifications 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                $engagement = $stmt->fetch();
                
                $stmt = $pdo->query("
                    SELECT 
                        u.department,
                        COUNT(n.id) as notification_count,
                        COUNT(CASE WHEN n.is_read = 1 THEN 1 END) as read_count
                    FROM notifications n
                    JOIN users u ON n.user_id = u.id
                    WHERE n.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY u.department
                    ORDER BY notification_count DESC
                ");
                $department_stats = $stmt->fetchAll();
                ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $engagement['read_count'] ?: 0; ?></div>
                        <div class="stat-label">Read (30 days)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $engagement['unread_count'] ?: 0; ?></div>
                        <div class="stat-label">Unread (30 days)</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo round($engagement['avg_read_time'] ?: 0, 1); ?>h</div>
                        <div class="stat-label">Avg. Read Time</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($department_stats); ?></div>
                        <div class="stat-label">Active Departments</div>
                    </div>
                </div>

                <h3 style="margin: 30px 0 20px 0; color: #2c3e50;">Department Engagement</h3>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($department_stats as $dept): ?>
                        <?php
                        $dept_read_rate = $dept['notification_count'] > 0 ? round(($dept['read_count'] / $dept['notification_count']) * 100, 1) : 0;
                        ?>
                        <div class="notification-item">
                            <div class="notification-header">
                                <div class="notification-title"><?php echo htmlspecialchars($dept['department'] ?: 'Unknown'); ?></div>
                                <div class="notification-date"><?php echo $dept_read_rate; ?>% read rate</div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #7f8c8d;">
                                <span>Total: <?php echo $dept['notification_count']; ?></span>
                                <span>Read: <?php echo $dept['read_count']; ?></span>
                                <span>Unread: <?php echo $dept['notification_count'] - $dept['read_count']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Trends Tab -->
            <div id="trends" class="tab-content">
                <?php
                // Get daily notification trends for the last 7 days
                $stmt = $pdo->query("
                    SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as count,
                        COUNT(CASE WHEN is_read = 1 THEN 1 END) as read_count
                    FROM notifications 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date DESC
                ");
                $daily_trends = $stmt->fetchAll();
                
                // Get hourly distribution
                $stmt = $pdo->query("
                    SELECT 
                        HOUR(created_at) as hour,
                        COUNT(*) as count
                    FROM notifications 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY HOUR(created_at)
                    ORDER BY hour
                ");
                $hourly_trends = $stmt->fetchAll();
                ?>
                
                <h3 style="margin: 0 0 20px 0; color: #2c3e50;">Daily Trends (Last 7 Days)</h3>
                <div style="max-height: 250px; overflow-y: auto; margin-bottom: 30px;">
                    <?php foreach ($daily_trends as $trend): ?>
                        <?php
                        $read_rate = $trend['count'] > 0 ? round(($trend['read_count'] / $trend['count']) * 100, 1) : 0;
                        ?>
                        <div class="notification-item">
                            <div class="notification-header">
                                <div class="notification-title"><?php echo date('M j, Y', strtotime($trend['date'])); ?></div>
                                <div class="notification-date"><?php echo $trend['count']; ?> notifications</div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.9rem; color: #7f8c8d;">
                                <span>Read: <?php echo $trend['read_count']; ?> (<?php echo $read_rate; ?>%)</span>
                                <span>Unread: <?php echo $trend['count'] - $trend['read_count']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <h3 style="margin: 0 0 20px 0; color: #2c3e50;">Peak Hours (Last 7 Days)</h3>
                <div style="max-height: 250px; overflow-y: auto;">
                    <?php foreach ($hourly_trends as $trend): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f8f9fa; margin-bottom: 5px; border-radius: 8px;">
                            <span style="font-weight: 600;"><?php echo sprintf('%02d:00', $trend['hour']); ?></span>
                            <span style="color: #3498db; font-weight: 600;"><?php echo $trend['count']; ?> notifications</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching functions
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        function switchNotificationTab(tabName) {
            // Hide all notification tab contents
            document.querySelectorAll('#recent-global, #recent-individual').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from notification tabs
            document.querySelectorAll('.card:nth-child(2) .tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        function switchAnalyticsTab(tabName) {
            // Hide all analytics tab contents
            document.querySelectorAll('#overview, #engagement, #trends').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from analytics tabs
            document.querySelectorAll('.card:last-child .tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked tab
            event.target.classList.add('active');
        }

        // Auto-refresh notifications every 30 seconds
        function refreshNotifications() {
            // You could implement AJAX refresh here
            console.log('Checking for new notifications...');
        }

        // Set up auto-refresh
        setInterval(refreshNotifications, 30000);

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Add form validation for all forms
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.style.borderColor = '#e74c3c';
                        } else {
                            field.style.borderColor = '#ecf0f1';
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            });

            // Character counter for message fields
            const messageFields = document.querySelectorAll('textarea');
            messageFields.forEach(field => {
                const counter = document.createElement('div');
                counter.style.cssText = 'text-align: right; color: #7f8c8d; font-size: 0.8rem; margin-top: 5px;';
                field.parentNode.appendChild(counter);
                
                function updateCounter() {
                    const length = field.value.length;
                    counter.textContent = `${length} characters`;
                    
                    if (length > 500) {
                        counter.style.color = '#e74c3c';
                    } else if (length > 400) {
                        counter.style.color = '#f39c12';
                    } else {
                        counter.style.color = '#7f8c8d';
                    }
                }
                
                field.addEventListener('input', updateCounter);
                updateCounter();
            });

            // Search functionality for user dropdown
            const userSelect = document.getElementById('user_id');
            if (userSelect) {
                // Convert select to searchable input
                const wrapper = document.createElement('div');
                wrapper.style.position = 'relative';
                
                const searchInput = document.createElement('input');
                searchInput.type = 'text';
                searchInput.className = 'form-control';
                searchInput.placeholder = 'Search users...';
                
                const dropdown = document.createElement('div');
                dropdown.style.cssText = `
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: white;
                    border: 2px solid #ecf0f1;
                    border-top: none;
                    border-radius: 0 0 10px 10px;
                    max-height: 200px;
                    overflow-y: auto;
                    display: none;
                    z-index: 1000;
                `;
                
                // Store original options
                const originalOptions = Array.from(userSelect.options).slice(1); // Skip the first "Choose a user..." option
                
                userSelect.parentNode.insertBefore(wrapper, userSelect);
                wrapper.appendChild(searchInput);
                wrapper.appendChild(dropdown);
                wrapper.appendChild(userSelect);
                userSelect.style.display = 'none';
                
                function filterUsers(searchTerm) {
                    dropdown.innerHTML = '';
                    
                    if (!searchTerm) {
                        dropdown.style.display = 'none';
                        return;
                    }
                    
                    const filteredOptions = originalOptions.filter(option => 
                        option.textContent.toLowerCase().includes(searchTerm.toLowerCase())
                    );
                    
                    if (filteredOptions.length === 0) {
                        dropdown.innerHTML = '<div style="padding: 10px; color: #7f8c8d;">No users found</div>';
                    } else {
                        filteredOptions.forEach(option => {
                            const item = document.createElement('div');
                            item.style.cssText = 'padding: 10px; cursor: pointer; border-bottom: 1px solid #ecf0f1;';
                            item.textContent = option.textContent;
                            item.addEventListener('click', () => {
                                userSelect.value = option.value;
                                searchInput.value = option.textContent;
                                dropdown.style.display = 'none';
                            });
                            item.addEventListener('mouseenter', () => {
                                item.style.background = '#e3f2fd';
                            });
                            item.addEventListener('mouseleave', () => {
                                item.style.background = 'white';
                            });
                            dropdown.appendChild(item);
                        });
                    }
                    
                    dropdown.style.display = 'block';
                }
                
                searchInput.addEventListener('input', (e) => {
                    filterUsers(e.target.value);
                });
                
                searchInput.addEventListener('focus', () => {
                    if (searchInput.value) {
                        filterUsers(searchInput.value);
                    }
                });
                
                document.addEventListener('click', (e) => {
                    if (!wrapper.contains(e.target)) {
                        dropdown.style.display = 'none';
                    }
                });
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + Enter to submit forms
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const activeTab = document.querySelector('.tab-content.active');
                const form = activeTab?.querySelector('form');
                if (form) {
                    form.submit();
                }
            }
            
            // Escape to close dropdowns
            if (e.key === 'Escape') {
                document.querySelectorAll('[style*="display: block"]').forEach(el => {
                    if (el.style.position === 'absolute') {
                        el.style.display = 'none';
                    }
                });
            }
        });

        // Smooth scrolling for long lists
        function smoothScrollToTop(element) {
            element.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // Add scroll to top button for long notification lists
        document.querySelectorAll('.notification-list').forEach(list => {
            if (list.children.length > 5) {
                const scrollBtn = document.createElement('button');
                scrollBtn.textContent = '↑ Top';
                scrollBtn.className = 'btn btn-primary';
                scrollBtn.style.cssText = `
                    position: sticky;
                    bottom: 10px;
                    float: right;
                    margin-top: 10px;
                    padding: 8px 16px;
                    font-size: 0.9rem;
                `;
                scrollBtn.addEventListener('click', () => smoothScrollToTop(list));
                list.parentNode.appendChild(scrollBtn);
            }
        });

        // Auto-save draft functionality for message fields
        const draftKeys = {
            'global_message': 'draft_global_message',
            'individual_message': 'draft_individual_message',
            'bulk_message': 'draft_bulk_message'
        };

        Object.keys(draftKeys).forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                // Load draft on page load
                const draft = localStorage.getItem(draftKeys[fieldId]);
                if (draft && !field.value) {
                    field.value = draft;
                }

                // Save draft on input
                field.addEventListener('input', function() {
                    localStorage.setItem(draftKeys[fieldId], this.value);
                });

                // Clear draft on successful submission
                field.closest('form').addEventListener('submit', function() {
                    localStorage.removeItem(draftKeys[fieldId]);
                });
            }
        });

        // Real-time notification preview
        function createPreview(titleField, messageField, previewContainer) {
            function updatePreview() {
                const title = titleField.value || 'Notification Title';
                const message = messageField.value || 'Your notification message will appear here...';
                
                previewContainer.innerHTML = `
                    <div class="notification-item" style="margin-top: 20px;">
                        <div class="notification-header">
                            <div class="notification-title">${title}</div>
                            <div class="notification-date">Just now</div>
                        </div>
                        <div class="notification-message">${message}</div>
                        <div class="notification-meta">
                            <span class="status-badge status-unread">Preview</span>
                        </div>
                    </div>
                `;
            }

            titleField.addEventListener('input', updatePreview);
            messageField.addEventListener('input', updatePreview);
            updatePreview();
        }

        // Add preview containers to each form
        document.addEventListener('DOMContentLoaded', function() {
            const forms = [
                { title: 'global_title', message: 'global_message', form: 'global' },
                { title: 'individual_title', message: 'individual_message', form: 'individual' },
                { title: 'bulk_title', message: 'bulk_message', form: 'bulk' }
            ];

            forms.forEach(config => {
                const titleField = document.getElementById(config.title);
                const messageField = document.getElementById(config.message);
                const formContainer = document.getElementById(config.form);

                if (titleField && messageField && formContainer) {
                    const previewContainer = document.createElement('div');
                    previewContainer.innerHTML = '<h4 style="margin: 20px 0 10px 0; color: #2c3e50;">Preview:</h4>';
                    formContainer.appendChild(previewContainer);
                    
                    createPreview(titleField, messageField, previewContainer);
                }
            });
        });
    </script>
</body>
</html>