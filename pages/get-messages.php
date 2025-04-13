<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'chat_error.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection - Use absolute path
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Check if request is AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Get the user ID to fetch messages from
$other_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Validate input
if ($other_user_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

try {
    // Get messages between current user and the specified user
    $stmt = $pdo->prepare("
        SELECT m.*, 
            u_sender.first_name as sender_first_name, 
            u_sender.last_name as sender_last_name,
            u_sender.profile_image as sender_image
        FROM messages m
        JOIN users u_sender ON m.sender_id = u_sender.id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
            OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC
    ");
    
    $stmt->execute([
        $_SESSION['user_id'], $other_user_id, 
        $other_user_id, $_SESSION['user_id']
    ]);
    
    $messages = $stmt->fetchAll();
    
    // Mark messages as read
    $update_stmt = $pdo->prepare("
        UPDATE messages
        SET is_read = 1
        WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
    ");
    
    $update_stmt->execute([$other_user_id, $_SESSION['user_id']]);
    
    // Return messages
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'messages' => $messages,
            'current_user_id' => $_SESSION['user_id']
        ]
    ]);
    exit;
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}
?>

