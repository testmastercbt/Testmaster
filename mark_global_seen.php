<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notification_id = (int)($_POST['notification_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($notification_id && $user_id) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO global_notification_views (user_id, notification_id, seen, seen_at) VALUES (?, ?, 1, NOW())");
        $stmt->execute([$user_id, $notification_id]);
    }
}
?>