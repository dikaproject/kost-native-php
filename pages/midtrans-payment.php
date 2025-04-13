<?php
// Include Midtrans configuration
require_once 'config/midtrans.php';

$page_title = "Payment";

// Debug variables to help diagnose issues
$debug_info = [];
$debug_info['session'] = [
    'has_token' => isset($_SESSION['midtrans_token']),
    'has_payment_id' => isset($_SESSION['payment_id']),
    'has_order_id' => isset($_SESSION['order_id']),
];

// Check if token exists in session
if (!isset($_SESSION['midtrans_token']) || empty($_SESSION['midtrans_token']) || 
    !isset($_SESSION['payment_id']) || empty($_SESSION['payment_id']) ||
    !isset($_SESSION['order_id']) || empty($_SESSION['order_id'])) {
    
    $debug_info['recovery_attempt'] = true;
    
    // Check if we can recover the payment info from the database
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
        // Try to find the most recent pending Midtrans payment for this user
        $stmt = $pdo->prepare("
            SELECT p.*, t.user_id, r.name as room_name 
            FROM payments p
            JOIN tenants t ON p.tenant_id = t.id
            JOIN rooms r ON t.room_id = r.id
            WHERE t.user_id = ? 
            AND p.payment_method = 'midtrans'
            AND (p.status = 'pending' OR p.status = 'unpaid')
            AND p.midtrans_token IS NOT NULL
            ORDER BY p.payment_date DESC
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $payment = $stmt->fetch();
        
        $debug_info['recovery_result'] = [
            'found_payment' => ($payment !== false),
        ];
        
        if ($payment) {
            // Restore session variables
            $_SESSION['midtrans_token'] = $payment['midtrans_token'];
            $_SESSION['payment_id'] = $payment['id'];
            $_SESSION['order_id'] = $payment['order_id'];
            $_SESSION['room_name'] = $payment['room_name'];
            $_SESSION['amount'] = $payment['amount'];
            
            $debug_info['recovery_result']['session_restored'] = true;
        } else {
            // Could not recover payment info
            $_SESSION['error_message'] = "Payment session expired or invalid. Please try again.";
            header("Location: index.php?page=payments");
            exit;
        }
    } else {
        // No user ID in session
        $_SESSION['error_message'] = "Payment session expired or invalid. Please log in and try again.";
        header("Location: index.php?page=payments");
        exit;
    }
}

// Get payment info from session
$token = $_SESSION['midtrans_token'];
$payment_id = $_SESSION['payment_id'];
$order_id = $_SESSION['order_id'];
$room_name = $_SESSION['room_name'];
$amount = $_SESSION['amount'];

$debug_info['payment_info'] = [
    'token_length' => strlen($token),
    'payment_id' => $payment_id,
    'order_id' => $order_id,
];

// Check if payment has been processed in case user refreshes the page
$stmt = $pdo->prepare("
    SELECT transaction_status, status FROM payments 
    WHERE order_id = ? AND id = ?
");
$stmt->execute([$order_id, $payment_id]);
$payment_data = $stmt->fetch();

$debug_info['payment_status'] = $payment_data;

// If payment is already completed, redirect to payment success page
if (isset($payment_data['transaction_status']) && 
    ($payment_data['transaction_status'] === 'settlement' || $payment_data['transaction_status'] === 'capture')) {
    
    // Update status to paid if not already
    if ($payment_data['status'] !== 'paid') {
        $stmt = $pdo->prepare("UPDATE payments SET status = 'paid' WHERE id = ?");
        $stmt->execute([$payment_id]);
    }
    
    unset($_SESSION['midtrans_token']);
    unset($_SESSION['payment_id']);
    unset($_SESSION['order_id']);
    unset($_SESSION['room_name']);
    unset($_SESSION['amount']);
    
    $_SESSION['success_message'] = "Payment completed successfully!";
    header("Location: index.php?page=payments");
    exit;
}

// If payment is expired or failed, redirect back to payments page
if (isset($payment_data['transaction_status']) && 
    in_array($payment_data['transaction_status'], ['expire', 'failure', 'deny', 'cancel'])) {
    
    unset($_SESSION['midtrans_token']);
    unset($_SESSION['payment_id']);
    unset($_SESSION['order_id']);
    unset($_SESSION['room_name']);
    unset($_SESSION['amount']);
    
    $_SESSION['error_message'] = "Payment was " . $payment_data['transaction_status'] . ". Please try again.";
    header("Location: index.php?page=payments");
    exit;
}

// Check payment status from Midtrans API
if (!empty($order_id)) {
    $transaction = check_transaction_status($order_id);
    
    if (!isset($transaction['error']) && isset($transaction['transaction_status'])) {
        $transaction_status = $transaction['transaction_status'];
        
        // Update payment status in the database
        $payment_status = 'pending';
        
        if ($transaction_status === 'settlement' || $transaction_status === 'capture') {
            $payment_status = 'paid';
            
            // Redirect to success page
            unset($_SESSION['midtrans_token']);
            unset($_SESSION['payment_id']);
            unset($_SESSION['order_id']);
            unset($_SESSION['room_name']);
            unset($_SESSION['amount']);
            
            $_SESSION['success_message'] = "Payment completed successfully!";
            header("Location: index.php?page=payments");
            exit;
        } else if ($transaction_status === 'expire' || $transaction_status === 'failure' || 
                  $transaction_status === 'deny' || $transaction_status === 'cancel') {
            
            // Update status
            $stmt = $pdo->prepare("
                UPDATE payments 
                SET transaction_status = ?, status = 'expired'
                WHERE id = ?
            ");
            $stmt->execute([$transaction_status, $payment_id]);
            
            // Redirect to payments page
            unset($_SESSION['midtrans_token']);
            unset($_SESSION['payment_id']);
            unset($_SESSION['order_id']);
            unset($_SESSION['room_name']);
            unset($_SESSION['amount']);
            
            $_SESSION['error_message'] = "Payment was " . $transaction_status . ". Please try again.";
            header("Location: index.php?page=payments");
            exit;
        }
        
        // Update the database with the latest status
        $stmt = $pdo->prepare("
            UPDATE payments 
            SET transaction_status = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([$transaction_status, $payment_status, $payment_id]);
    }
}

// For debugging purposes - uncomment to see debug info
// echo '<pre>' . print_r($debug_info, true) . '</pre>';
?>

<div class="payment-page">
    <div class="payment-container">
        <div class="payment-header">
            <h1>Complete Your Payment</h1>
            <p>Please complete your payment to finalize your room booking.</p>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="payment-details">
            <div class="detail-row">
                <span class="detail-label">Room:</span>
                <span class="detail-value"><?php echo htmlspecialchars($room_name); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Amount:</span>
                <span class="detail-value">IDR <?php echo number_format($amount, 0, ',', '.'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Order ID:</span>
                <span class="detail-value"><?php echo htmlspecialchars($order_id); ?></span>
            </div>
        </div>
        
        <div class="payment-actions">
            <button id="pay-button" class="btn-pay">Pay Now</button>
            <a href="index.php?page=payments" class="btn-cancel">Cancel</a>
        </div>
    </div>
</div>

<style>
    .payment-page {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 70vh;
    }
    
    .payment-container {
        width: 100%;
        max-width: 600px;
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-md);
        border: 1px solid var(--border-color);
    }
    
    .payment-header {
        padding: 24px;
        border-bottom: 1px solid var(--border-color);
        text-align: center;
    }
    
    .payment-header h1 {
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .payment-header p {
        color: var(--text-secondary);
    }
    
    .payment-details {
        padding: 24px;
    }
    
    .detail-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .detail-row:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    
    .detail-label {
        color: var(--text-secondary);
        font-weight: 500;
    }
    
    .detail-value {
        font-weight: 600;
    }
    
    .payment-actions {
        padding: 24px;
        display: flex;
        gap: 16px;
    }
    
    .btn-pay {
        flex: 1;
        padding: 14px;
        border: none;
        border-radius: var(--border-radius-md);
        background-color: var(--accent-color);
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
    }
    
    .btn-pay:hover {
        opacity: 0.9;
        transform: translateY(-2px);
    }
    
    .btn-cancel {
        flex: 1;
        padding: 14px;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-md);
        background-color: transparent;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        text-align: center;
        text-decoration: none;
        color: var(--text-primary);
    }
    
    .btn-cancel:hover {
        background-color: var(--hover-color);
    }
    
    .alert {
        padding: 16px;
        border-radius: var(--border-radius-md);
        margin: 24px;
    }

    .alert-danger {
        background-color: rgba(211, 47, 47, 0.1);
        color: #d32f2f;
        border: 1px solid rgba(211, 47, 47, 0.2);
    }
</style>

<!-- Include Midtrans JS library -->
<script src="<?php echo $midtrans_js_url; ?>" data-client-key="<?php echo $midtrans_client_key; ?>"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add Pay button click event
        document.getElementById('pay-button').addEventListener('click', function() {
            // Show Snap payment page
            snap.pay('<?php echo $token; ?>', {
                onSuccess: function(result) {
                    console.log('Success:', result);
                    window.location.href = 'index.php?page=midtrans-callback&status=success&order_id=<?php echo $order_id; ?>';
                },
                onPending: function(result) {
                    console.log('Pending:', result);
                    window.location.href = 'index.php?page=midtrans-callback&status=pending&order_id=<?php echo $order_id; ?>';
                },
                onError: function(result) {
                    console.log('Error:', result);
                    // Check the error code from Midtrans
                    if (result.status_code === "406") {
                        alert("Payment session has expired. You will be redirected to create a new payment.");
                        window.location.href = 'index.php?page=payments';
                    } else {
                        window.location.href = 'index.php?page=midtrans-callback&status=error&order_id=<?php echo $order_id; ?>';
                    }
                },
                onClose: function() {
                    console.log('Customer closed the payment window');
                    window.location.href = 'index.php?page=payments';
                }
            });
        });
        
        // Auto-trigger payment window on page load
        // Uncomment this if you want the payment window to open automatically
        /*
        setTimeout(function() {
            document.getElementById('pay-button').click();
        }, 1000);
        */
    });
</script>