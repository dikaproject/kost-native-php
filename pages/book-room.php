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

// Get room images
$stmt = $pdo->prepare("
    SELECT * FROM room_images 
    WHERE room_id = ?
    ORDER BY is_primary DESC
");
$stmt->execute([$room_id]);
$images = $stmt->fetchAll();

// Get room features
$stmt = $pdo->prepare("
    SELECT feature_name FROM room_features 
    WHERE room_id = ?
");
$stmt->execute([$room_id]);
$features = $stmt->fetchAll();

// Process booking form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug - log form submission
    error_log("Book room form submitted for room ID: " . $room_id);
    
    // Validate form data
    $start_date = $_POST['start_date'];
    $payment_method = $_POST['payment_method'];
    $agree_terms = isset($_POST['agree_terms']) ? 1 : 0;
    
    // Validate required fields
    $errors = [];
    if (empty($start_date)) {
        $errors[] = "Start date is required";
    }
    if (empty($payment_method)) {
        $errors[] = "Payment method is required";
    }
    if (!$agree_terms) {
        $errors[] = "You must agree to the terms and conditions";
    }
    
    // If no errors, process booking
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Debug - log transaction start
            error_log("Starting booking transaction for user ID: " . $_SESSION['user_id'] . " and room ID: " . $room_id);
            
            // Create tenant record
            $stmt = $pdo->prepare("
                INSERT INTO tenants (user_id, room_id, start_date, status, created_at, updated_at)
                VALUES (?, ?, ?, 'active', NOW(), NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $room_id, $start_date]);
            $tenant_id = $pdo->lastInsertId();
            
            // Debug - log tenant creation
            error_log("Created tenant record with ID: " . $tenant_id);
            
            // Update room status
            $stmt = $pdo->prepare("
                UPDATE rooms SET status = 'occupied' WHERE id = ?
            ");
            $stmt->execute([$room_id]);
            
            // Debug - log room status update
            error_log("Updated room status to occupied for room ID: " . $room_id);
            
            // Create payment record
            $stmt = $pdo->prepare("
                INSERT INTO payments (tenant_id, amount, payment_date, payment_method, status, created_at, updated_at)
                VALUES (?, ?, NOW(), ?, 'pending', NOW(), NOW())
            ");
            $stmt->execute([$tenant_id, $room['price'], $payment_method]);
            
            // Debug - log payment creation
            error_log("Created payment record for tenant ID: " . $tenant_id);
            
            // Create notification for admin
            $stmt = $pdo->prepare("
                INSERT INTO notifications (recipient_id, sender_id, type, message, is_read, created_at, updated_at)
                VALUES (1, ?, 'booking', ?, 0, NOW(), NOW())
            ");
            $message = "New booking: {$_SESSION['user_id']} has booked room {$room['name']}";
            $stmt->execute([$_SESSION['user_id'], $message]);
            
            // Create notification for user
            $stmt = $pdo->prepare("
                INSERT INTO notifications (recipient_id, sender_id, type, message, is_read, created_at, updated_at)
                VALUES (?, 1, 'booking', ?, 0, NOW(), NOW())
            ");
            $message = "Your booking for room {$room['name']} has been confirmed. Please complete your payment.";
            $stmt->execute([$_SESSION['user_id'], $message]);
            
            // Commit transaction
            $pdo->commit();
            
            // Debug - log successful transaction
            error_log("Successfully completed booking transaction");
            
            // Set success message and redirect
            $_SESSION['success_message'] = "Room booked successfully! Please complete your payment.";
            header("Location: index.php?page=payments");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            
            // Debug - log error
            error_log("Error in booking transaction: " . $e->getMessage());
            
            // Add detailed error message
            $errors[] = "An error occurred during booking: " . $e->getMessage();
        }
    }
}
?>

<div class="page-content">
    <div class="booking-header">
        <a href="index.php?page=room-detail&id=<?php echo $room_id; ?>" class="back-button">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Room Details</span>
        </a>
        <h1 class="page-title">Book Room</h1>
    </div>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']); // Clear the message after displaying
            ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="booking-container">
        <!-- Room Summary -->
        <div class="booking-summary">
            <div class="room-image">
                <img src="<?php echo !empty($images) ? 'uploads/rooms/' . $images[0]['image_path'] : 'assets/images/default-room.jpg'; ?>" alt="<?php echo $room['name']; ?>">
            </div>
            <div class="room-details">
                <h2><?php echo $room['name']; ?></h2>
                <div class="room-price">IDR <?php echo number_format($room['price'], 0, ',', '.'); ?>/month</div>
                
                <div class="room-features">
                    <h3>Features</h3>
                    <ul>
                        <?php foreach ($features as $feature): ?>
                            <li>
                                <?php
                                $icons = [
                                    'Air Conditioning' => 'fas fa-wind',
                                    'Private Bathroom' => 'fas fa-shower',
                                    'Shared Bathroom' => 'fas fa-shower',
                                    'Study Desk' => 'fas fa-desk',
                                    'Free WiFi' => 'fas fa-wifi',
                                    'Single Bed' => 'fas fa-bed',
                                    'Double Bed' => 'fas fa-bed',
                                    'TV' => 'fas fa-tv',
                                    'Cleaning Service' => 'fas fa-broom'
                                ];
                                $icon = isset($icons[$feature['feature_name']]) ? $icons[$feature['feature_name']] : 'fas fa-check';
                                ?>
                                <i class="<?php echo $icon; ?>"></i>
                                <span><?php echo $feature['feature_name']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="room-description">
                    <h3>Description</h3>
                    <p><?php echo $room['description'] ?: 'No description available.'; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Booking Form -->
        <div class="booking-form-container">
            <h2>Booking Details</h2>
            
            <form method="post" class="booking-form">
                <div class="form-group">
                    <label for="start_date" class="form-label">Move-in Date</label>
                    <input type="date" id="start_date" name="start_date" class="form-input" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <div class="payment-methods">
                        <div class="payment-method">
                            <input type="radio" id="payment_credit" name="payment_method" value="credit_card" required>
                            <label for="payment_credit">
                                <i class="fas fa-credit-card"></i>
                                <span>Credit Card</span>
                            </label>
                        </div>
                        
                        <div class="payment-method">
                            <input type="radio" id="payment_bank" name="payment_method" value="bank_transfer" required>
                            <label for="payment_bank">
                                <i class="fas fa-university"></i>
                                <span>Bank Transfer</span>
                            </label>
                        </div>
                        
                        <div class="payment-method">
                            <input type="radio" id="payment_ewallet" name="payment_method" value="e_wallet" required>
                            <label for="payment_ewallet">
                                <i class="fas fa-wallet"></i>
                                <span>E-Wallet</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="booking-summary-box">
                    <h3>Booking Summary</h3>
                    <div class="summary-item">
                        <span>Room</span>
                        <span><?php echo $room['name']; ?></span>
                    </div>
                    <div class="summary-item">
                        <span>Monthly Rent</span>
                        <span>IDR <?php echo number_format($room['price'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="summary-item">
                        <span>Security Deposit</span>
                        <span>IDR <?php echo number_format($room['price'], 0, ',', '.'); ?></span>
                    </div>
                    <div class="summary-item total">
                        <span>Total Initial Payment</span>
                        <span>IDR <?php echo number_format($room['price'] * 2, 0, ',', '.'); ?></span>
                    </div>
                    <div class="summary-note">
                        <p>* Initial payment includes first month's rent and security deposit</p>
                    </div>
                </div>
                
                <div class="form-group terms-checkbox">
                    <input type="checkbox" id="agree_terms" name="agree_terms" required>
                    <label for="agree_terms">I agree to the <a href="#" class="terms-link">Terms and Conditions</a></label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Confirm Booking</button>
                    <a href="index.php?page=room-detail&id=<?php echo $room_id; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    /* Page content */
    .page-content {
        margin-bottom: 24px;
    }

    .booking-header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 24px;
    }

    .back-button {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background-color: transparent;
        border: 1px solid var(--border-color);
        border-radius: 50px;
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        color: var(--text-primary);
        font-size: 14px;
    }

    .back-button:hover {
        background-color: var(--hover-color);
        transform: translateX(-3px);
    }

    .page-title {
        font-size: 28px;
        font-weight: 700;
        letter-spacing: -0.5px;
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

    .alert ul {
        margin: 0;
        padding-left: 20px;
    }

    /* Booking Container */
    .booking-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }

    /* Room Summary */
    .booking-summary {
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        border: 1px solid var(--border-color);
    }

    .booking-summary:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .room-image {
        height: 200px;
        overflow: hidden;
    }

    .room-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--transition);
    }

    .booking-summary:hover .room-image img {
        transform: scale(1.05);
    }

    .room-details {
        padding: 24px;
    }

    .room-details h2 {
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .room-price {
        font-size: 18px;
        font-weight: 600;
        color: var(--accent-color);
        margin-bottom: 16px;
    }

    .room-features {
        margin-bottom: 16px;
    }

    .room-features h3 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .room-features ul {
        list-style: none;
        padding: 0;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }

    .room-features li {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .room-features li i {
        color: var(--accent-color);
    }

    .room-description h3 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .room-description p {
        font-size: 14px;
        color: var(--text-secondary);
        line-height: 1.6;
    }

    /* Booking Form */
    .booking-form-container {
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 24px;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        border: 1px solid var(--border-color);
    }

    .booking-form-container:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .booking-form-container h2 {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 24px;
    }

    .booking-form {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .form-label {
        font-size: 14px;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .form-input {
        padding: 12px 16px;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-md);
        font-size: 14px;
        transition: var(--transition);
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent-color);
        box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
    }

    /* Payment Methods */
    .payment-methods {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }

    .payment-method {
        position: relative;
    }

    .payment-method input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
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

    /* Booking Summary Box */
    .booking-summary-box {
        background-color: var(--sidebar-bg);
        border-radius: var(--border-radius-md);
        padding: 16px;
    }

    .booking-summary-box h3 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 16px;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
        font-size: 14px;
    }

    .summary-item.total {
        font-weight: 600;
        font-size: 16px;
        border-top: 1px solid var(--border-color);
        padding-top: 12px;
        margin-top: 12px;
    }

    .summary-note {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 12px;
    }

    /* Terms Checkbox */
    .terms-checkbox {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 8px;
    }

    .terms-link {
        color: var(--accent-color);
        text-decoration: none;
    }

    .terms-link:hover {
        text-decoration: underline;
    }

    /* Form Actions */
    .form-actions {
        display: flex;
        gap: 16px;
        margin-top: 16px;
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

    .btn-secondary {
        background-color: var(--sidebar-bg);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }

    .btn-secondary:hover {
        background-color: var(--hover-color);
        transform: translateY(-2px);
    }

    @media (max-width: 992px) {
        .booking-container {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .payment-methods {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add subtle hover effects
        const sections = document.querySelectorAll('.booking-summary, .booking-form-container');
        sections.forEach(section => {
            section.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.boxShadow = 'var(--shadow-md)';
            });
            section.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'var(--shadow-sm)';
            });
        });
    });
</script>

