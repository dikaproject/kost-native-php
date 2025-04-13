<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once '../config/database.php';

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

// Process the message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    // Validate input
    if (empty($message)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
        exit;
    }
    
    if ($receiver_id <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid recipient']);
        exit;
    }
    
    try {
        // Insert message
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, is_read, created_at)
            VALUES (?, ?, ?, 0, NOW())
        ");
        
        $result = $stmt->execute([$_SESSION['user_id'], $receiver_id, $message]);
        
        if ($result) {
            // Get the created timestamp and message ID
            $message_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT created_at FROM messages WHERE id = ?");
            $stmt->execute([$message_id]);
            $created_at = $stmt->fetchColumn();
            
            // Create notification for the receiver
            $stmt = $pdo->prepare("
                INSERT INTO notifications (
                    recipient_id, created_by, title, message, is_read, created_at
                ) VALUES (?, ?, ?, ?, 0, NOW())
            ");
            
            // Get sender name
            $sender_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
            $sender_stmt->execute([$_SESSION['user_id']]);
            $sender = $sender_stmt->fetch();
            $sender_name = $sender['first_name'] . ' ' . $sender['last_name'];
            
            $notification_title = 'New Message';
            $notification_message = "New message from {$sender_name}: " . (strlen($message) > 30 ? substr($message, 0, 30) . '...' : $message);
            
            $stmt->execute([$receiver_id, $_SESSION['user_id'], $notification_title, $notification_message]);
            
            // Return success response
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'Message sent successfully',
                'data' => [
                    'id' => $message_id,
                    'sender_id' => $_SESSION['user_id'],
                    'receiver_id' => $receiver_id,
                    'message' => $message,
                    'created_at' => $created_at
                ]
            ]);
            exit;
        } else {
            throw new Exception("Failed to send message");
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}
?>