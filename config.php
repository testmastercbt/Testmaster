<?php
// DATABASE CONFIGURATION

$host = 'localhost';         // Default for XAMPP
$db   = 'testmaster_db';     // Make sure this matches your DB name
$user = 'root';              // Default user
$pass = '';                  // Default password is empty
$charset = 'utf8mb4';        // Charset that supports all characters

// Data Source Name
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// PDO settings
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Show errors
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return assoc arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements
];

try {
    // Connect to the database
    $pdo = new PDO($dsn, $user, $pass, $options);
    // echo "✅ Connected successfully";
} catch (PDOException $e) {
    die("❌ DB Connection failed: " . $e->getMessage());
}
?>