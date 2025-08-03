<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['session_token'])) {
    // Deactivate current session
    $stmt = $pdo->prepare("UPDATE user_sessions SET is_active = 0 WHERE session_token = ?");
    $stmt->execute([$_SESSION['session_token']]);
    
    // Log logout activity
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("INSERT INTO activities (user_id, action, timestamp) VALUES (?, 'logout', NOW())");
        $stmt->execute([$_SESSION['user_id']]);
    }
}

// Clear all session data
session_destroy();
header("Location: login.php");
exit;
?>