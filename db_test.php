<?php
require_once 'config.php';

try {
    // Try fetching something from the users table
    $stmt = $pdo->query("SELECT COUNT(*) AS total_users FROM users");
    $result = $stmt->fetch();

    echo "✅ Connected to DB. Total users in system: " . $result['total_users'];
} catch (Exception $e) {
    echo "❌ Query failed: " . $e->getMessage();
}
