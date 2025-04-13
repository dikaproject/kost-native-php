<?php
$page_title = "Book Room";

// Get room ID from URL
$room_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if user already has an active room
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tenants 
    WHERE user_id = ? AND status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$has_active_room = ($stmt->fetchColumn() > 0);

// If user already has a room, redirect to dashboard
if ($has_active_room) {
    $_SESSION['error_message'] = "You already have an active room. You cannot book another room.";
    header("Location: index.php?page=dashboard");
    exit;
}

// Get room details
$stmt = $pdo->prepare("
    SELECT r.* 
    FROM rooms r
    WHERE r.id = ? AND r.status = 'available'
");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

// If room not found or not available, redirect to rooms page
if (!$room) {
    $_SESSION['error_message'] = "Room not available for booking.";
    header("Location: index.php?page=rooms");
    exit;
}

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    // Create tenant record with today's date as start date
    $start_date = date('Y-m-d');
    $stmt = $pdo->prepare("
        INSERT INTO tenants (user_id, room_id, start_date, status)
        VALUES (?, ?, ?, 'active')
    ");
    $stmt->execute([$_SESSION['user_id'], $room_id, $start_date]);
    $tenant_id = $pdo->lastInsertId();
    
    // Update room status
    $stmt = $pdo->prepare("
        UPDATE rooms SET status = 'occupied' WHERE id = ?
    ");
    $stmt->execute([$room_id]);
    
    // Update user's room_id in the users table
    $stmt = $pdo->prepare("
        UPDATE users SET room_id = ? WHERE id = ?
    ");
    $stmt->execute([$room_id, $_SESSION['user_id']]);
    
    // Create payment record with default payment method (bank_transfer)
    $stmt = $pdo->prepare("
        INSERT INTO payments (tenant_id, amount, payment_date, payment_method, status)
        VALUES (?, ?, NOW(), 'transfer', 'pending')
    ");
    $stmt->execute([$tenant_id, $room['price']]);
    
    // Check the structure of the notifications table
    $stmt = $pdo->query("DESCRIBE notifications");
    $notifColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Determine the correct column names
    $recipientField = in_array('recipient_id', $notifColumns) ? 'recipient_id' : 
                     (in_array('user_id', $notifColumns) ? 'user_id' : 'recipient_id');
    $senderField = in_array('sender_id', $notifColumns) ? 'sender_id' : 
                  (in_array('created_by', $notifColumns) ? 'created_by' : 'sender_id');
    $messageField = in_array('message', $notifColumns) ? 'message' : 
                   (in_array('content', $notifColumns) ? 'content' : 'message');
    $readField = in_array('is_read', $notifColumns) ? 'is_read' : 
                (in_array('read', $notifColumns) ? '`read`' : 'is_read');

    // Create notification for admin
    $adminNotifSQL = "INSERT INTO notifications ($recipientField, $senderField, $messageField, $readField) VALUES (1, ?, ?, 0)";
    $message = "New booking: User ID {$_SESSION['user_id']} has booked room {$room['name']}";
    $stmt = $pdo->prepare($adminNotifSQL);
    $stmt->execute([$_SESSION['user_id'], $message]);

    // Create notification for user
    $userNotifSQL = "INSERT INTO notifications ($recipientField, $senderField, $messageField, $readField) VALUES (?, 1, ?, 0)";
    $message = "Your booking for room {$room['name']} has been confirmed. Please complete your payment.";
    $stmt = $pdo->prepare($userNotifSQL);
    $stmt->execute([$_SESSION['user_id'], $message]);
    
    // Commit transaction
    $pdo->commit();
    
    // Set success message and redirect to payments page
    $_SESSION['success_message'] = "Room booked successfully! Please complete your payment.";
    header("Location: index.php?page=payments");
    exit;
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    // Set error message and redirect
    $_SESSION['error_message'] = "An error occurred during booking: " . $e->getMessage();
    header("Location: index.php?page=rooms");
    exit;
}
?>

<!-- This page doesn't have any HTML content as it's a processing page -->

