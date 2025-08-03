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
    if (isset($_POST['add_user'])) {
        try {
            // Check if email or matric number already exists
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR matric_number = ?");
            $check_stmt->execute([$_POST['email'], $_POST['matric_number']]);
            
            if ($check_stmt->fetch()) {
                $message = "User with this email or matric number already exists!";
                $messageType = "error";
            } else {
                $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, matric_number, role, department, level, gender, time_created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['email'],
                    $hashed_password,
                    $_POST['matric_number'],
                    $_POST['role'],
                    $_POST['department'],
                    $_POST['level'],
                    $_POST['gender']
                ]);
                $message = "User added successfully!";
                $messageType = "success";
            }
        } catch(PDOException $e) {
            $message = "Error adding user: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    if (isset($_POST['update_user'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, matric_number = ?, role = ?, department = ?, level = ?, gender = ? WHERE id = ?");
            $stmt->execute([
                $_POST['name'],
                $_POST['email'],
                $_POST['matric_number'],
                $_POST['role'],
                $_POST['department'],
                $_POST['level'],
                $_POST['gender'],
                $_POST['user_id']
            ]);
            $message = "User updated successfully!";
            $messageType = "success";
        } catch(PDOException $e) {
            $message = "Error updating user: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    if (isset($_POST['delete_user'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$_POST['user_id']]);
            $message = "User deleted successfully!";
            $messageType = "success";
        } catch(PDOException $e) {
            $message = "Error deleting user: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    if (isset($_POST['reset_password'])) {
        try {
            $new_password = 'password123'; // Default password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_POST['user_id']]);
            $message = "Password reset successfully! New password: password123";
            $messageType = "success";
        } catch(PDOException $e) {
            $message = "Error resetting password: " . $e->getMessage();
            $messageType = "error";
        }
    }
    
    if (isset($_POST['toggle_login_status'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET is_logged_in = IF(is_logged_in = 1, 0, 1) WHERE id = ?");
            $stmt->execute([$_POST['user_id']]);
            $message = "Login status updated successfully!";
            $messageType = "success";
        } catch(PDOException $e) {
            $message = "Error updating login status: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get users with pagination and filtering
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$department_filter = isset($_GET['department_filter']) ? $_GET['department_filter'] : '';
$level_filter = isset($_GET['level_filter']) ? $_GET['level_filter'] : '';

$where_clause = "WHERE 1=1";
$params = [];

if ($search) {
    $where_clause .= " AND (name LIKE ? OR email LIKE ? OR matric_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter) {
    $where_clause .= " AND role = ?";
    $params[] = $role_filter;
}

if ($department_filter) {
    $where_clause .= " AND department = ?";
    $params[] = $department_filter;
}

if ($level_filter) {
    $where_clause .= " AND level = ?";
    $params[] = $level_filter;
}

// Get total count for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users $where_clause");
$count_stmt->execute($params);
$total_users = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_users / $limit);

// Get users
$users_stmt = $pdo->prepare("
    SELECT * FROM users 
    $where_clause 
    ORDER BY time_created DESC 
    LIMIT $limit OFFSET $offset
");
$users_stmt->execute($params);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
$stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

$stmt = $pdo->query("SELECT COUNT(*) as free_users FROM users WHERE role = 'free'");
$stats['free_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['free_users'];

$stmt = $pdo->query("SELECT COUNT(*) as premium_users FROM users WHERE role = 'premium'");
$stats['premium_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['premium_users'];

$stmt = $pdo->query("SELECT COUNT(*) as online_users FROM users WHERE is_logged_in = 1");
$stats['online_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['online_users'];

$stmt = $pdo->query("SELECT COUNT(*) as new_today FROM users WHERE DATE(time_created) = CURDATE()");
$stats['new_today'] = $stmt->fetch(PDO::FETCH_ASSOC)['new_today'];

$stmt = $pdo->query("SELECT COUNT(*) as active_week FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$stats['active_week'] = $stmt->fetch(PDO::FETCH_ASSOC)['active_week'];

// Get departments for filter
$dept_stmt = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department");
$departments = $dept_stmt->fetchColumn(0);
$departments = $dept_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get levels for filter
$level_stmt = $pdo->query("SELECT DISTINCT level FROM users WHERE level IS NOT NULL ORDER BY level");
$levels = $level_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TestMaster - Manage Users</title>
    <link rel="stylesheet" href="manage-users.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>👥 Manage Users</h1>
            <a href="admin-dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">Total Users</div>
            </div>

            <!-- Add User Tab -->
            <div id="add-user" class="tab-content">
                <h2>Add New User</h2>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" name="name" id="name" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" name="email" id="email" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="matric_number">Matric Number</label>
                            <input type="text" name="matric_number" id="matric_number" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" name="password" id="password" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">User Role</label>
                            <select name="role" id="role" required>
                                <option value="">Select Role</option>
                                <option value="free">Free User</option>
                                <option value="premium">Premium User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select name="gender" id="gender">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="department">Department</label>
                            <input type="text" name="department" id="department" placeholder="e.g., Computer Science">
                        </div>
                        <div class="form-group">
                            <label for="level">Level</label>
                            <select name="level" id="level">
                                <option value="">Select Level</option>
                                <option value="100 Level">100 Level</option>
                                <option value="200 Level">200 Level</option>
                                <option value="300 Level">300 Level</option>
                                <option value="400 Level">400 Level</option>
                                <option value="500 Level">500 Level</option>
                                <option value="Graduate">Graduate</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </form>
            </div>

            <!-- Analytics Tab -->
            <div id="analytics" class="tab-content">
                <h2>User Analytics & Reports</h2>
                
                <?php
                // Get registration trends (last 30 days)
                $registration_trends = $pdo->query("
                    SELECT DATE(time_created) as date, COUNT(*) as count 
                    FROM users 
                    WHERE time_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(time_created)
                    ORDER BY date DESC
                    LIMIT 10
                ")->fetchAll(PDO::FETCH_ASSOC);

                // Get department distribution
                $dept_stats = $pdo->query("
                    SELECT department, COUNT(*) as count 
                    FROM users 
                    WHERE department IS NOT NULL 
                    GROUP BY department 
                    ORDER BY count DESC
                ")->fetchAll(PDO::FETCH_ASSOC);

                // Get level distribution
                $level_stats = $pdo->query("
                    SELECT level, COUNT(*) as count 
                    FROM users 
                    WHERE level IS NOT NULL 
                    GROUP BY level 
                    ORDER BY level
                ")->fetchAll(PDO::FETCH_ASSOC);

                // Get gender distribution
                $gender_stats = $pdo->query("
                    SELECT gender, COUNT(*) as count 
                    FROM users 
                    WHERE gender IS NOT NULL 
                    GROUP BY gender
                ")->fetchAll(PDO::FETCH_ASSOC);

                // Get most active users (by login activity)
                $active_users = $pdo->query("
                    SELECT u.name, u.email, u.last_login, 
                           COUNT(DISTINCT e.id) as exam_count,
                           COUNT(DISTINCT br.id) as brainstorm_count
                    FROM users u 
                    LEFT JOIN exams e ON u.id = e.user_id 
                    LEFT JOIN brainstorm_exams br ON u.id = br.user_id 
                    WHERE u.last_login IS NOT NULL AND  e.time_ended is NOT NULL
                    GROUP BY u.id 
                    ORDER BY u.last_login DESC 
                    LIMIT 10
                ")->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                    <!-- Registration Trends -->
                    <div>
                        <h3 style="margin-bottom: 15px; color: #333;">Recent Registrations</h3>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>New Users</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($registration_trends)): ?>
                                        <tr>
                                            <td colspan="2" style="text-align: center; color: #666;">No recent registrations</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($registration_trends as $trend): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($trend['date'])); ?></td>
                                                <td><span class="role-badge role-premium"><?php echo $trend['count']; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Department Distribution -->
                    <div>
                        <h3 style="margin-bottom: 15px; color: #333;">Users by Department</h3>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Department</th>
                                        <th>Users</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($dept_stats)): ?>
                                        <tr>
                                            <td colspan="3" style="text-align: center; color: #666;">No department data</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        $total_dept_users = array_sum(array_column($dept_stats, 'count'));
                                        foreach ($dept_stats as $dept): 
                                            $percentage = round(($dept['count'] / $total_dept_users) * 100, 1);
                                        ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($dept['department']); ?></td>
                                                <td><span class="role-badge role-free"><?php echo $dept['count']; ?></span></td>
                                                <td><?php echo $percentage; ?>%</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
                    <!-- Level Distribution -->
                    <div>
                        <h3 style="margin-bottom: 15px; color: #333;">Users by Level</h3>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Level</th>
                                        <th>Users</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($level_stats as $level): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($level['level']); ?></td>
                                            <td><span class="role-badge role-admin"><?php echo $level['count']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Gender Distribution -->
                    <div>
                        <h3 style="margin-bottom: 15px; color: #333;">Gender Distribution</h3>
                        <div class="table-container">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Gender</th>
                                        <th>Users</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_gender_users = array_sum(array_column($gender_stats, 'count'));
                                    foreach ($gender_stats as $gender): 
                                        $percentage = $total_gender_users > 0 ? round(($gender['count'] / $total_gender_users) * 100, 1) : 0;
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($gender['gender']); ?></td>
                                            <td><span class="role-badge role-premium"><?php echo $gender['count']; ?></span></td>
                                            <td><?php echo $percentage; ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Most Active Users -->
                <div>
                    <h3 style="margin-bottom: 15px; color: #333;">Most Active Users</h3>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Last Login</th>
                                    <th>Exams Taken</th>
                                    <th>Brainstorm Sessions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($active_users)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: #666;">No active users found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($active_users as $active_user): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($active_user['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($active_user['email']); ?></td>
                                            <td><?php echo date('M j, g:i A', strtotime($active_user['last_login'])); ?></td>
                                            <td><span class="role-badge role-free"><?php echo $active_user['exam_count']; ?></span></td>
                                            <td><span class="role-badge role-premium"><?php echo $active_user['brainstorm_count']; ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h2>Edit User</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_name">Full Name</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_email">Email Address</label>
                        <input type="email" name="email" id="edit_email" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_matric_number">Matric Number</label>
                        <input type="text" name="matric_number" id="edit_matric_number" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_role">User Role</label>
                        <select name="role" id="edit_role" required>
                            <option value="free">Free User</option>
                            <option value="premium">Premium User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_department">Department</label>
                        <input type="text" name="department" id="edit_department">
                    </div>
                    <div class="form-group">
                        <label for="edit_level">Level</label>
                        <select name="level" id="edit_level">
                            <option value="">Select Level</option>
                            <option value="100 Level">100 Level</option>
                            <option value="200 Level">200 Level</option>
                            <option value="300 Level">300 Level</option>
                            <option value="400 Level">400 Level</option>
                            <option value="500 Level">500 Level</option>
                            <option value="Graduate">Graduate</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit_gender">Gender</label>
                    <select name="gender" id="edit_gender">
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>

                <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
            </form>
        </div>
    </div>

    <script src="manage-users.js"></script>
</body>
</html>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['free_users']); ?></div>
                <div class="stat-label">Free Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['premium_users']); ?></div>
                <div class="stat-label">Premium Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['online_users']; ?></div>
                <div class="stat-label">Online Now</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['new_today']; ?></div>
                <div class="stat-label">New Today</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['active_week']; ?></div>
                <div class="stat-label">Active This Week</div>
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
                <button class="tab active" onclick="showTab('users')">👥 All Users</button>
                <button class="tab" onclick="showTab('add-user')">➕ Add User</button>
                <button class="tab" onclick="showTab('analytics')">📊 Analytics</button>
            </div>

            <!-- Users List Tab -->
            <div id="users" class="tab-content active">
                <h2>User Management (<?php echo number_format($total_users); ?> users)</h2>
                
                <!-- Filters -->
                <div class="filters">
                    <input type="text" id="search" placeholder="Search by name, email, or matric number..." value="<?php echo htmlspecialchars($search); ?>">
                    <select id="role_filter">
                        <option value="">All Roles</option>
                        <option value="free" <?php echo ($role_filter == 'free') ? 'selected' : ''; ?>>Free</option>
                        <option value="premium" <?php echo ($role_filter == 'premium') ? 'selected' : ''; ?>>Premium</option>
                        <option value="admin" <?php echo ($role_filter == 'admin') ? 'selected' : ''; ?>>Admin</option>
                    </select>
                    <select id="department_filter">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($department_filter == $dept) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select id="level_filter">
                        <option value="">All Levels</option>
                        <?php foreach ($levels as $level): ?>
                            <option value="<?php echo htmlspecialchars($level); ?>" <?php echo ($level_filter == $level) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($level); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button onclick="applyFilters()" class="btn btn-primary">Filter</button>
                </div>

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Matric No.</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Level</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 30px; color: #666;">
                                        No users found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar" style="overflow: hidden">
                                                    <img style="width: 80px;" src="https://portal.abu.edu.ng/pixx/<?= htmlspecialchars($matric_num) ?>.JPG">
                                                </div>
                                                <div class="user-details">
                                                    <h4><?php echo htmlspecialchars($user['name'] ?: 'No Name'); ?></h4>
                                                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['matric_number'] ?: 'N/A'); ?></td>
                                        <td>
                                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                                <?php echo strtoupper($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['department'] ?: 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($user['level'] ?: 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $user['is_logged_in'] ? 'online' : 'offline'; ?>">
                                                <?php echo $user['is_logged_in'] ? 'Online' : 'Offline'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $user['last_login'] ? date('M j, g:i A', strtotime($user['last_login'])) : 'Never'; ?>
                                        </td>
                                        <td class="actions-cell">
                                            <button onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)" class="btn btn-edit">Edit</button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Reset password to default?')">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="reset_password" class="btn btn-warning">Reset Password</button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Toggle login status?')">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="toggle_login_status" class="btn btn-secondary">
                                                    <?php echo $user['is_logged_in'] ? 'Force Logout' : 'Allow Login'; ?>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
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
                            <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&role_filter=<?php echo $role_filter; ?>&department_filter=<?php echo urlencode($department_filter); ?>&level_filter=<?php echo urlencode($level_filter); ?>">← Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role_filter=<?php echo $role_filter; ?>&department_filter=<?php echo urlencode($department_filter); ?>&level_filter=<?php echo urlencode($level_filter); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&role_filter=<?php echo $role_filter; ?>&department_filter=<?php echo urlencode($department_filter); ?>&level_filter=<?php echo urlencode($level_filter); ?>">Next →</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>