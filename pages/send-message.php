<?php
// This is a processing page, not a display page
// It handles message sending and redirects back to the chat page

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
 header("Location: index.php?page=chat");
 exit;
}

// Get form data
$receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Validate data
if (empty($receiver_id) || empty($message)) {
 $_SESSION['error_message'] = "Invalid message data";
 header("Location: index.php?page=chat");
 exit;
}

// Insert message into database
$stmt = $pdo->prepare("
 INSERT INTO messages (sender_id, receiver_id, message, is_read, created_at)
 VALUES (?, ?, ?, 0, NOW())
");

try {
   if ($stmt->execute([$_SESSION['user_id'], $receiver_id, $message])) {
       // Create notification for receiver
       $stmt = $pdo->prepare("
           INSERT INTO notifications (
               recipient_id, created_by, title, message, is_read, created_at
           ) VALUES (
               ?, ?, ?, ?, 0, NOW()
           )
       ");
       
       // Get sender name
       $stmt_user = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
       $stmt_user->execute([$_SESSION['user_id']]);
       $sender = $stmt_user->fetch();
       $sender_name = $sender['first_name'] . ' ' . $sender['last_name'];
       
       $notification_title = "New Message";
       $notification_message = "New message from {$sender_name}: " . substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '');
       $stmt->execute([$receiver_id, $_SESSION['user_id'], $notification_title, $notification_message]);
       
       // Success
       $_SESSION['success_message'] = "Message sent successfully";
   } else {
       // Error
       $_SESSION['error_message'] = "Failed to send message";
   }
} catch (Exception $e) {
   // Log the error
   error_log("Error sending message: " . $e->getMessage());
   $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
}

// If this is an AJAX request, return a JSON response
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
 header('Content-Type: application/json');
 echo json_encode([
     'success' => !isset($_SESSION['error_message']),
     'message' => isset($_SESSION['error_message']) ? $_SESSION['error_message'] : $_SESSION['success_message']
 ]);
 exit;
}

// Redirect back to chat page
header("Location: index.php?page=chat&user={$receiver_id}");
exit;
?>

