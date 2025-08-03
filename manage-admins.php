<?php
session_start();

// Security check - ensure user is logged in and is admin
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "testmaster_db";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get current admin details and check permissions
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['admin_id']]);
$current_admin = $stmt->fetch();

// Only superadmins can manage other admins
$can_manage_admins = ($current_admin['role'] === 'superadmin');

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage_admins) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_admin':
                try {
                    $name = trim($_POST['admin_name']);
                    $email = trim($_POST['admin_email']);
                    $password = $_POST['admin_password'];
                    $confirm_password = $_POST['confirm_password'];
                    $role = $_POST['admin_role'];
                    
                    // Validation
                    if (empty($name) || empty($email) || empty($password)) {
                        throw new Exception("All fields are required.");
                    }
                    
                    if ($password !== $confirm_password) {
                        throw new Exception("Passwords do not match.");
                    }
                    
                    if (strlen($password) < 6) {
                        throw new Exception("Password must be at least 6 characters long.");
                    }
                    
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception("Please enter a valid email address.");
                    }
                    
                    // Check if email already exists
                    $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        throw new Exception("An admin with this email already exists.");
                    }
                    
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new admin
                    $stmt = $pdo->prepare("INSERT INTO admins (name, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $hashed_password, $role]);
                    
                    $success_message = "Admin created successfully!";
                    
                    // Log the activity
                    $activity = "Created new admin: " . $name . " (" . $email . ") with role: " . $role;
                    $stmt = $pdo->prepare("INSERT INTO activities (user_id, action) VALUES (?, ?)");
                    $stmt->execute([$_SESSION['admin_id'], $activity]);
                    
                } catch (Exception $e) {
                    $error_message = "Error creating admin: " . $e->getMessage();
                }
                break;
                
            case 'update_admin':
                try {
                    $admin_id = $_POST['admin_id'];
                    $name = trim($_POST['edit_name']);
                    $email = trim($_POST['edit_email']);
                    $role = $_POST['edit_role'];
                    
                    // Validation
                    if (empty($name) || empty($email)) {
                        throw new Exception("Name and email are required.");
                    }
                    
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new Exception("Please enter a valid email address.");
                    }
                    
                    // Prevent self-demotion
                    if ($admin_id == $_SESSION['admin_id'] && $role !== 'superadmin') {
                        throw new Exception("You cannot change your own role.");
                    }
                    
                    // Check if email exists for another admin
                    $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $admin_id]);
                    if ($stmt->fetch()) {
                        throw new Exception("Another admin with this email already exists.");
                    }
                    
                    // Update admin
                    $stmt = $pdo->prepare("UPDATE admins SET name = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $role, $admin_id]);
                    
                    $success_message = "Admin updated successfully!";
                    
                    // Log the activity
                    $activity = "Updated admin: " . $name . " (" . $email . ")";
                    $stmt = $pdo->prepare("INSERT INTO activities (user_id, action) VALUES (?, ?)");
                    $stmt->execute([$_SESSION['admin_id'], $activity]);
                    
                } catch (Exception $e) {
                    $error_message = "Error updating admin: " . $e->getMessage();
                }
                break;
                
            case 'delete_admin':
                try {
                    $admin_id = $_POST['admin_id'];
                    
                    // Prevent self-deletion
                    if ($admin_id == $_SESSION['admin_id']) {
                        throw new Exception("You cannot delete your own account.");
                    }
                    
                    // Get admin details before deletion for logging
                    $stmt = $pdo->prepare("SELECT name, email FROM admins WHERE id = ?");
                    $stmt->execute([$admin_id]);
                    $admin_to_delete = $stmt->fetch();
                    
                    if (!$admin_to_delete) {
                        throw new Exception("Admin not found.");
                    }
                    
                    // Delete admin
                    $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
                    $stmt->execute([$admin_id]);
                    
                    $success_message = "Admin deleted successfully!";
                    
                    // Log the activity
                    $activity = "Deleted admin: " . $admin_to_delete['name'] . " (" . $admin_to_delete['email'] . ")";
                    $stmt = $pdo->prepare("INSERT INTO activities (user_id, action) VALUES (?, ?)");
                    $stmt->execute([$_SESSION['admin_id'], $activity]);
                    
                } catch (Exception $e) {
                    $error_message = "Error deleting admin: " . $e->getMessage();
                }
                break;
                
            case 'reset_password':
                try {
                    $admin_id = $_POST['admin_id'];
                    $new_password = $_POST['new_password'];
                    $confirm_new_password = $_POST['confirm_new_password'];
                    
                    if (empty($new_password)) {
                        throw new Exception("New password is required.");
                    }
                    
                    if ($new_password !== $confirm_new_password) {
                        throw new Exception("Passwords do not match.");
                    }
                    
                    if (strlen($new_password) < 6) {
                        throw new Exception("Password must be at least 6 characters long.");
                    }
                    
                    // Hash new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password
                    $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $admin_id]);
                    
                    // Get admin name for logging
                    $stmt = $pdo->prepare("SELECT name FROM admins WHERE id = ?");
                    $stmt->execute([$admin_id]);
                    $admin_name = $stmt->fetch()['name'];
                    
                    $success_message = "Password reset successfully!";
                    
                    // Log the activity
                    $activity = "Reset password for admin: " . $admin_name;
                    $stmt = $pdo->prepare("INSERT INTO activities (user_id, action) VALUES (?, ?)");
                    $stmt->execute([$_SESSION['admin_id'], $activity]);
                    
                } catch (Exception $e) {
                    $error_message = "Error resetting password: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get all admins
$stmt = $pdo->query("SELECT * FROM admins ORDER BY created_at DESC");
$all_admins = $stmt->fetchAll();

// Get admin statistics
$stats = [];
$stmt = $pdo->query("SELECT COUNT(*) as total FROM admins");
$stats['total_admins'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM admins WHERE role = 'superadmin'");
$stats['superadmins'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM admins WHERE role = 'admin'");
$stats['regular_admins'] = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM admins WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['recent_admins'] = $stmt->fetch()['total'];

// Get recent admin activities
$stmt = $pdo->prepare("
    SELECT a.*, ad.name as admin_name 
    FROM activities a 
    LEFT JOIN admins ad ON a.user_id = ad.id 
    WHERE a.action LIKE '%admin%' 
    ORDER BY a.timestamp DESC 
    LIMIT 10
");
$stmt->execute();
$recent_activities = $stmt->fetchAll();

// Get admin login statistics (if you want to track this)
$admin_logins = [];
foreach ($all_admins as $admin) {
    // You could track admin logins in a separate table or add last_login to admins table
    $admin_logins[$admin['id']] = [
        'last_login' => $admin['created_at'], // Placeholder - replace with actual last_login field
        'login_count' => rand(5, 50) // Placeholder - replace with actual count
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TestMaster Admin - Manage Admins</title>
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
            margin-bottom: 10px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 1rem;
            font-weight: 600;
        }

        .stat-card.blue .stat-number { color: #3498db; }
        .stat-card.green .stat-number { color: #27ae60; }
        .stat-card.orange .stat-number { color: #f39c12; }
        .stat-card.purple .stat-number { color: #9b59b6; }

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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
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

        .btn-success {
            background: linear-gradient(45deg, #27ae60, #229954);
            color: white;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
            padding: 8px 16px;
            font-size: 0.9rem;
        }

        .btn-success:hover {
            background: linear-gradient(45deg, #229954, #1e8449);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
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

        .alert-warning {
            background: linear-gradient(45deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-top: 20px;
        }

        .admin-table th {
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .admin-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #ecf0f1;
            font-size: 0.9rem;
        }

        .admin-table tr:hover {
            background: #f8f9fa;
        }

        .role-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .role-superadmin {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: white;
        }

        .role-admin {
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            color: #2c3e50;
            margin: 0;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: #e74c3c;
        }

        .activity-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .activity-item:hover {
            background: #e3f2fd;
            border-color: #3498db;
            transform: translateX(5px);
        }

        .activity-time {
            color: #7f8c8d;
            font-size: 0.8rem;
            margin-bottom: 5px;
        }

        .activity-action {
            color: #2c3e50;
            font-weight: 600;
        }

        .activity-admin {
            color: #3498db;
            font-size: 0.9rem;
        }

        .permission-warning {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        .permission-warning h3 {
            color: #e74c3c;
            margin-bottom: 15px;
        }

        .search-box {
            position: relative;
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 2px solid #ecf0f1;
            border-radius: 10px;
            font-size: 1rem;
        }

        .search-box::after {
            content: '🔍';
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
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
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .admin-table {
                font-size: 0.8rem;
            }
            
            .admin-table th,
            .admin-table td {
                padding: 8px 6px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .modal-content {
                margin: 5% auto;
                width: 95%;
                padding: 20px;
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

        .password-strength {
            margin-top: 5px;
            font-size: 0.8rem;
        }

        .strength-weak { color: #e74c3c; }
        .strength-medium { color: #f39c12; }
        .strength-strong { color: #27ae60; }

        .show-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #7f8c8d;
        }

        .password-field {
            position: relative;
        }

        .full-width-card {
            grid-column: 1 / -1;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin-dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <div class="header">
            <h1>Manage Administrators</h1>
            <p>Create, update, and manage admin accounts and permissions</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if (!$can_manage_admins): ?>
            <div class="alert alert-warning">
                <strong>Limited Access:</strong> You have read-only access. Only superadmins can create, edit, or delete admin accounts.
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-number"><?php echo $stats['total_admins']; ?></div>
                <div class="stat-label">Total Administrators</div>
            </div>
            <div class="stat-card green">
                <div class="stat-number"><?php echo $stats['superadmins']; ?></div>
                <div class="stat-label">Super Administrators</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-number"><?php echo $stats['regular_admins']; ?></div>
                <div class="stat-label">Regular Administrators</div>
            </div>
            <div class="stat-card purple">
                <div class="stat-number"><?php echo $stats['recent_admins']; ?></div>
                <div class="stat-label">Added This Month</div>
            </div>
        </div>

        <?php if ($can_manage_admins): ?>
        <!-- Create New Admin Section -->
        <div class="content-grid">
            <!-- Left Column - Create Admin -->
            <div class="card">
                <h2>Create New Administrator</h2>
                
                <form method="POST" onsubmit="return validateCreateForm()">
                    <input type="hidden" name="action" value="create_admin">
                    
                    <div class="form-group">
                        <label for="admin_name">Full Name</label>
                        <input type="text" id="admin_name" name="admin_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_email">Email Address</label>
                        <input type="email" id="admin_email" name="admin_email" class="form-control" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="admin_password">Password</label>
                            <div class="password-field">
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="6">
                                <span class="show-password" onclick="togglePassword('confirm_password')">👁️</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_role">Role</label>
                        <select id="admin_role" name="admin_role" class="form-control" required>
                            <option value="admin">Regular Administrator</option>
                            <option value="superadmin">Super Administrator</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Create Administrator</button>
                </form>
            </div>

            <!-- Right Column - Recent Activities -->
            <div class="card">
                <h2>Recent Admin Activities</h2>
                
                <div class="activity-list">
                    <?php if (empty($recent_activities)): ?>
                        <div class="activity-item">
                            <div class="activity-action">No admin activities found</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-time"><?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?></div>
                                <div class="activity-action"><?php echo htmlspecialchars($activity['action']); ?></div>
                                <div class="activity-admin">by <?php echo htmlspecialchars($activity['admin_name'] ?? 'Unknown Admin'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Administrators List -->
        <div class="card full-width-card">
            <h2>All Administrators</h2>
            
            <!-- Search Box -->
            <div class="search-box">
                <input type="text" id="adminSearch" placeholder="Search administrators..." onkeyup="filterAdmins()">
            </div>
            
            <?php if ($can_manage_admins): ?>
                <table class="admin-table" id="adminTable">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_admins as $admin): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($admin['name']); ?></td>
                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                <td>
                                    <span class="role-badge role-<?php echo $admin['role']; ?>">
                                        <?php echo ucfirst($admin['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($admin['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-warning" onclick="editAdmin(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['name']); ?>', '<?php echo htmlspecialchars($admin['email']); ?>', '<?php echo $admin['role']; ?>')">Edit</button>
                                        <button class="btn btn-success" onclick="resetPassword(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['name']); ?>')">Reset Password</button>
                                        <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                            <button class="btn btn-danger" onclick="deleteAdmin(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars($admin['name']); ?>')">Delete</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="permission-warning">
                    <h3>Access Restricted</h3>
                    <p>You need superadmin privileges to view the full administrators list.</p>
                    <p>Contact your system administrator for access.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Edit Admin Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="close" onclick="closeModal('editModal')">&times;</span>
                    <h3>Edit Administrator</h3>
                </div>
                
                <form method="POST" onsubmit="return validateEditForm()">
                    <input type="hidden" name="action" value="update_admin">
                    <input type="hidden" id="edit_admin_id" name="admin_id">
                    
                    <div class="form-group">
                        <label for="edit_name">Full Name</label>
                        <input type="text" id="edit_name" name="edit_name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">Email Address</label>
                        <input type="email" id="edit_email" name="edit_email" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_role">Role</label>
                        <select id="edit_role" name="edit_role" class="form-control" required>
                            <option value="admin">Regular Administrator</option>
                            <option value="superadmin">Super Administrator</option>
                        </select>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">Update Administrator</button>
                        <button type="button" class="btn" onclick="closeModal('editModal')" style="background: #95a5a6; color: white;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reset Password Modal -->
        <div id="passwordModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="close" onclick="closeModal('passwordModal')">&times;</span>
                    <h3>Reset Password</h3>
                </div>
                
                <form method="POST" onsubmit="return validatePasswordForm()">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" id="password_admin_id" name="admin_id">
                    
                    <div class="form-group">
                        <label id="password_admin_label">Resetting password for: </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="password-field">
                            <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6" onkeyup="checkPasswordStrength(this.value, 'new-password-strength')">
                            <span class="show-password" onclick="togglePassword('new_password')">👁️</span>
                        </div>
                        <div id="new-password-strength" class="password-strength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_new_password">Confirm New Password</label>
                        <div class="password-field">
                            <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-control" required minlength="6">
                            <span class="show-password" onclick="togglePassword('confirm_new_password')">👁️</span>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-primary">Reset Password</button>
                        <button type="button" class="btn" onclick="closeModal('passwordModal')" style="background: #95a5a6; color: white;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <span class="close" onclick="closeModal('deleteModal')">&times;</span>
                    <h3>Confirm Deletion</h3>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="delete_admin">
                    <input type="hidden" id="delete_admin_id" name="admin_id">
                    
                    <div class="form-group">
                        <p id="delete_confirmation_text">Are you sure you want to delete this administrator?</p>
                        <p style="color: #e74c3c; font-weight: bold;">This action cannot be undone!</p>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-danger">Yes, Delete Administrator</button>
                        <button type="button" class="btn" onclick="closeModal('deleteModal')" style="background: #95a5a6; color: white;">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Password strength checker
        function checkPasswordStrength(password, targetId = 'password-strength') {
            const strengthElement = document.getElementById(targetId);
            let strength = 0;
            let message = '';
            
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            switch (strength) {
                case 0:
                case 1:
                    message = 'Very Weak';
                    strengthElement.className = 'password-strength strength-weak';
                    break;
                case 2:
                    message = 'Weak';
                    strengthElement.className = 'password-strength strength-weak';
                    break;
                case 3:
                    message = 'Medium';
                    strengthElement.className = 'password-strength strength-medium';
                    break;
                case 4:
                    message = 'Strong';
                    strengthElement.className = 'password-strength strength-strong';
                    break;
                case 5:
                    message = 'Very Strong';
                    strengthElement.className = 'password-strength strength-strong';
                    break;
            }
            
            strengthElement.textContent = password.length > 0 ? `Password strength: ${message}` : '';
        }

        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
            field.setAttribute('type', type);
        }

        // Form validation
        function validateCreateForm() {
            const password = document.getElementById('admin_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            return true;
        }

        function validateEditForm() {
            const name = document.getElementById('edit_name').value.trim();
            const email = document.getElementById('edit_email').value.trim();
            
            if (!name || !email) {
                alert('Name and email are required!');
                return false;
            }
            
            return true;
        }

        function validatePasswordForm() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_new_password').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long!');
                return false;
            }
            
            return true;
        }

        // Modal functions
        function editAdmin(id, name, email, role) {
            document.getElementById('edit_admin_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            document.getElementById('editModal').style.display = 'block';
        }

        function resetPassword(id, name) {
            document.getElementById('password_admin_id').value = id;
            document.getElementById('password_admin_label').textContent = `Resetting password for: ${name}`;
            document.getElementById('passwordModal').style.display = 'block';
        }

        function deleteAdmin(id, name) {
            document.getElementById('delete_admin_id').value = id;
            document.getElementById('delete_confirmation_text').textContent = `Are you sure you want to delete "${name}"?`;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Search/Filter functionality
        function filterAdmins() {
            const input = document.getElementById('adminSearch');
            const filter = input.value.toLowerCase();
            const table = document.getElementById('adminTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let match = false;
                
                for (let j = 0; j < cells.length - 1; j++) { // Exclude actions column
                    if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                        match = true;
                        break;
                    }
                }
                
                rows[i].style.display = match ? '' : 'none';
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }

        // Auto-clear alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>