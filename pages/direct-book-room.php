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
    
    // Get current user's name for notification
    $stmt = $pdo->prepare("
        SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user_name = $stmt->fetchColumn();
    
    // Create notification message
    $message = $user_name . " has booked room " . $room['name'] . " (Room #" . $room_id . ")";
    
    // Find admin users to notify instead of assuming ID 1
    $adminStmt = $pdo->prepare("
        SELECT id FROM users WHERE role = 'admin'
    ");
    $adminStmt->execute();
    $adminUsers = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Insert notifications for each admin user
    if (!empty($adminUsers)) {
        $notificationStmt = $pdo->prepare("
            INSERT INTO notifications (recipient_id, created_by, message, is_read, created_at)
            VALUES (?, ?, ?, 0, NOW())
        ");
        
        foreach ($adminUsers as $adminId) {
            // Verify the admin user still exists in the database (double-check)
            $verifyStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $verifyStmt->execute([$adminId]);
            if ($verifyStmt->fetchColumn()) {
                $notificationStmt->execute([$adminId, $_SESSION['user_id'], $message]);
            }
        }
    }
    
    // Create Midtrans transaction
    $transaction_details = array(
        'order_id' => $order_id,
        'gross_amount' => $room['price']
    );
    
    $customer_details = array(
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'email' => $user['email'],
        'phone' => $user['phone'] ?? ''
    );
    
    $item_details = array(
        array(
            'id' => 'ROOM' . $room_id,
            'price' => $room['price'],
            'quantity' => 1,
            'name' => 'Room ' . $room['name'] . ' - Monthly Rent'
        )
    );
    
    $transaction_data = array(
        'transaction_details' => $transaction_details,
        'customer_details' => $customer_details,
        'item_details' => $item_details
    );
    
    // Create Midtrans Token
    $midtrans_token = get_midtrans_token($transaction_data);
    
    // Store token
    $stmt = $pdo->prepare("
        UPDATE payments SET midtrans_token = ? WHERE id = ?
    ");
    $stmt->execute([$midtrans_token, $payment_id]);
    
    // Commit transaction
    $pdo->commit();
    
    // Store data in session for midtrans-payment.php
    $_SESSION['midtrans_token'] = $midtrans_token;
    $_SESSION['payment_id'] = $payment_id;
    $_SESSION['order_id'] = $order_id;
    $_SESSION['room_name'] = $room['name'];
    $_SESSION['amount'] = $room['price'];
    
    // Redirect to payment page
    header("Location: index.php?page=midtrans-payment");
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    $_SESSION['error_message'] = "An error occurred during booking: " . $e->getMessage();
    header("Location: index.php?page=room-detail&id=" . $room_id);
    exit;
}
?>