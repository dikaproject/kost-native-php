<?php
$page_title = "Book Room";

// Include Midtrans configuration
require_once 'config/midtrans.php';

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

// Get user details for Midtrans
$stmt = $pdo->prepare("
    SELECT * FROM users WHERE id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

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
    
    // Generate unique order ID for Midtrans
    $order_id = 'ROOM-' . $room_id . '-' . time();
    
    // Create payment record with Midtrans as payment method and add order_id
    $stmt = $pdo->prepare("
    INSERT INTO payments (tenant_id, amount, payment_date, payment_method, status, order_id)
    VALUES (?, ?, NOW(), 'midtrans', 'unpaid', ?)
");
    $stmt->execute([$tenant_id, $room['price'], $order_id]);
    $payment_id = $pdo->lastInsertId();
    
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
    
    // Prepare Midtrans transaction parameters
    $transaction_details = [
        'order_id' => $order_id,
        'gross_amount' => (int)$room['price'],
    ];
    
    $customer_details = [
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
    ];
    
    $item_details = [
        [
            'id' => 'ROOM-' . $room_id,
            'price' => (int)$room['price'],
            'quantity' => 1,
            'name' => $room['name'] . ' - Monthly Rent',
        ]
    ];
    
    $transaction_data = [
        'transaction_details' => $transaction_details,
        'customer_details' => $customer_details,
        'item_details' => $item_details,
    ];
    
    // Create Midtrans transaction
    $midtrans_response = create_midtrans_transaction($transaction_data);
    
    if (isset($midtrans_response['error'])) {
        // Error creating Midtrans transaction
        throw new Exception("Midtrans error: " . $midtrans_response['error']);
    }
    
    // Store Midtrans token in session to use on the payment page
    $_SESSION['midtrans_token'] = $midtrans_response['token'];
    $_SESSION['payment_id'] = $payment_id;
    $_SESSION['order_id'] = $order_id;
    $_SESSION['room_name'] = $room['name'];
    $_SESSION['amount'] = $room['price'];
    
    // Redirect to Midtrans payment page
    header("Location: index.php?page=midtrans-payment");
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