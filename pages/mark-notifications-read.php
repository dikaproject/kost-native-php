<?php
// This file handles AJAX requests to mark notifications as read

// Check if this is an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    // Get user ID from POST data
    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    
    // Validate user ID
    if ($user_id === 0 || $user_id !== $_SESSION['user_id']) {
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit;
    }
    
    try {
        // Mark all notifications as read
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
        exit;
    } catch (Exception $e) {
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// If not an AJAX request, redirect to notifications page
header('Location: index.php?page=notifications');
exit;
?>

