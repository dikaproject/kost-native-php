<?php
// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get tenant information if exists
$stmt = $pdo->prepare("
    SELECT t.*, r.name as room_name, r.price, r.id as room_id
    FROM tenants t 
    JOIN rooms r ON t.room_id = r.id 
    WHERE t.user_id = ? AND t.status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$tenant = $stmt->fetch();

$page_title = "My Profile";
?>

<div class="page-content">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success_message'];
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>

    <div class="profile-header-section">
        <h1 class="page-title">My Profile</h1>
        <div class="profile-actions">
            <a href="index.php?page=edit-profile" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit Profile
            </a>
            <?php if ($tenant && $user['role'] !== 'admin'): ?>
                <a href="index.php?page=change-room" class="btn btn-secondary">
                    <i class="fas fa-exchange-alt"></i> Change Room
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Profile Overview Section -->
    <div class="profile-section">
        <div class="profile-header">
            <div class="profile-avatar">
                <img src="<?php echo $user['profile_image'] ? 'uploads/profiles/' . $user['profile_image'] : 'assets/images/default-avatar.jpg'; ?>" alt="<?php echo $user['first_name'] . ' ' . $user['last_name']; ?>">
            </div>
            <div class="profile-info">
                <h2><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h2>
                <p class="profile-role"><?php echo ucfirst($user['role']); ?></p>
                <p class="profile-location"><?php echo $user['city'] && $user['country'] ? $user['city'] . ', ' . $user['country'] : 'Location not specified'; ?></p>
                <?php if (isset($user['bio']) && $user['bio']): ?>
                    <p class="profile-bio"><?php echo $user['bio']; ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Personal Information Section -->
    <div class="profile-section">
        <h3 class="section-title">Personal Information</h3>
        
        <div class="form-grid">
            <div class="form-group">
                <div class="form-label">First Name</div>
                <div class="form-value"><?php echo $user['first_name']; ?></div>
            </div>
            
            <div class="form-group">
                <div class="form-label">Last Name</div>
                <div class="form-value"><?php echo $user['last_name']; ?></div>
            </div>
            
            <div class="form-group">
                <div class="form-label">Email</div>
                <div class="form-value"><?php echo $user['email']; ?></div>
            </div>
            
            <div class="form-group">
                <div class="form-label">Phone</div>
                <div class="form-value"><?php echo $user['phone'] ?: 'Not specified'; ?></div>
            </div>
        </div>
    </div>
    
    <!-- Address Section -->
    <div class="profile-section">
        <h3 class="section-title">Address</h3>
        
        <div class="form-grid">
            <div class="form-group">
                <div class="form-label">Country</div>
                <div class="form-value"><?php echo $user['country'] ?: 'Not specified'; ?></div>
            </div>
            
            <div class="form-group">
                <div class="form-label">City</div>
                <div class="form-value"><?php echo $user['city'] ?: 'Not specified'; ?></div>
            </div>
            
            <div class="form-group">
                <div class="form-label">Street</div>
                <div class="form-value"><?php echo $user['street'] ?: 'Not specified'; ?></div>
            </div>
            
            <div class="form-group">
                <div class="form-label">Postal Code</div>
                <div class="form-value"><?php echo $user['postal_code'] ?: 'Not specified'; ?></div>
            </div>
        </div>
    </div>
    
    <?php if ($tenant && $user['role'] !== 'admin'): ?>
    <!-- Room Information Section -->
    <div class="profile-section">
        <h3 class="section-title">Room Information</h3>
        
        <div class="room-info-container">
            <div class="room-info-details">
                <div class="form-grid">
                    <div class="form-group">
                        <div class="form-label">Room</div>
                        <div class="form-value"><?php echo $tenant['room_name']; ?></div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Monthly Rent</div>
                        <div class="form-value">IDR <?php echo number_format($tenant['price'], 0, ',', '.'); ?></div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Start Date</div>
                        <div class="form-value"><?php echo date('d F Y', strtotime($tenant['start_date'])); ?></div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Status</div>
                        <div class="form-value"><span class="status-badge active"><?php echo ucfirst($tenant['status']); ?></span></div>
                    </div>
                </div>
                
                <div class="room-actions">
                    <a href="index.php?page=room-detail&id=<?php echo $tenant['room_id']; ?>" class="btn btn-secondary btn-sm">
                        <i class="fas fa-eye"></i> View Room Details
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Security Section -->
    <div class="profile-section">
        <h3 class="section-title">Security</h3>
        
        <div class="security-actions">
            <a href="index.php?page=change-password" class="btn btn-secondary">
                <i class="fas fa-lock"></i> Change Password
            </a>
        </div>
    </div>
</div>

<style>
    /* Page content */
    .page-content {
        margin-bottom: 24px;
    }

    .profile-header-section {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .page-title {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 0;
        letter-spacing: -0.5px;
    }

    .profile-actions {
        display: flex;
        gap: 12px;
    }

    /* Alert Styles */
    .alert {
        padding: 16px;
        border-radius: var(--border-radius-md);
        margin-bottom: 24px;
    }

    .alert-success {
        background-color: rgba(46, 125, 50, 0.1);
        color: #2e7d32;
        border: 1px solid rgba(46, 125, 50, 0.2);
    }

    /* Profile sections */
    .profile-section {
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        border: 1px solid var(--border-color);
        padding: 24px;
        margin-bottom: 24px;
        position: relative;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
    }

    .profile-section:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .profile-header {
        display: flex;
        align-items: center;
        gap: 24px;
        margin-bottom: 24px;
    }

    .profile-avatar {
        width: 96px;
        height: 96px;
        border-radius: 50%;
        overflow: hidden;
        box-shadow: var(--shadow-md);
        border: 3px solid white;
    }

    .profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profile-info {
        flex: 1;
    }

    .profile-info h2 {
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 8px;
        letter-spacing: -0.5px;
    }

    .profile-role {
        color: var(--accent-color);
        font-weight: 500;
        margin-bottom: 4px;
    }

    .profile-location {
        color: var(--text-secondary);
        font-size: 14px;
        margin-bottom: 8px;
    }

    .profile-bio {
        color: var(--text-secondary);
        font-size: 14px;
        line-height: 1.6;
    }

    .section-title {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 24px;
        letter-spacing: -0.5px;
        position: relative;
        display: inline-block;
    }

    .section-title::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 0;
        width: 32px;
        height: 2px;
        background-color: var(--accent-color);
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
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

    .form-value {
        font-weight: 500;
        font-size: 16px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border-color);
    }

    /* Room Info */
    .room-info-container {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .room-info-details {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .room-actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 16px;
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-badge.active {
        background-color: rgba(46, 125, 50, 0.1);
        color: #2e7d32;
    }

    /* Security Section */
    .security-actions {
        display: flex;
        gap: 16px;
    }

    /* Button Styles */
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

    .btn i {
        margin-right: 8px;
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

    .btn-sm {
        padding: 8px 16px;
        font-size: 14px;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .profile-header {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .profile-info {
            text-align: center;
        }
        
        .profile-header-section {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        
        .profile-actions {
            width: 100%;
            justify-content: flex-start;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add subtle hover effects
        const sections = document.querySelectorAll('.profile-section');
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

