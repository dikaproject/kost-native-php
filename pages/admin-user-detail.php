<?php
// Get user ID from URL
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Process delete request
if (isset($_POST['delete_user']) && $_POST['user_id'] == $user_id) {
    // Check if the user exists and is not an admin (safety check)
    $check = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role != 'admin'");
    $check->execute([$user_id]);
    $user = $check->fetch();
    
    if ($user) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // First, get all tenant IDs for this user
            $tenant_stmt = $pdo->prepare("SELECT id FROM tenants WHERE user_id = ?");
            $tenant_stmt->execute([$user_id]);
            $tenant_ids = $tenant_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Delete payments associated with these tenant IDs
            if (!empty($tenant_ids)) {
                $placeholders = str_repeat('?,', count($tenant_ids) - 1) . '?';
                $pdo->prepare("DELETE FROM payments WHERE tenant_id IN ($placeholders)")->execute($tenant_ids);
            }
            
            // Delete any other tenant-related records
            // For example, if you have maintenance_requests or invoices referencing tenants
            if (!empty($tenant_ids)) {
                $placeholders = str_repeat('?,', count($tenant_ids) - 1) . '?';
                $pdo->prepare("DELETE FROM maintenance_requests WHERE tenant_id IN ($placeholders)")->execute($tenant_ids);
                $pdo->prepare("DELETE FROM invoices WHERE tenant_id IN ($placeholders)")->execute($tenant_ids);
            }
            
            // Now delete the tenant records
            $pdo->prepare("DELETE FROM tenants WHERE user_id = ?")->execute([$user_id]);
            
            // Delete other related user data
            $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")->execute([$user_id, $user_id]);
            $pdo->prepare("DELETE FROM notifications WHERE recipient_id = ? OR created_by = ?")->execute([$user_id, $user_id]);
            
            // Finally delete the user
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            
            // Commit the transaction
            $pdo->commit();
            
            // Set success message
            $_SESSION['success_message'] = "User successfully deleted";
        } catch (Exception $e) {
            // Rollback on error
            $pdo->rollBack();
            $_SESSION['error_message'] = "Failed to delete user: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "User not found or cannot delete admin user";
    }
}

// Get user details
$stmt = $pdo->prepare("
    SELECT u.*,
    (SELECT COUNT(*) FROM messages WHERE sender_id = u.id) as messages_sent,
    (SELECT COUNT(*) FROM notifications WHERE recipient_id = u.id) as notifications_received
    FROM users u
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// If user not found, redirect back to user list
if (!$user) {
    $_SESSION['error_message'] = "User not found";
    header("Location: index.php?page=admin-user");
    exit;
}

// Check if user is a tenant
$stmt = $pdo->prepare("
    SELECT t.*, r.name as room_name, r.price
    FROM tenants t
    JOIN rooms r ON t.room_id = r.id
    WHERE t.user_id = ? AND t.status = 'active'
");
$stmt->execute([$user_id]);
$tenant = $stmt->fetch();

// Get payment history if user is a tenant
$payments = [];
if ($tenant) {
    $stmt = $pdo->prepare("
        SELECT * FROM payments
        WHERE tenant_id = ?
        ORDER BY payment_date DESC
        LIMIT 5
    ");
    $stmt->execute([$tenant['id']]);
    $payments = $stmt->fetchAll();
}

$page_title = "User Details: " . $user['first_name'] . " " . $user['last_name'];
?>

<div class="page-content">
    <a href="index.php?page=admin-user" class="back-button">
        <i class="fas fa-arrow-left"></i>
        <span>Back to Users</span>
    </a>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="user-detail-container">
        <!-- User Profile Card -->
        <div class="user-profile-card">
            <div class="profile-header">
                <div class="profile-cover"></div>
                <div class="profile-avatar">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img src="uploads/profiles/<?php echo $user['profile_photo']; ?>" alt="<?php echo $user['first_name']; ?>">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="profile-actions">
                    <?php if ($user['role'] !== 'admin'): ?>
                        <button type="button" class="btn btn-delete" onclick="confirmDelete()">
                            <i class="fas fa-trash-alt"></i>
                            <span>Delete User</span>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="profile-body">
                <h1 class="user-name"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h1>
                <div class="user-role">
                    <span class="role-badge <?php echo $user['role'] === 'admin' ? 'admin' : 'tenant'; ?>">
                        <?php echo ucfirst($user['role']); ?>
                    </span>
                    <?php if ($tenant): ?>
                        <span class="tenant-badge">
                            <i class="fas fa-home"></i>
                            <?php echo $tenant['room_name']; ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="user-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></div>
                        <div class="stat-label">Joined</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $user['messages_sent']; ?></div>
                        <div class="stat-label">Messages</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $user['notifications_received']; ?></div>
                        <div class="stat-label">Notifications</div>
                    </div>
                </div>
                
                <?php if (!empty($user['bio'])): ?>
                    <div class="user-bio">
                        <h3>About</h3>
                        <p><?php echo nl2br(htmlspecialchars($user['bio'])); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- User Details -->
        <div class="user-details-cards">
            <div class="details-card">
                <h2 class="card-title">Personal Information</h2>
                
                <div class="detail-group">
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Email Address</div>
                            <div class="detail-value"><?php echo $user['email']; ?></div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Phone Number</div>
                            <div class="detail-value"><?php echo $user['phone']; ?></div>
                        </div>
                    </div>
                    
                    <?php if (!empty($user['dob'])): ?>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-birthday-cake"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Date of Birth</div>
                            <div class="detail-value"><?php echo date('F j, Y', strtotime($user['dob'])); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($user['gender'])): ?>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-venus-mars"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Gender</div>
                            <div class="detail-value"><?php echo ucfirst($user['gender']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!empty($user['street']) || !empty($user['city']) || !empty($user['country'])): ?>
            <div class="details-card">
                <h2 class="card-title">Address Information</h2>
                
                <div class="detail-group">
                    <?php if (!empty($user['street'])): ?>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Street Address</div>
                            <div class="detail-value"><?php echo $user['street']; ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($user['city']) || !empty($user['postal_code'])): ?>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-city"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">City & Postal Code</div>
                            <div class="detail-value">
                                <?php echo !empty($user['city']) ? $user['city'] : ''; ?>
                                <?php echo !empty($user['postal_code']) ? ' - ' . $user['postal_code'] : ''; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($user['state']) || !empty($user['country'])): ?>
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-globe"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">State & Country</div>
                            <div class="detail-value">
                                <?php echo !empty($user['state']) ? $user['state'] : ''; ?>
                                <?php echo !empty($user['country']) ? (!empty($user['state']) ? ', ' : '') . $user['country'] : ''; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($tenant): ?>
            <div class="details-card">
                <h2 class="card-title">Tenant Information</h2>
                
                <div class="detail-group">
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-home"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Room</div>
                            <div class="detail-value"><?php echo $tenant['room_name']; ?></div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Monthly Rent</div>
                            <div class="detail-value">IDR <?php echo number_format($tenant['price'], 0, ',', '.'); ?></div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Start Date</div>
                            <div class="detail-value"><?php echo date('F j, Y', strtotime($tenant['start_date'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">
                                <span class="status-badge active">
                                    <span class="status-dot"></span>
                                    <?php echo ucfirst($tenant['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($payments)): ?>
                <div class="payment-history">
                    <h3>Recent Payments</h3>
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($payment['payment_date'])); ?></td>
                                <td>IDR <?php echo number_format($payment['amount'], 0, ',', '.'); ?></td>
                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                <td>
                                    <span class="payment-status <?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirm Deletion</h2>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete the user <strong><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></strong>?</p>
            <p class="warning">This action cannot be undone. All user data will be permanently removed.</p>
        </div>
        <div class="modal-footer">
            <form method="POST">
                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                <input type="hidden" name="delete_user" value="1">
                <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete User</button>
            </form>
        </div>
    </div>
</div>

<style>
    /* Back button */
    .back-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background-color: var(--sidebar-bg);
        border-radius: 50px;
        color: var(--text-primary);
        font-size: 14px;
        text-decoration: none;
        transition: var(--transition);
        margin-bottom: 24px;
        border: 1px solid transparent;
    }

    .back-button:hover {
        background-color: var(--hover-color);
        transform: translateX(-4px);
        border-color: var(--border-color);
    }
    
    /* Alerts */
    .alert {
        padding: 12px 16px;
        border-radius: var(--border-radius-md);
        margin-bottom: 24px;
        display: flex;
        align-items: center;
    }
    
    .alert-danger {
        background-color: rgba(220, 53, 69, 0.1);
        border-left: 4px solid #dc3545;
        color: #dc3545;
    }
    
    /* User detail container */
    .user-detail-container {
        display: grid;
        grid-template-columns: 1fr;
        gap: 24px;
    }
    
    @media (min-width: 992px) {
        .user-detail-container {
            grid-template-columns: 320px 1fr;
            align-items: start;
        }
    }
    
    /* User profile card */
    .user-profile-card {
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-sm);
        overflow: hidden;
    }
    
    .profile-header {
        position: relative;
    }
    
    .profile-cover {
        height: 100px;
        background-color: var(--primary-color);
        background-image: linear-gradient(135deg, var(--primary-color) 0%, #5438DC 100%);
    }
    
    .profile-avatar {
        position: absolute;
        bottom: -40px;
        left: 24px;
        width: 80px;
        height: 80px;
        border-radius: 50%;
        overflow: hidden;
        border: 4px solid var(--card-bg);
        background-color: var(--sidebar-bg);
    }
    
    .profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .avatar-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--primary-color);
        color: white;
        font-weight: 600;
        font-size: 28px;
    }
    
    .profile-actions {
        position: absolute;
        top: 12px;
        right: 12px;
    }
    
    .profile-body {
        padding: 52px 24px 24px;
    }
    
    .user-name {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 8px;
        color: var(--text-primary);
    }
    
    .user-role {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
    }
    
    .role-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .role-badge.admin {
        background-color: rgba(var(--primary-rgb), 0.1);
        color: var(--primary-color);
    }
    
    .role-badge.tenant {
        background-color: rgba(108, 117, 125, 0.1);
        color: #6c757d;
    }
    
    .tenant-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 600;
        background-color: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }
    
    .tenant-badge i {
        font-size: 10px;
    }
    
    .user-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        margin-bottom: 24px;
    }
    
    .stat-item {
        text-align: center;
        padding: 12px;
        background-color: var(--sidebar-bg);
        border-radius: var(--border-radius-md);
    }
    
    .stat-value {
        font-weight: 600;
        font-size: 16px;
        margin-bottom: 4px;
    }
    
    .stat-label {
        font-size: 12px;
        color: var(--text-secondary);
    }
    
    .user-bio {
        margin-top: 24px;
    }
    
    .user-bio h3 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 12px;
        color: var(--text-primary);
    }
    
    .user-bio p {
        font-size: 14px;
        line-height: 1.6;
        color: var(--text-secondary);
    }
    
    /* User details cards */
    .user-details-cards {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    
    .details-card {
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 24px;
        box-shadow: var(--shadow-sm);
    }
    
    .card-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .detail-group {
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
    }
    
    @media (min-width: 768px) {
        .detail-group {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    .detail-item {
        display: flex;
        align-items: center;
        gap: 16px;
    }
    
    .detail-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: var(--sidebar-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .detail-icon i {
        color: var(--primary-color);
        font-size: 16px;
    }
    
    .detail-label {
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }
    
    .detail-value {
        font-weight: 500;
        color: var(--text-primary);
    }
    
    /* Payment history */
    .payment-history {
        margin-top: 24px;
    }
    
    .payment-history h3 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 16px;
        color: var(--text-primary);
    }
    
    .payments-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .payments-table th {
        text-align: left;
        padding: 12px;
        background-color: var(--sidebar-bg);
        color: var(--text-primary);
        font-weight: 600;
        font-size: 14px;
    }
    
    .payments-table td {
        padding: 12px;
        border-bottom: 1px solid var(--border-color);
        font-size: 14px;
    }
    
    .payments-table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .payment-status {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .payment-status.paid {
        background-color: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }
    
    .payment-status.unpaid {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
    
    .payment-status.pending {
        background-color: rgba(255, 193, 7, 0.1);
        color: #ffc107;
    }
    
    /* Status badges */
    .status-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        font-weight: 500;
    }
    
    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }
    
    .status-badge.active {
        color: #28a745;
    }
    
    .status-badge.active .status-dot {
        background-color: #28a745;
    }
    
    /* Buttons */
    .btn {
        padding: 8px 12px;
        border-radius: var(--border-radius-md);
        border: none;
        cursor: pointer;
        font-weight: 500;
        transition: var(--transition);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .btn-delete {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
    
    .btn-delete:hover {
        background-color: rgba(220, 53, 69, 0.2);
    }
    
    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }
    
    .modal-content {
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        width: 90%;
        max-width: 500px;
        box-shadow: var(--shadow-lg);
        animation: modalFadeIn 0.3s ease-out;
    }
    
    @keyframes modalFadeIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .modal-header {
        padding: 16px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h2 {
        font-size: 20px;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }
    
    .close-modal {
        cursor: pointer;
        font-size: 24px;
        line-height: 1;
        color: var(--text-secondary);
    }
    
    .modal-body {
        padding: 24px;
    }
    
    .modal-body p {
        margin-top: 0;
        margin-bottom: 16px;
        line-height: 1.5;
    }
    
    .modal-body .warning {
        color: #dc3545;
        font-weight: 500;
    }
    
    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--border-color);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }
    
    .btn-secondary {
        background-color: #f8f9fa;
        color: #212529;
        border: 1px solid #dee2e6;
    }
    
    .btn-secondary:hover {
        background-color: #e9ecef;
    }
    
    .btn-danger {
        background-color: #dc3545;
        color: white;
    }
    
    .btn-danger:hover {
        background-color: #c82333;
    }
</style>

<script>
    // Modal functions
    function confirmDelete() {
        document.getElementById('deleteModal').style.display = 'flex';
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Close modal when clicking the X or Cancel
        let closeButtons = document.querySelectorAll('.close-modal');
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('deleteModal').style.display = 'none';
            });
        });
        
        // Close modal when clicking outside of it
        window.addEventListener('click', function(event) {
            let modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });
</script>