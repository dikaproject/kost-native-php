<?php
// Include Midtrans configuration
require_once 'config/midtrans.php';

$page_title = "Payment";

// Check if token exists in session
if (!isset($_SESSION['midtrans_token']) || empty($_SESSION['midtrans_token'])) {
    $_SESSION['error_message'] = "Payment session expired or invalid. Please try booking again.";
    header("Location: index.php?page=rooms");
    exit;
}

// Get payment info from session
$token = $_SESSION['midtrans_token'];
$payment_id = $_SESSION['payment_id'];
$order_id = $_SESSION['order_id'];
$room_name = $_SESSION['room_name'];
$amount = $_SESSION['amount'];

// Check if payment has been processed in case user refreshes the page
$stmt = $pdo->prepare("
    SELECT transaction_status FROM payments 
    WHERE order_id = ? AND id = ?
");
$stmt->execute([$order_id, $payment_id]);
$payment_status = $stmt->fetchColumn();

// If payment is already completed, redirect to payment success page
if ($payment_status === 'settlement' || $payment_status === 'capture') {
    unset($_SESSION['midtrans_token']);
    unset($_SESSION['payment_id']);
    unset($_SESSION['order_id']);
    unset($_SESSION['room_name']);
    unset($_SESSION['amount']);
    
    $_SESSION['success_message'] = "Payment completed successfully!";
    header("Location: index.php?page=payments");
    exit;
}
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
                <span class="detail-value"><?php echo $room_name; ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Amount:</span>
                <span class="detail-value">IDR <?php echo number_format($amount, 0, ',', '.'); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Order ID:</span>
                <span class="detail-value"><?php echo $order_id; ?></span>
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
                    /* You can add code here to handle the success payment */
                    window.location.href = 'index.php?page=midtrans-callback&status=success&order_id=<?php echo $order_id; ?>';
                },
                onPending: function(result) {
                    /* You can add code here to handle the pending payment */
                    window.location.href = 'index.php?page=midtrans-callback&status=pending&order_id=<?php echo $order_id; ?>';
                },
                onError: function(result) {
                    /* You can add code here to handle the error payment */
                    window.location.href = 'index.php?page=midtrans-callback&status=error&order_id=<?php echo $order_id; ?>';
                },
                onClose: function() {
                    /* You can add code here to handle the customer closed the popup without finishing the payment */
                    console.log('Customer closed the payment window');
                }
            });
        });
    });
</script>