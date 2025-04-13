<?php
// This file should be placed in the root directory of your project
// It handles Midtrans notifications/webhooks

// Start session
session_start();

// Database connection
require_once 'config/database.php';
require_once 'config/midtrans.php';

// Get the raw post data from Midtrans
$notification = json_decode(file_get_contents('php://input'), true);
$transaction_status = $notification['transaction_status'] ?? '';
$order_id = $notification['order_id'] ?? '';
$transaction_id = $notification['transaction_id'] ?? '';
$payment_type = $notification['payment_type'] ?? '';
$transaction_time = $notification['transaction_time'] ?? null;

// Create a log file for debugging
$log_file = 'midtrans_notification.log';
$log_data = date('Y-m-d H:i:s') . " - Notification received for order_id: $order_id, status: $transaction_status\n";
file_put_contents($log_file, $log_data, FILE_APPEND);

// Verify the notification by checking status directly from Midtrans
$status_response = check_transaction_status($order_id);

// If verification fails, stop processing
if (isset($status_response['error']) || $status_response['transaction_status'] !== $transaction_status) {
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Verification failed\n", FILE_APPEND);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Verification failed']);
    exit;
}

// Process the notification
try {
    // Get payment info from database
    $stmt = $pdo->prepare("
        SELECT p.*, t.user_id, t.room_id, r.name as room_name
        FROM payments p
        JOIN tenants t ON p.tenant_id = t.id
        JOIN rooms r ON t.room_id = r.id
        WHERE p.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        file_put_contents($log_file, date('Y-m-d H:i:s') . " - Payment not found for order_id: $order_id\n", FILE_APPEND);
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Payment not found']);
        exit;
    }

    $pdo->beginTransaction();
    
    // Map Midtrans status to our payment status
    $payment_status = 'pending';
    if ($transaction_status === 'settlement' || $transaction_status === 'capture') {
        $payment_status = 'paid';
    } elseif ($transaction_status === 'cancel' || $transaction_status === 'deny' || $transaction_status === 'expire') {
        $payment_status = 'unpaid';
    }
    
    $transaction_time_formatted = !empty($transaction_time) ? date('Y-m-d H:i:s', strtotime($transaction_time)) : null;
    
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
    
    $stmt->execute([
        $transaction_id,
        $payment_type,
        $transaction_time_formatted,
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
        $message = "Payment received: User ID {$payment['user_id']} has paid for room {$payment['room_name']}";
        $stmt->execute([$payment['user_id'], $message]);
        
        // Create notification for user
        $stmt = $pdo->prepare("
            INSERT INTO notifications (recipient_id, created_by, message, is_read, created_at)
            VALUES (?, 1, ?, 0, NOW())
        ");
        $message = "Your payment for room {$payment['room_name']} has been successfully processed. Thank you!";
        $stmt->execute([$payment['user_id'], $message]);
    }
    
    $pdo->commit();
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Payment updated successfully, status: $payment_status\n", FILE_APPEND);
    
    // Return success response
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Notification processed']);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
    
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}