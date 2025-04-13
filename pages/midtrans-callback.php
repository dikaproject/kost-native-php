<?php
// Midtrans callback handler

// Include Midtrans configuration
require_once 'config/midtrans.php';

$page_title = "Payment Callback";

// Initialize variables
$transaction_status = '';
$order_id = '';
$transaction_id = '';
$status_code = '';
$payment_type = '';
$amount = 0;
$tenant_id = 0;
$user_id = 0;
$message = '';

// Handle check payment status from URL
if (isset($_GET['status']) && $_GET['status'] == 'check' && isset($_GET['order_id'])) {
    $order_id = $_GET['order_id'];
    $transaction = check_transaction_status($order_id);
    
    if (!isset($transaction['error'])) {
        $transaction_status = $transaction['transaction_status'] ?? '';
        $transaction_id = $transaction['transaction_id'] ?? '';
        $status_code = $transaction['status_code'] ?? '';
        $payment_type = $transaction['payment_type'] ?? '';
        $amount = $transaction['gross_amount'] ?? 0;
        
        // Get payment and tenant information
        $stmt = $pdo->prepare("
            SELECT p.id, p.tenant_id, t.user_id, t.room_id
            FROM payments p
            JOIN tenants t ON p.tenant_id = t.id
            WHERE p.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $payment_data = $stmt->fetch();
        
        if ($payment_data) {
            $tenant_id = $payment_data['tenant_id'];
            $user_id = $payment_data['user_id'];
            $payment_id = $payment_data['id'];
            
            // Update payment status in database
            $payment_status = 'unpaid';
            if ($transaction_status == 'settlement' || $transaction_status == 'capture') {
                $payment_status = 'paid';
            } else if ($transaction_status == 'expire' || $transaction_status == 'failure' || $transaction_status == 'deny' || $transaction_status == 'cancel') {
                $payment_status = 'expired';
            }
            
            // Update payment record
            $stmt = $pdo->prepare("
                UPDATE payments 
                SET transaction_id = ?, 
                    transaction_status = ?, 
                    payment_type = ?, 
                    status = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$transaction_id, $transaction_status, $payment_type, $payment_status, $payment_id]);
            
            // If payment is successful, notify admin users
            if ($payment_status == 'paid') {
                // Get current user's name for the notification
                $stmt = $pdo->prepare("
                    SELECT CONCAT(first_name, ' ', last_name) as full_name 
                    FROM users 
                    WHERE id = ?
                ");
                $stmt->execute([$user_id]);
                $user_name = $stmt->fetchColumn();
                
                // Create notification message
                $message = $user_name . " has completed payment for " . $amount . " IDR";
                
                // Find admin users to notify
                $adminStmt = $pdo->prepare("
                    SELECT id FROM users WHERE role = 'admin'
                ");
                $adminStmt->execute();
                $adminUsers = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Insert notifications for each admin user (if any exist)
                if (!empty($adminUsers)) {
                    // Prepare notification insertion statement
                    $notificationStmt = $pdo->prepare("
                        INSERT INTO notifications (recipient_id, created_by, message, is_read, created_at)
                        VALUES (?, ?, ?, 0, NOW())
                    ");
                    
                    foreach ($adminUsers as $adminId) {
                        // Verify the admin user still exists before inserting
                        $verifyStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
                        $verifyStmt->execute([$adminId]);
                        if ($verifyStmt->fetchColumn() > 0) {
                            try {
                                $notificationStmt->execute([$adminId, $user_id, $message]);
                            } catch (Exception $e) {
                                // Log error but continue processing
                                error_log("Failed to create notification: " . $e->getMessage());
                            }
                        }
                    }
                } else {
                    // No admin users found - log this but don't fail the payment processing
                    error_log("No admin users found for payment notification");
                }
                
                // Set success message
                $_SESSION['success_message'] = "Payment completed successfully!";
            } else if ($transaction_status == 'pending') {
                $_SESSION['info_message'] = "Payment is still pending. Please complete your payment.";
            } else {
                $_SESSION['error_message'] = "Payment failed or expired.";
            }
        } else {
            $_SESSION['error_message'] = "Payment data not found.";
        }
    } else {
        $_SESSION['error_message'] = "Error checking payment status: " . ($transaction['error'] ?? 'Unknown error');
    }
    
    // Redirect to payments page
    header("Location: index.php?page=payments");
    exit;
}

// Handle direct notification from Midtrans
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notification = json_decode(file_get_contents('php://input'), true);
    
    if (isset($notification['order_id'])) {
        $order_id = $notification['order_id'];
        $transaction_status = $notification['transaction_status'] ?? '';
        $transaction_id = $notification['transaction_id'] ?? '';
        $status_code = $notification['status_code'] ?? '';
        $payment_type = $notification['payment_type'] ?? '';
        $amount = $notification['gross_amount'] ?? 0;
        
        // Get payment and tenant information
        $stmt = $pdo->prepare("
            SELECT p.id, p.tenant_id, t.user_id, t.room_id
            FROM payments p
            JOIN tenants t ON p.tenant_id = t.id
            WHERE p.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $payment_data = $stmt->fetch();
        
        if ($payment_data) {
            $tenant_id = $payment_data['tenant_id'];
            $user_id = $payment_data['user_id'];
            $payment_id = $payment_data['id'];
            
            // Update payment status in database
            $payment_status = 'unpaid';
            if ($transaction_status == 'settlement' || $transaction_status == 'capture') {
                $payment_status = 'paid';
            } else if ($transaction_status == 'expire' || $transaction_status == 'failure' || $transaction_status == 'deny' || $transaction_status == 'cancel') {
                $payment_status = 'expired';
            }
            
            // Update payment record
            $stmt = $pdo->prepare("
                UPDATE payments 
                SET transaction_id = ?, 
                    transaction_status = ?, 
                    payment_type = ?, 
                    status = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$transaction_id, $transaction_status, $payment_type, $payment_status, $payment_id]);
            
            // If payment is successful, notify admin users
            if ($payment_status == 'paid') {
                // Get current user's name for the notification
                $stmt = $pdo->prepare("
                    SELECT CONCAT(first_name, ' ', last_name) as full_name 
                    FROM users 
                    WHERE id = ?
                ");
                $stmt->execute([$user_id]);
                $user_name = $stmt->fetchColumn();
                
                // Create notification message
                $message = $user_name . " has completed payment for " . $amount . " IDR";
                
                // Find admin users to notify
                $adminStmt = $pdo->prepare("
                    SELECT id FROM users WHERE role = 'admin'
                ");
                $adminStmt->execute();
                $adminUsers = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Insert notifications for each admin user (if any exist)
                if (!empty($adminUsers)) {
                    // Prepare notification insertion statement
                    $notificationStmt = $pdo->prepare("
                        INSERT INTO notifications (recipient_id, created_by, message, is_read, created_at)
                        VALUES (?, ?, ?, 0, NOW())
                    ");
                    
                    foreach ($adminUsers as $adminId) {
                        // Verify the admin user still exists before inserting
                        $verifyStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
                        $verifyStmt->execute([$adminId]);
                        if ($verifyStmt->fetchColumn() > 0) {
                            try {
                                $notificationStmt->execute([$adminId, $user_id, $message]);
                            } catch (Exception $e) {
                                // Log error but continue processing
                                error_log("Failed to create notification: " . $e->getMessage());
                            }
                        }
                    }
                } else {
                    // No admin users found - log this but don't fail the payment processing
                    error_log("No admin users found for payment notification");
                }
            }
            
            // Success response for Midtrans
            http_response_code(200);
            echo json_encode(['status' => 'success']);
            exit;
        }
    }
    
    // Error response for Midtrans
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid notification data']);
    exit;
}

// Handle success, pending or error redirects from Midtrans
if (isset($_GET['status']) && isset($_GET['order_id'])) {
    $status = $_GET['status'];
    $order_id = $_GET['order_id'];
    
    if ($status == 'success') {
        $_SESSION['success_message'] = "Payment completed successfully!";
    } else if ($status == 'pending') {
        $_SESSION['info_message'] = "Payment is still pending. Please complete your payment.";
    } else if ($status == 'error') {
        $_SESSION['error_message'] = "Payment failed. Please try again.";
    }
    
    // Redirect to payments page
    header("Location: index.php?page=payments");
    exit;
}

// If no valid action, redirect to homepage
header("Location: index.php");
exit;
?>