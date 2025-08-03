<?php
// ====================
// 💡 Utility Functions
// ====================

function sanitize_input($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        redirect('login');
    }
}

// Token Generator (for CSRF or whatever)
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

function verify_token($token, $session_token) {
    return hash_equals($session_token, $token);
}

// Example usage:
// $_SESSION['csrf_token'] = generate_token();
// verify_token($_POST['token'], $_SESSION['csrf_token']);

?>