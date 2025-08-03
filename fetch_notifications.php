<?php
require_once 'config.php';
session_start();

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode([]);
    exit;
}

// Fetch global notifications
$globalStmt = $pdo->prepare("SELECT id, title, message, created_at FROM global_notifications ORDER BY created_at DESC");
$globalStmt->execute();
$globalNotes = $globalStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user-specific notifications
$userStmt = $pdo->prepare("SELECT id, title, message, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$userStmt->execute([$user_id]);
$userNotes = $userStmt->fetchAll(PDO::FETCH_ASSOC);

// Combine
$allNotes = array_merge($globalNotes, $userNotes);

// Sort by newest
usort($allNotes, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

echo json_encode($allNotes);