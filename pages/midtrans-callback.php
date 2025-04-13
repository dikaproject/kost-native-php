<?php
// Include Midtrans configuration
require_once 'config/midtrans.php';

$page_title = "Payment Confirmation";

// Get status and order ID from URL
$status = isset($_GET['status']) ? $_GET['status'] : '';
$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : '';

if (empty($order_id)) {
    $_SESSION['error_message'] = "Invalid payment reference.";
    header("Location: index.php?page=payments");
    exit;
}

// Check transaction status from Midtrans
$transaction = check_transaction_status($order_id);

if (isset($transaction['error'])) {
    $_SESSION['error_message'] = "Error checking payment status: " . $transaction['error'];
    header("Location: index.php?page=payments");
    exit;
}

// Get payment info from database
$stmt = $pdo->prepare("
    SELECT p.*, t.room_id, r.name as room_name
    FROM payments p
    JOIN tenants t ON p.tenant_id = t.id
    JOIN rooms r ON t.room_id = r.id
    WHERE p.order_id = ?
");
$stmt->execute([$order_id]);
$payment = $stmt->fetch();

if (!$payment) {
    $_SESSION['error_message'] = "Payment not found.";
    header("Location: index.php?page=payments");
    exit;
}

// Update payment status based on Midtrans response
try {
    $pdo->beginTransaction();
    
    $transaction_status = $transaction['transaction_status'] ?? 'pending';
    $payment_type = $transaction['payment_type'] ?? null;
    $transaction_id = $transaction['transaction_id'] ?? null;
    $transaction_time = $transaction['transaction_time'] ?? null;
    
    // Update payment record
    $stmt = $pdo->prepare("
        UPDATE payments 
        SET 
            transaction_id = ?,
            payment_type = ?,
            transaction_time = ?,
            transaction_status = ?,
            status = ?
        WHERE order_id = ?
    ");
    
    // Map Midtrans status to our payment status
    $payment_status = 'unpaid';
if ($transaction_status === 'settlement' || $transaction_status === 'capture') {
    $payment_status = 'paid';
}
    
    $transaction_time = !empty($transaction_time) ? date('Y-m-d H:i:s', strtotime($transaction_time)) : null;
    
    $stmt->execute([
        $transaction_id,
        $payment_type,
        $transaction_time,
        $transaction_status,
        $payment_status,
        $order_id
    ]);
    
    // If payment is successful, update notifications
    if ($payment_status === 'paid') {
        // Create notification for admin
        $stmt = $pdo->prepare("
            INSERT INTO notifications (recipient_id, created_by, message, is_read, created_at)
            VALUES (1, ?, ?, 0, NOW())
        ");
        $message = "Payment received: User ID {$_SESSION['user_id']} has paid for room {$payment['room_name']}";
        $stmt->execute([$_SESSION['user_id'], $message]);
        
        // Create notification for user
        $stmt = $pdo->prepare("
            INSERT INTO notifications (recipient_id, created_by, message, is_read, created_at)
            VALUES (?, 1, ?, 0, NOW())
        ");
        $message = "Your payment for room {$payment['room_name']} has been successfully processed. Thank you!";
        $stmt->execute([$_SESSION['user_id'], $message]);
    }
    
    $pdo->commit();
    
    // Clean up session
    unset($_SESSION['midtrans_token']);
    unset($_SESSION['payment_id']);
    unset($_SESSION['order_id']);
    unset($_SESSION['room_name']);
    unset($_SESSION['amount']);
    
    // Set message based on payment status
    if ($payment_status === 'paid') {
        $_SESSION['success_message'] = "Payment successful! Your room has been confirmed.";
    } elseif ($transaction_status === 'pending') {
        $_SESSION['info_message'] = "Your payment is being processed. Please check again later.";
    } else {
        $_SESSION['error_message'] = "Payment failed. Status: " . ucfirst($transaction_status);
    }
    
    // Redirect to payments page
    header("Location: index.php?page=payments");
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
    header("Location: index.php?page=payments");
    exit;
}
?>