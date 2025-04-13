<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'chat_error.log');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

// Database connection - Use absolute path
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'User not logged in']);
  exit;
}

// Get active user ID
$active_user_id = isset($_GET['user']) ? intval($_GET['user']) : 0;

if (empty($active_user_id)) {
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'No active user specified']);
  exit;
}

try {
  // Mark messages as read
  $stmt = $pdo->prepare("
      UPDATE messages 
      SET is_read = 1 
      WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
  ");

  $result = $stmt->execute([$active_user_id, $_SESSION['user_id']]);

  // Send response
  header('Content-Type: application/json');
  echo json_encode([
      'success' => $result,
      'message' => $result ? 'Messages marked as read' : 'Failed to mark messages as read'
  ]);
} catch (PDOException $e) {
  // Log error
  error_log("Database error: " . $e->getMessage());
  
  // Error response
  header('Content-Type: application/json');
  echo json_encode([
      'success' => false,
      'error' => 'Database error: ' . $e->getMessage()
  ]);
}
exit;
?>

