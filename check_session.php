<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

// Check if request is POST and has the correct action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($input && $input['action'] === 'check_session') {
        // Check if required session variables exist
        $session_active = isset($_SESSION['user_id']) && 
                         isset($_SESSION['exam_session_id']) && 
                         isset($_SESSION['questions']);
        
        echo json_encode(['session_active' => $session_active]);
        exit;
    }
}

// Invalid request
echo json_encode(['session_active' => false]);
exit;
?>