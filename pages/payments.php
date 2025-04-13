<?php
// Add this at the very beginning of the file, before any other code
ob_start();

// Include Midtrans configuration
require_once 'config/midtrans.php';

$page_title = "Payments";

// Get tenant information
$stmt = $pdo->prepare("
  SELECT t.*, r.name as room_name, r.price 
  FROM tenants t 
  JOIN rooms r ON t.room_id = r.id 
  WHERE t.user_id = ? AND t.status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$tenant = $stmt->fetch();

// Get payment history
$payments = [];
if ($tenant) {
    $stmt = $pdo->prepare("
      SELECT * FROM payments 
      WHERE tenant_id = ?
      ORDER BY payment_date DESC
  ");
    $stmt->execute([$tenant['id']]);
    $payments = $stmt->fetchAll();
}

// Calculate due amount
$dueAmount = $tenant ? $tenant['price'] : 0;

// Check if there's a pending Midtrans payment
$has_pending_midtrans = false;
$pending_payment = null;
$already_paid_this_month = false;

if ($tenant) {
    // Check if there's a paid payment for this month
    $current_month_start = date('Y-m-01 00:00:00');
    $current_month_end = date('Y-m-t 23:59:59');

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM payments
        WHERE tenant_id = ? 
        AND status = 'paid' 
        AND payment_date BETWEEN ? AND ?
    ");
    $stmt->execute([$tenant['id'], $current_month_start, $current_month_end]);
    $already_paid_this_month = ($stmt->fetchColumn() > 0);

    // If not paid this month, check for pending payments
    if (!$already_paid_this_month) {
        // First check for any 'pending' status payments (not just 'unpaid')
        $stmt = $pdo->prepare("
            SELECT * FROM payments
            WHERE tenant_id = ? 
            AND (status = 'pending' OR status = 'unpaid')
            AND payment_method = 'midtrans' 
            ORDER BY payment_date DESC
            LIMIT 1
        ");
        $stmt->execute([$tenant['id']]);
        $pending_payment = $stmt->fetch();
        
        // Only consider it pending if there's a valid record
        $has_pending_midtrans = ($pending_payment !== false);

        // If there's a pending payment, check its status from Midtrans
        if ($pending_payment) {
            // Check if this payment has a Midtrans token
            if (!empty($pending_payment['midtrans_token'])) {
                // Only check transaction status if we have an order_id
                if (!empty($pending_payment['order_id'])) {
                    $transaction = check_transaction_status($pending_payment['order_id']);
                    
                    // If we get a valid response from Midtrans
                    if (!isset($transaction['error'])) {
                        $transaction_status = $transaction['transaction_status'] ?? '';
                        $fraud_status = $transaction['fraud_status'] ?? '';
                        $payment_type = $transaction['payment_type'] ?? '';
                        
                        // Update the payment type in database if available
                        if (!empty($payment_type) && $payment_type != $pending_payment['payment_type']) {
                            $stmt = $pdo->prepare("
                                UPDATE payments SET payment_type = ? WHERE id = ?
                            ");
                            $stmt->execute([$payment_type, $pending_payment['id']]);
                        }
                        
                        // Determine payment status based on Midtrans response
                        $payment_status = 'pending'; // Default to pending
                        
                        if ($transaction_status === 'settlement' || 
                            ($transaction_status === 'capture' && $fraud_status === 'accept')) {
                            $payment_status = 'paid';
                            $already_paid_this_month = true;
                        } else if ($transaction_status === 'pending') {
                            $payment_status = 'pending';
                        } else if ($transaction_status === 'expire' || 
                                  $transaction_status === 'failure' || 
                                  $transaction_status === 'deny' || 
                                  $transaction_status === 'cancel' ||
                                  ($transaction_status === 'capture' && $fraud_status === 'challenge')) {
                            // Allow creating a new transaction for expired/failed/denied/canceled ones
                            $has_pending_midtrans = false;
                            $payment_status = 'expired';
                        }
                        
                        // Update payment record with latest status
                        $stmt = $pdo->prepare("
                            UPDATE payments 
                            SET transaction_status = ?, status = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$transaction_status, $payment_status, $pending_payment['id']]);
                        
                        // Refresh payment data after update
                        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
                        $stmt->execute([$pending_payment['id']]);
                        $pending_payment = $stmt->fetch();
                    } else {
                        // If Midtrans API error, just use existing status
                        // Don't mark as expired automatically as the payment might still be valid
                    }
                }
            } else {
                // If no midtrans_token, this payment might be corrupted
                // Mark as expired so user can create a new one
                $stmt = $pdo->prepare("
                    UPDATE payments 
                    SET status = 'expired', transaction_status = 'expire'
                    WHERE id = ?
                ");
                $stmt->execute([$pending_payment['id']]);
                
                $has_pending_midtrans = false;
            }
        }
    }
}

// Process payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $payment_method = $_POST['payment_method'] ?? '';
    $amount = $tenant ? $tenant['price'] : 0;

    // Validate required fields
    $errors = [];
    if (empty($payment_method)) {
        $errors[] = "Payment method is required";
    }

    // Validate payment method is one of the allowed values
    if (!in_array($payment_method, ['cash', 'transfer', 'qris', 'midtrans'])) {
        $errors[] = "Invalid payment method";
    }

    // If no errors, process payment
    if (empty($errors) && $tenant) {
        try {
            // Begin transaction
            $pdo->beginTransaction();

            if ($payment_method === 'midtrans') {
                // Check if there's already a pending payment we can continue
                if ($has_pending_midtrans && !empty($pending_payment['midtrans_token'])) {
                    // We already have a pending payment, use its token
                    $_SESSION['midtrans_token'] = $pending_payment['midtrans_token'];
                    $_SESSION['payment_id'] = $pending_payment['id'];
                    $_SESSION['order_id'] = $pending_payment['order_id'];
                    $_SESSION['room_name'] = $tenant['room_name'];
                    $_SESSION['amount'] = $pending_payment['amount'];
                    
                    $pdo->commit();
                    header("Location: index.php?page=midtrans-payment");
                    exit;
                }
                
                // If we get here, we need to create a new payment
                // Generate unique order ID
                $order_id = 'ROOM-' . $tenant['room_id'] . '-' . time();

                // Create payment record with Midtrans as payment method
                $stmt = $pdo->prepare("
                    INSERT INTO payments (tenant_id, amount, payment_date, payment_method, status, order_id)
                    VALUES (?, ?, NOW(), 'midtrans', 'pending', ?)
                ");
                $stmt->execute([$tenant['id'], $amount, $order_id]);
                $payment_id = $pdo->lastInsertId();

                // Prepare Midtrans transaction parameters
                $transaction_details = [
                    'order_id' => $order_id,
                    'gross_amount' => (int)$amount,
                ];

                // Get user email or provide a valid default
                $user_email = isset($_SESSION['email']) && filter_var($_SESSION['email'], FILTER_VALIDATE_EMAIL) 
                              ? $_SESSION['email'] 
                              : 'customer_' . time() . '@example.com';
                
                $customer_details = [
                    'first_name' => $_SESSION['first_name'] ?? 'Customer',
                    'last_name' => $_SESSION['last_name'] ?? '',
                    'email' => $user_email, // Use validated email or fallback
                    'phone' => $_SESSION['phone'] ?? '08123456789', // Provide a default phone if none exists
                ];

                $item_details = [
                    [
                        'id' => 'ROOM-' . $tenant['room_id'],
                        'price' => (int)$amount,
                        'quantity' => 1,
                        'name' => $tenant['room_name'] . ' - Monthly Rent',
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
                    throw new Exception("Midtrans error: " . $midtrans_response['error'] . 
                        (isset($midtrans_response['details']) ? " - Details: " . json_encode($midtrans_response['details']) : ""));
                }

                // Store Midtrans token
                $stmt = $pdo->prepare("
                    UPDATE payments SET midtrans_token = ? WHERE id = ?
                ");
                $stmt->execute([$midtrans_response['token'], $payment_id]);

                // Store token in session to use on the payment page
                $_SESSION['midtrans_token'] = $midtrans_response['token'];
                $_SESSION['payment_id'] = $payment_id;
                $_SESSION['order_id'] = $order_id;
                $_SESSION['room_name'] = $tenant['room_name'];
                $_SESSION['amount'] = $amount;

                // Commit transaction
                $pdo->commit();

                // Redirect to Midtrans payment page
                header("Location: index.php?page=midtrans-payment");
                exit;
            } else {
                // Process traditional payment methods
                $stmt = $pdo->prepare("
                    INSERT INTO payments (tenant_id, amount, payment_date, payment_method, status, created_at)
                    VALUES (?, ?, NOW(), ?, 'paid', NOW())
                ");
                $stmt->execute([$tenant['id'], $amount, $payment_method]);

                // Create notification for admin
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (recipient_id, created_by, message, is_read, created_at)
                    VALUES (1, ?, ?, 0, NOW())
                ");
                $message = "New payment: {$_SESSION['user_name']} has made a payment of IDR " . number_format($amount, 0, ',', '.');
                $stmt->execute([$_SESSION['user_id'], $message]);

                // Create notification for user
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (recipient_id, created_by, message, is_read, created_at)
                    VALUES (?, 1, ?, 0, NOW())
                ");
                $message = "Your payment of IDR " . number_format($amount, 0, ',', '.') . " has been successfully processed and marked as paid.";
                $stmt->execute([$_SESSION['user_id'], $message]);

                // Commit transaction
                $pdo->commit();

                // Set success message and redirect
                $_SESSION['success_message'] = "Payment submitted successfully! Your payment has been marked as paid.";
                header("Location: index.php?page=payments");
                exit;
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}
?>

<div class="page-content">
    <h1 class="page-title">Payments</h1>
    <p class="page-subtitle">Manage your monthly payments</p>

    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php
            echo $_SESSION['success_message'];
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php
            echo $_SESSION['error_message'];
            unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['info_message'])): ?>
        <div class="alert alert-info">
            <?php
            echo $_SESSION['info_message'];
            unset($_SESSION['info_message']);
            ?>
        </div>
    <?php endif; ?>

    <div class="payment-container">
        <!-- Payment Summary Card -->
        <?php if ($tenant): ?>
            <div class="card payment-summary-card">
                <div class="card-header">
                    <h2>Payment Summary</h2>
                </div>
                <div class="card-body">
                    <div class="summary-details">
                        <div class="summary-item">
                            <span class="item-label">Room</span>
                            <span class="item-value"><?php echo $tenant['room_name']; ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="item-label">Monthly Rent</span>
                            <span class="item-value">IDR <?php echo number_format($tenant['price'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="summary-item">
                            <span class="item-label">Due Date</span>
                            <span class="item-value"><?php echo date('d F Y', strtotime('last day of this month')); ?></span>
                        </div>
                    </div>

                    <?php if ($already_paid_this_month): ?>
                        <!-- Payment completed message -->
                        <div class="payment-status-info payment-paid">
                            <div class="status-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="status-text">
                                <h3>Payment Completed</h3>
                                <p>You have already paid for this month. Your next payment is due on <?php echo date('d F Y', strtotime('first day of next month')); ?>.</p>
                            </div>
                        </div>
                    <?php elseif ($has_pending_midtrans): ?>
                        <!-- Show pending Midtrans payment info -->
                        <div class="pending-payment-info">
                            <h3>Pending Payment</h3>
                            <p class="pending-message">You have a pending payment. Please complete your payment or check its status.</p>
                            <div class="pending-details">
                                <div class="pending-item">
                                    <span class="item-label">Amount:</span>
                                    <span class="item-value">IDR <?php echo number_format($pending_payment['amount'], 0, ',', '.'); ?></span>
                                </div>
                                <div class="pending-item">
                                    <span class="item-label">Order ID:</span>
                                    <span class="item-value"><?php echo $pending_payment['order_id']; ?></span>
                                </div>
                                <div class="pending-item">
                                    <span class="item-label">Status:</span>
                                    <span class="item-value status-<?php echo $pending_payment['status']; ?>">
                                        <?php 
                                        $displayStatus = $pending_payment['transaction_status'] ?: $pending_payment['status'];
                                        echo ucfirst($displayStatus); 
                                        ?>
                                    </span>
                                </div>
                                <?php if (!empty($pending_payment['payment_type'])): ?>
                                <div class="pending-item">
                                    <span class="item-label">Payment Method:</span>
                                    <span class="item-value"><?php echo ucfirst($pending_payment['payment_type']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="pending-actions">
                                <form method="post" action="">
                                    <input type="hidden" name="payment_method" value="midtrans">
                                    <button type="submit" class="btn btn-primary">Continue Payment</button>
                                </form>
                                <a href="index.php?page=midtrans-callback&status=check&order_id=<?php echo $pending_payment['order_id']; ?>" class="btn btn-secondary">
                                    Check Status
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Show payment form -->
                        <div class="payment-form">
                            <h3>Make a Payment</h3>
                            <form method="post" action="">
                                <div class="form-group">
                                    <label>Payment Method</label>
                                    <div class="payment-methods">
                                        <div class="payment-method">
                                            <input type="radio" id="method_midtrans" name="payment_method" value="midtrans" checked>
                                            <label for="method_midtrans">
                                                <i class="fas fa-credit-card"></i>
                                                <span>Payment Gateway Midtrans</span>
                                            </label>
                                        </div>
                                        <div class="payment-method">
                                            <input type="radio" id="method_transfer" name="payment_method" value="transfer">
                                            <label for="method_transfer">
                                                <i class="fas fa-university"></i>
                                                <span>Transfer</span>
                                            </label>
                                        </div>
                                        <div class="payment-method">
                                            <input type="radio" id="method_qris" name="payment_method" value="qris">
                                            <label for="method_qris">
                                                <i class="fas fa-qrcode"></i>
                                                <span>QRIS</span>
                                            </label>
                                        </div>
                                        <div class="payment-method">
                                            <input type="radio" id="method_cash" name="payment_method" value="cash">
                                            <label for="method_cash">
                                                <i class="fas fa-money-bill-wave"></i>
                                                <span>Cash</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="payment-amount">
                                    <span>Amount to Pay:</span>
                                    <span class="amount">IDR <?php echo number_format($tenant['price'], 0, ',', '.'); ?></span>
                                </div>

                                <button type="submit" class="btn btn-primary">Submit Payment</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card no-room-card">
                <div class="card-body">
                    <div class="no-room-message">
                        <i class="fas fa-home"></i>
                        <h3>No Active Room</h3>
                        <p>You don't have an active room yet. Book a room to make payments.</p>
                        <a href="index.php?page=rooms" class="btn btn-primary">Browse Rooms</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Payment History Card -->
        <div class="card payment-history-card">
            <div class="card-header">
                <h2>Payment History</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($payments)): ?>
                    <div class="payment-history">
                        <?php foreach ($payments as $payment): ?>
                            <div class="payment-history-item">
                                <div class="payment-icon <?php echo $payment['status']; ?>">
                                    <?php if ($payment['status'] === 'paid'): ?>
                                        <i class="fas fa-check-circle"></i>
                                    <?php elseif ($payment['status'] === 'pending'): ?>
                                        <i class="fas fa-clock"></i>
                                    <?php else: ?>
                                        <i class="fas fa-times-circle"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="payment-details">
                                    <div class="payment-date"><?php echo date('d F Y', strtotime($payment['payment_date'])); ?></div>
                                    <div class="payment-method">
                                        <?php
                                        switch ($payment['payment_method']) {
                                            case 'transfer':
                                                echo 'Bank Transfer';
                                                break;
                                            case 'qris':
                                                echo 'QRIS';
                                                break;
                                            case 'cash':
                                                echo 'Cash';
                                                break;
                                            case 'midtrans':
                                                $payment_type = $payment['payment_type'] ?: 'Online Payment';
                                                echo ucfirst($payment_type);
                                                break;
                                            default:
                                                echo ucfirst($payment['payment_method']);
                                        }
                                        ?>
                                    </div>
                                    <?php if ($payment['payment_method'] === 'midtrans' && !empty($payment['transaction_status'])): ?>
                                        <div class="transaction-status">
                                            Status: <?php echo ucfirst($payment['transaction_status']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="payment-amount">
                                    <div class="amount">IDR <?php echo number_format($payment['amount'], 0, ',', '.'); ?></div>
                                    <div class="status <?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-history">
                        <i class="fas fa-receipt"></i>
                        <p>No payment history available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Payment Instructions Card -->
        <?php if ($tenant): ?>
            <div class="card payment-instructions-card">
                <div class="card-header">
                    <h2>Payment Instructions</h2>
                </div>
                <div class="card-body">
                    <div class="instruction-tabs">
                        <div class="tab active" data-tab="midtrans">Payment Gateway Midtrans</div>
                        <div class="tab" data-tab="transfer">Bank Transfer</div>
                        <div class="tab" data-tab="qris">QRIS</div>
                        <div class="tab" data-tab="cash">Cash</div>
                    </div>

                    <div class="instruction-content">
                        <div class="instruction-panel active" id="midtrans-panel">
                            <div class="instruction-item">
                                <div class="instruction-step">1</div>
                                <div class="instruction-text">
                                    Select "Payment Gateway Midtrans" payment method.
                                </div>
                            </div>
                            <div class="instruction-item">
                                <div class="instruction-step">2</div>
                                <div class="instruction-text">
                                    Click "Submit Payment" to proceed to the payment gateway.
                                </div>
                            </div>
                            <div class="instruction-item">
                                <div class="instruction-step">3</div>
                                <div class="instruction-text">
                                    Complete the payment through the secure payment gateway, which supports:
                                    <div class="supported-methods">
                                        <div class="method-icon">
                                            <i class="fas fa-credit-card"></i>
                                            <span>Credit Cards</span>
                                        </div>
                                        <div class="method-icon">
                                            <i class="fas fa-wallet"></i>
                                            <span>E-Wallets</span>
                                        </div>
                                        <div class="method-icon">
                                            <i class="fas fa-university"></i>
                                            <span>Bank Transfer</span>
                                        </div>
                                        <div class="method-icon">
                                            <i class="fas fa-store"></i>
                                            <span>Convenience Stores</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="instruction-panel" id="transfer-panel">
                            <div class="instruction-item">
                                <div class="instruction-step">1</div>
                                <div class="instruction-text">
                                    Transfer to the following account:<br>
                                    <strong>Bank: Bank Central Asia (BCA)</strong><br>
                                    <strong>Account Number: 1234567890</strong><br>
                                    <strong>Account Name: Aulia Kost</strong>
                                </div>
                            </div>
                            <div class="instruction-item">
                                <div class="instruction-step">2</div>
                                <div class="instruction-text">
                                    Include your room number in the transfer description.
                                </div>
                            </div>
                            <div class="instruction-item">
                                <div class="instruction-step">3</div>
                                <div class="instruction-text">
                                    Submit your payment through the form above.
                                </div>
                            </div>
                        </div>

                        <div class="instruction-panel" id="qris-panel">
                            <div class="instruction-item">
                                <div class="instruction-step">1</div>
                                <div class="instruction-text">
                                    Scan the QRIS code below:<br>
                                    <div class="qris-code">
                                        <img src="assets/images/qris-code.png" alt="QRIS Code">
                                    </div>
                                </div>
                            </div>
                            <div class="instruction-item">
                                <div class="instruction-step">2</div>
                                <div class="instruction-text">
                                    Include your room number in the payment description.
                                </div>
                            </div>
                            <div class="instruction-item">
                                <div class="instruction-step">3</div>
                                <div class="instruction-text">
                                    Submit your payment through the form above.
                                </div>
                            </div>
                        </div>

                        <div class="instruction-panel" id="cash-panel">
                            <div class="instruction-item">
                                <div class="instruction-step">1</div>
                                <div class="instruction-text">
                                    Visit the Aulia Kost office during office hours: 08:00 - 20:00.
                                </div>
                            </div>
                            <div class="instruction-item">
                                <div class="instruction-step">2</div>
                                <div class="instruction-text">
                                    Make your payment to the receptionist.
                                </div>
                            </div>
                            <div class="instruction-item">
                                <div class="instruction-step">3</div>
                                <div class="instruction-text">
                                    Keep your receipt as proof of payment.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Add these styles to fix the display issues */
.payment-status-info {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    border-radius: 8px;
    margin-top: 16px;
}

.payment-paid {
    background-color: rgba(46, 125, 50, 0.1);
    border: 1px solid rgba(46, 125, 50, 0.2);
}

.payment-status-info .status-icon {
    font-size: 32px;
    color: #2e7d32;
}

.payment-status-info .status-text h3 {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 4px;
}

.payment-status-info .status-text p {
    font-size: 14px;
    color: #666;
    margin: 0;
}

/* Pending payment info styling */
.pending-payment-info {
    background-color: rgba(237, 108, 2, 0.1);
    border: 1px solid rgba(237, 108, 2, 0.2);
    border-radius: 8px;
    padding: 20px;
    margin-top: 16px;
}

.pending-payment-info h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
    color: #ed6c02;
}

.pending-message {
    margin-bottom: 16px;
    color: #666;
}

.pending-details {
    background-color: rgba(255, 255, 255, 0.5);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
}

.pending-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.pending-item:last-child {
    margin-bottom: 0;
    padding-bottom: 0;
    border-bottom: none;
}

.pending-actions {
    display: flex;
    gap: 12px;
}

.pending-actions .btn {
    flex: 1;
}

/* Status badges */
.status-pending {
    color: #ed6c02;
}

.status-paid, .status-settlement, .status-capture {
    color: #2e7d32;
}

.status-expired, .status-expire, .status-failure, .status-cancel, .status-deny {
    color: #d32f2f;
}

/* Button styles */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 16px;
    border-radius: 8px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    text-decoration: none;
}

.btn-primary {
    background-color: #3f51b5;
    color: white;
}

.btn-primary:hover {
    background-color: #333;
    transform: translateY(-2px);
}

.btn-secondary {
    background-color: #f5f5f5;
    color: #333;
    border: 1px solid #ddd;
}

.btn-secondary:hover {
    background-color: #e0e0e0;
}

/* Add styles for payment history */
.payment-history-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    border-radius: 8px;
    background-color: #f5f5f5;
    margin-bottom: 12px;
    transition: all 0.2s ease;
}

.payment-history-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.payment-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.payment-icon.paid {
    background-color: rgba(46, 125, 50, 0.1);
    color: #2e7d32;
}

.payment-icon.unpaid, .payment-icon.pending {
    background-color: rgba(237, 108, 2, 0.1);
    color: #ed6c02;
}

.payment-icon.expired {
    background-color: rgba(211, 47, 47, 0.1);
    color: #d32f2f;
}

.payment-details {
    flex: 1;
}

.payment-date {
    font-weight: 500;
    margin-bottom: 4px;
}

.payment-method {
    font-size: 14px;
    color: #666;
}

.transaction-status {
    font-size: 12px;
    margin-top: 4px;
    color: #666;
}

.payment-amount {
    text-align: right;
}

.payment-amount .amount {
    font-weight: 600;
    margin-bottom: 4px;
}

.payment-amount .status {
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 50px;
    display: inline-block;
    text-align: center;
}

.payment-amount .status.paid {
    background-color: rgba(46, 125, 50, 0.1);
    color: #2e7d32;
}

.payment-amount .status.unpaid, .payment-amount .status.pending {
    background-color: rgba(237, 108, 2, 0.1);
    color: #ed6c02;
}

.payment-amount .status.expired {
    background-color: rgba(211, 47, 47, 0.1);
    color: #d32f2f;
}

/* Payment form styling */
.payment-form h3 {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 16px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
}

.payment-methods {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
}

.payment-method {
    position: relative;
}

.payment-method input[type="radio"] {
    position: absolute;
    opacity: 0;
}

.payment-method label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 16px;
    border: 1px solid #ddd;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.payment-method label:hover {
    background-color: #f5f5f5;
}

.payment-method input[type="radio"]:checked+label {
    border-color: #3f51b5;
    background-color: rgba(63, 81, 181, 0.05);
}

.payment-method i {
    font-size: 24px;
    color: #3f51b5;
}

.payment-amount {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background-color: #f5f5f5;
    border-radius: 8px;
    margin-bottom: 20px;
}

.payment-amount .amount {
    font-size: 18px;
    font-weight: 600;
    color: #3f51b5;
}

/* Card styles */
.card {
    background-color: white;
    border-radius: 8px;
    border: 1px solid #ddd;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    margin-bottom: 24px;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.card-header {
    padding: 16px 20px;
    border-bottom: 1px solid #ddd;
    background-color: #f9f9f9;
}

.card-header h2 {
    font-size: 18px;
    font-weight: 600;
    margin: 0;
}

.card-body {
    padding: 20px;
}

/* Instructions tab styling */
.instruction-tabs {
    display: flex;
    border-bottom: 1px solid #ddd;
    margin-bottom: 20px;
    overflow-x: auto;
}

.tab {
    padding: 12px 20px;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s ease;
    border-bottom: 2px solid transparent;
}

.tab:hover {
    background-color: #f5f5f5;
}

.tab.active {
    border-bottom-color: #3f51b5;
    font-weight: 500;
}

.instruction-panel {
    display: none;
}

.instruction-panel.active {
    display: block;
}

.instruction-item {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
}

.instruction-step {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #3f51b5;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    flex-shrink: 0;
}

/* Alert styles */
.alert {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-danger {
    background-color: rgba(211, 47, 47, 0.1);
    color: #d32f2f;
    border: 1px solid rgba(211, 47, 47, 0.2);
}

.alert-success {
    background-color: rgba(46, 125, 50, 0.1);
    color: #2e7d32;
    border: 1px solid rgba(46, 125, 50, 0.2);
}

.alert-info {
    background-color: rgba(2, 136, 209, 0.1);
    color: #0288d1;
    border: 1px solid rgba(2, 136, 209, 0.2);
}

/* Responsive fixes */
@media (max-width: 768px) {
    .payment-methods {
        grid-template-columns: 1fr;
    }
    
    .pending-actions {
        flex-direction: column;
    }
    
    .instruction-tabs {
        flex-direction: column;
        border-bottom: none;
    }
    
    .tab {
        border-bottom: none;
        border-left: 2px solid transparent;
    }
    
    .tab.active {
        border-bottom-color: transparent;
        border-left-color: #3f51b5;
    }
}
</style>

<script src="<?php echo $midtrans_js_url; ?>" data-client-key="<?php echo $midtrans_client_key; ?>"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Payment method selection
        const paymentMethods = document.querySelectorAll('.payment-method input[type="radio"]');
        paymentMethods.forEach(method => {
            method.addEventListener('change', function() {
                // You can add additional logic here if needed
            });
        });

        // Instruction tabs
        const tabs = document.querySelectorAll('.instruction-tabs .tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and panels
                tabs.forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.instruction-panel').forEach(p => p.classList.remove('active'));

                // Add active class to clicked tab and corresponding panel
                this.classList.add('active');
                document.getElementById(`${this.dataset.tab}-panel`).classList.add('active');
            });
        });

        // Add subtle hover effects
        const cards = document.querySelectorAll('.card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Continue payment button for pending Midtrans payments
        const continuePaymentBtn = document.getElementById('continue-payment');
        if (continuePaymentBtn) {
            continuePaymentBtn.addEventListener('click', function() {
                const token = this.getAttribute('data-token');
                const orderId = this.getAttribute('data-order');

                if (token) {
                    // Show Snap payment page
                    snap.pay(token, {
                        onSuccess: function(result) {
                            window.location.href = 'index.php?page=midtrans-callback&status=success&order_id=' + orderId;
                        },
                        onPending: function(result) {
                            window.location.href = 'index.php?page=midtrans-callback&status=pending&order_id=' + orderId;
                        },
                        onError: function(result) {
                            console.log('Error result:', result);
                            // Handle Midtrans token expired error
                            if (result.status_code === "406") {
                                alert("Payment session has expired. A new payment will be created.");
                                const newPaymentForm = document.querySelector('form[action=""] input[name="payment_method"][value="midtrans"]').closest('form');
                                if (newPaymentForm) {
                                    newPaymentForm.submit();
                                }
                            } else {
                                window.location.href = 'index.php?page=midtrans-callback&status=error&order_id=' + orderId;
                            }
                        },
                        onClose: function() {
                            console.log('Customer closed the payment window');
                            // Refresh the page to update payment status
                            window.location.reload();
                        }
                    });
                } else {
                    // If no token, just submit the form to create a new payment
                    const newPaymentForm = document.querySelector('form[action=""] input[name="payment_method"][value="midtrans"]').closest('form');
                    if (newPaymentForm) {
                        newPaymentForm.submit();
                    }
                }
            });
        }
    });
</script>

<?php
// Add this at the end of the file to flush the output buffer
ob_end_flush();
?>