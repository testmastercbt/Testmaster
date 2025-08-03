 <?php
session_start();
require_once 'config.php'; // PDO connection

/**
 * Generate a secure device token
 */
function generateDeviceToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Generate a secure session token
 */
function generateSessionToken() {
    return bin2hex(random_bytes(64));
}

/**
 * Get or create device token for current request
 */
function getDeviceToken($pdo, $user_id) {
    // Check if device token exists in cookie
    if (isset($_COOKIE['device_token'])) {
        $device_token = $_COOKIE['device_token'];
        
        // Verify device exists in database
        $stmt = $pdo->prepare("SELECT id FROM user_devices WHERE device_token = ? AND user_id = ?");
        $stmt->execute([$device_token, $user_id]);
        
        if ($stmt->fetch()) {
            // Update last seen
            $pdo->prepare("UPDATE user_devices SET last_seen = NOW() WHERE device_token = ?")->execute([$device_token]);
            return $device_token;
        }
    }
    
    // Generate new device token
    $device_token = generateDeviceToken();
    
    // Detect device type
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $device_type = (preg_match('/Mobile|Android|iPhone|iPad/', $user_agent)) ? 'mobile' : 'desktop';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Insert new device
    $stmt = $pdo->prepare("
        INSERT INTO user_devices (user_id, device_token, device_type, user_agent, ip_address, last_seen) 
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE last_seen = NOW(), user_agent = VALUES(user_agent), ip_address = VALUES(ip_address)
    ");
    $stmt->execute([$user_id, $device_token, $device_type, $user_agent, $ip_address]);
    
    // Set cookie for 30 days
    setcookie('device_token', $device_token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
    
    return $device_token;
}

/**
 * Create new session record
 */
function createSession($pdo, $user_id, $device_token) {
    $session_token = generateSessionToken();
    
    // Deactivate any existing sessions for this device
    $pdo->prepare("UPDATE user_sessions SET is_active = 0 WHERE user_id = ? AND device_token = ?")->execute([$user_id, $device_token]);
    
    // Create new session
    $stmt = $pdo->prepare("
        INSERT INTO user_sessions (user_id, session_token, device_token, is_active, login_time) 
        VALUES (?, ?, ?, 1, NOW())
    ");
    $stmt->execute([$user_id, $session_token, $device_token]);
    
    // Store session token in PHP session
    $_SESSION['session_token'] = $session_token;
    $_SESSION['device_token'] = $device_token;
    
    return $session_token;
}

/**
 * Log user activity
 */
function logActivity($pdo, $user_id, $action) {
    $stmt = $pdo->prepare("INSERT INTO activities (user_id, action, timestamp) VALUES (?, ?, NOW())");
    $stmt->execute([$user_id, $action]);
}

/**
 * Clean up old sessions (optional - call this periodically)
 */
function cleanupOldSessions($pdo, $days = 30) {
    $stmt = $pdo->prepare("DELETE FROM user_sessions WHERE login_time < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
}

/**
 * Clean up old devices (optional - call this periodically)
 */
function cleanupOldDevices($pdo, $days = 90) {
    $stmt = $pdo->prepare("DELETE FROM user_devices WHERE last_seen < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days]);
}

// Main authentication logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matric = trim($_POST['matric_number']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);

    if (empty($matric) || empty($password)) {
        header("Location: login.php?error=All fields are required.");
        exit;
    }

    try {
        // Prepare secure query
        $stmt = $pdo->prepare("SELECT * FROM users WHERE matric_number = ?");
        $stmt->execute([$matric]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Set basic session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['matric_number'] = $user['matric_number'];

            // Handle device tracking and session management
            $device_token = getDeviceToken($pdo, $user['id']);
            $session_token = createSession($pdo, $user['id'], $device_token);

            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            // Log login activity
            logActivity($pdo, $user['id'], 'login');

            // Handle "Remember Me" functionality
            if ($remember_me) {
                // Extend session cookie lifetime to 30 days
                ini_set('session.cookie_lifetime', 30 * 24 * 60 * 60);
                session_regenerate_id(true);
            }

            // Optional: Clean up old sessions and devices (you might want to do this less frequently)
            if (rand(1, 100) <= 5) { // 5% chance to run cleanup
                cleanupOldSessions($pdo);
                cleanupOldDevices($pdo);
            }

            // Redirect to dashboard
            header("Location: dashboard.php");
            exit;
        } else {
            // Log failed login attempt
            if ($user) {
                logActivity($pdo, $user['id'], 'failed_login_attempt');
            }
            
            header("Location: login.php?error=Invalid credentials");
            exit;
        }
    } catch (PDOException $e) {
        // Log error (in production, log to file instead of displaying)
        error_log("Authentication error: " . $e->getMessage());
        header("Location: login.php?error=System error. Please try again.");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}
?>