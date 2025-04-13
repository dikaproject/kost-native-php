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
if ($tenant) {
    // Cek apakah ada pembayaran aktif bulan ini yang sudah paid
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
    
    // Jika belum ada pembayaran yang paid bulan ini, baru cek pembayaran pending
    if (!$already_paid_this_month) {
        $stmt = $pdo->prepare("
            SELECT * FROM payments
            WHERE tenant_id = ? 
            AND status = 'unpaid' 
            AND payment_method = 'midtrans' 
            AND transaction_status IS NOT NULL 
            AND transaction_status NOT IN ('settlement', 'capture', 'cancel', 'deny', 'expire')
            ORDER BY payment_date DESC
            LIMIT 1
        ");
        $stmt->execute([$tenant['id']]);
        $pending_payment = $stmt->fetch();
        $has_pending_midtrans = ($pending_payment !== false);
        
        // Jika ada pembayaran yang pending, cek statusnya dari Midtrans
        if ($has_pending_midtrans && !empty($pending_payment['order_id'])) {
            $transaction = check_transaction_status($pending_payment['order_id']);
            
            // Jika status telah berubah, update database
            if (!isset($transaction['error'])) {
                $transaction_status = $transaction['transaction_status'] ?? '';
                $payment_status = 'unpaid';
                
                if ($transaction_status === 'settlement' || $transaction_status === 'capture') {
                    $payment_status = 'paid';
                    // Jika status berubah jadi paid, kita set flag sudah bayar bulan ini
                    $already_paid_this_month = true;
                }
                
                // Update payment record
                $stmt = $pdo->prepare("
                    UPDATE payments 
                    SET 
                        transaction_status = ?,
                        status = ?
                    WHERE id = ?
                ");
                $stmt->execute([$transaction_status, $payment_status, $pending_payment['id']]);
                
                // Refresh payment info
                $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
                $stmt->execute([$pending_payment['id']]);
                $pending_payment = $stmt->fetch();
                $has_pending_midtrans = ($pending_payment['status'] === 'unpaid');
            }
        }
    } else {
        // Jika sudah ada pembayaran yang paid bulan ini, jangan tampilkan form pembayaran
        $has_pending_midtrans = false;
    }
}

// Process payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $payment_method = $_POST['payment_method'];
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
                
                $customer_details = [
                    'first_name' => $_SESSION['first_name'] ?? 'Customer',
                    'last_name' => $_SESSION['last_name'] ?? '',
                    'email' => $_SESSION['email'] ?? '',
                    'phone' => $_SESSION['phone'] ?? '',
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
                    throw new Exception("Midtrans error: " . $midtrans_response['error']);
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
        <!-- Tampilkan pesan bahwa sudah melakukan pembayaran bulan ini -->
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
                            <span class="item-value status-<?php echo $pending_payment['transaction_status'] ?: 'pending'; ?>">
                                <?php echo ucfirst($pending_payment['transaction_status'] ?: 'pending'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="pending-actions">
                        <?php if (!empty($pending_payment['midtrans_token'])): ?>
                        <button id="continue-payment" class="btn btn-primary" data-token="<?php echo $pending_payment['midtrans_token']; ?>">
                            Continue Payment
                        </button>
                        <?php endif; ?>
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
                                        <span>payment Gateway Midtrans</span>
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
                                    switch($payment['payment_method']) {
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
                    <div class="tab active" data-tab="midtrans">payment Gateway Midtrans</div>
                    <div class="tab" data-tab="transfer">Bank Transfer</div>
                    <div class="tab" data-tab="qris">QRIS</div>
                    <div class="tab" data-tab="cash">Cash</div>
                </div>
                
                <div class="instruction-content">
                    <div class="instruction-panel active" id="midtrans-panel">
                        <div class="instruction-item">
                            <div class="instruction-step">1</div>
                            <div class="instruction-text">
                                Select "payment Gateway Midtrans" payment method.
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
                                    <img src="assets/images/qris-code.png" alt="QRIS Code" onerror="this.src='https://via.placeholder.com/200x200?text=QRIS+Code'">
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
    .payment-status-info {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    border-radius: var(--border-radius-md);
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
    color: var(--text-secondary);
    margin: 0;
}

    /* Page content */
    .page-content {
        margin-bottom: 24px;
    }

    .page-title {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 8px;
        letter-spacing: -0.5px;
    }

    .page-subtitle {
        color: var(--text-secondary);
        font-size: 16px;
        margin-bottom: 24px;
    }

    /* Alert Styles */
    .alert {
        padding: 16px;
        border-radius: var(--border-radius-md);
        margin-bottom: 24px;
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

    .alert ul {
        margin: 0;
        padding-left: 20px;
    }

    /* Payment Container */
    .payment-container {
        display: grid;
        grid-template-columns: 1fr;
        gap: 24px;
    }

    /* Card Styles */
    .card {
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        border: 1px solid var(--border-color);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
    }

    .card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
    }

    .card-header h2 {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
    }

    .card-body {
        padding: 24px;
    }

    /* Payment Summary Card */
    .payment-summary-card .summary-details {
        margin-bottom: 24px;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border-color);
    }

    .summary-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .item-label {
        color: var(--text-secondary);
    }

    .item-value {
        font-weight: 500;
    }

    /* Payment Form */
    .payment-form h3 {
        font-size: 16px;
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
        grid-template-columns: repeat(3, 1fr);
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
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-md);
        cursor: pointer;
        transition: var(--transition);
    }

    .payment-method label:hover {
        background-color: var(--hover-color);
    }

    .payment-method input[type="radio"]:checked + label {
        border-color: var(--accent-color);
        background-color: rgba(0, 0, 0, 0.02);
    }

    .payment-method i {
        font-size: 24px;
        color: var(--accent-color);
    }

    .payment-amount {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px;
        background-color: var(--sidebar-bg);
        border-radius: var(--border-radius-md);
        margin-bottom: 20px;
    }

    .payment-amount .amount {
        font-size: 18px;
        font-weight: 600;
        color: var(--accent-color);
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 12px 24px;
        border-radius: var(--border-radius-md);
        font-weight: 500;
        cursor: pointer;
        transition: var(--transition);
        border: none;
        text-decoration: none;
    }

    .btn-primary {
        background-color: var(--accent-color);
        color: white;
    }

    .btn-primary:hover {
        background-color: #333;
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }

    /* No Room Card */
    .no-room-card .card-body {
        padding: 48px 24px;
    }

    .no-room-message {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }

    .no-room-message i {
        font-size: 48px;
        color: var(--text-secondary);
        margin-bottom: 16px;
    }

    .no-room-message h3 {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .no-room-message p {
        color: var(--text-secondary);
        margin-bottom: 24px;
    }

    /* Payment History Card */
    .payment-history {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .payment-history-item {
        display: flex;
        align-items: center;
        gap: 16px;
        padding: 16px;
        border-radius: var(--border-radius-md);
        background-color: var(--sidebar-bg);
        transition: var(--transition);
    }

    .payment-history-item:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
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

    .payment-icon.unpaid {
        background-color: rgba(237, 108, 2, 0.1);
        color: #ed6c02;
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
        color: var(--text-secondary);
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
    }

    .payment-amount .status.paid {
        background-color: rgba(46, 125, 50, 0.1);
        color: #2e7d32;
    }

    .payment-amount .status.unpaid {
        background-color: rgba(237, 108, 2, 0.1);
        color: #ed6c02;
    }

    .no-history {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        padding: 24px;
    }

    .no-history i {
        font-size: 48px;
        color: var(--text-secondary);
        margin-bottom: 16px;
    }

    .no-history p {
        color: var(--text-secondary);
    }

    /* Payment Instructions Card */
    .instruction-tabs {
        display: flex;
        border-bottom: 1px solid var(--border-color);
        margin-bottom: 24px;
    }

    .tab {
        padding: 12px 24px;
        cursor: pointer;
        transition: var(--transition);
        border-bottom: 2px solid transparent;
    }

    .tab:hover {
        background-color: var(--hover-color);
    }

    .tab.active {
        border-bottom-color: var(--accent-color);
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

    .instruction-item:last-child {
        margin-bottom: 0;
    }

    .instruction-step {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background-color: var(--accent-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        flex-shrink: 0;
    }

    .instruction-text {
        flex: 1;
        line-height: 1.6;
    }

    .qris-code {
        margin-top: 12px;
        text-align: center;
    }

    .qris-code img {
        max-width: 200px;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-md);
        padding: 8px;
        background-color: white;
    }

    @media (max-width: 768px) {
        .payment-methods {
            grid-template-columns: 1fr;
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
            border-left-color: var(--accent-color);
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
                if (token) {
                    // Show Snap payment page
                    snap.pay(token, {
                        onSuccess: function(result) {
                            window.location.href = 'index.php?page=midtrans-callback&status=success&order_id=' + result.order_id;
                        },
                        onPending: function(result) {
                            window.location.href = 'index.php?page=midtrans-callback&status=pending&order_id=' + result.order_id;
                        },
                        onError: function(result) {
                            window.location.href = 'index.php?page=midtrans-callback&status=error&order_id=' + result.order_id;
                        },
                        onClose: function() {
                            console.log('Customer closed the payment window');
                        }
                    });
                }
            });
        }
    });
</script>