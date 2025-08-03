<?php
// b-take-exam.php - SIMPLIFIED AND BULLETPROOF
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if (!$course_id) {
    die("Invalid course ID");
}

try {
    // Get course info
    $stmt = $pdo->prepare("SELECT * FROM brainstorm_courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        die("Course not found");
    }
    
    // Check if user is registered
    $stmt = $pdo->prepare("SELECT * FROM brainstorm_registrations WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$user_id, $course_id]);
    $registration = $stmt->fetch();
    
    if (!$registration) {
        die("You are not registered for this course");
    }
    
    // Check attempts
    if ($registration['attempts_used'] >= $registration['max_attempts']) {
        die("Maximum attempts reached");
    }
    
    // Check for active exam
    $stmt = $pdo->prepare("SELECT * FROM brainstorm_exams WHERE user_id = ? AND course_id = ? AND status = 'active'");
    $stmt->execute([$user_id, $course_id]);
    $activeExam = $stmt->fetch();
    
    if ($activeExam) {
        // Resume exam
        header("Location: brainstorm-exam.php?exam_id=" . $activeExam['id']);
        exit;
    }
    
    // Create new exam
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("INSERT INTO brainstorm_exams (user_id, course_id, time_started, status) VALUES (?, ?, NOW(), 'active')");
    $stmt->execute([$user_id, $course_id]);
    $exam_id = $pdo->lastInsertId();
    
    // Update attempts
    $stmt = $pdo->prepare("UPDATE brainstorm_registrations SET attempts_used = attempts_used + 1 WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$user_id, $course_id]);
    
    $pdo->commit();
    
    header("Location: brainstorm-exam.php?exam_id=$exam_id");
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollback();
    }
    die("Error: " . $e->getMessage());
}
?>