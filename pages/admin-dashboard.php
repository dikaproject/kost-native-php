<div class="card">
    <div class="card-header">
        <h1 class="card-title">Admin Dashboard</h1>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <?php
                    // Get total rooms
                    $stmt = $pdo->query("SELECT COUNT(*) FROM rooms");
                    $total_rooms = $stmt->fetchColumn();
                    ?>
                    <div class="stat-card-icon">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <div class="stat-card-content">
                        <div class="stat-card-title">Total Rooms</div>
                        <div class="stat-card-value"><?php echo $total_rooms; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <?php
                    // Get occupied rooms
                    $stmt = $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'occupied'");
                    $occupied_rooms = $stmt->fetchColumn();
                    ?>
                    <div class="stat-card-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-card-content">
                        <div class="stat-card-title">Occupied Rooms</div>
                        <div class="stat-card-value"><?php echo $occupied_rooms; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <?php
                    // Get total tenants
                    $stmt = $pdo->query("SELECT COUNT(*) FROM tenants WHERE status = 'active'");
                    $total_tenants = $stmt->fetchColumn();
                    ?>
                    <div class="stat-card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-card-content">
                        <div class="stat-card-title">Active Tenants</div>
                        <div class="stat-card-value"><?php echo $total_tenants; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <?php
                    // Get total income this month
                    $stmt = $pdo->query("SELECT SUM(amount) FROM payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())");
                    $monthly_income = $stmt->fetchColumn() ?: 0;
                    ?>
                    <div class="stat-card-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-card-content">
                        <div class="stat-card-title">Monthly Income</div>
                        <div class="stat-card-value">IDR <?php echo number_format($monthly_income, 0, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Payments</h2>
                <a href="index.php?page=income-overview" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tenant</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Method</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Get recent payments
                            $stmt = $pdo->query("
                                SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) AS tenant_name
                                FROM payments p
                                JOIN tenants t ON p.tenant_id = t.id
                                JOIN users u ON t.user_id = u.id
                                ORDER BY p.payment_date DESC
                                LIMIT 5
                            ");
                            $payments = $stmt->fetchAll();
                            
                            foreach ($payments as $payment):
                            ?>
                            <tr>
                                <td><?php echo $payment['tenant_name']; ?></td>
                                <td>IDR <?php echo number_format($payment['amount'], 0, ',', '.'); ?></td>
                                <td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                <td>
                                    <span class="badge <?php echo $payment['status'] === 'paid' ? 'badge-success' : 'badge-warning'; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent Messages</h2>
                <a href="index.php?page=admin-chat" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Sender</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Get recent messages
                            $stmt = $pdo->query("
                                SELECT m.*, CONCAT(u.first_name, ' ', u.last_name) AS sender_name
                                FROM messages m
                                JOIN users u ON m.sender_id = u.id
                                WHERE m.receiver_id = {$_SESSION['user_id']}
                                ORDER BY m.created_at DESC
                                LIMIT 5
                            ");
                            $messages = $stmt->fetchAll();
                            
                            foreach ($messages as $message):
                            ?>
                            <tr>
                                <td><?php echo $message['sender_name']; ?></td>
                                <td><?php echo strlen($message['message']) > 30 ? substr($message['message'], 0, 30) . '...' : $message['message']; ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($message['created_at'])); ?></td>
                                <td>
                                    <a href="index.php?page=admin-chat&user=<?php echo $message['sender_id']; ?>" class="btn btn-sm btn-primary">Reply</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Quick Actions</h2>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="index.php?page=add-room" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="quick-action-title">Add New Room</div>
                    </a>
                    <a href="index.php?page=add-announcement" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="quick-action-title">Create Announcement</div>
                    </a>
                    <a href="index.php?page=add-notification" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="quick-action-title">Send Notification</div>
                    </a>
                    <a href="index.php?page=admin-chat" class="quick-action-card">
                        <div class="quick-action-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="quick-action-title">Chat with Tenants</div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .row {
        display: flex;
        flex-wrap: wrap;
        margin: 0 -12px;
    }
    
    .col-md-3 {
        width: 25%;
        padding: 0 12px;
    }
    
    .col-md-6 {
        width: 50%;
        padding: 0 12px;
    }
    
    .col-md-12 {
        width: 100%;
        padding: 0 12px;
    }
    
    .stat-card {
        display: flex;
        align-items: center;
        background-color: var(--card-bg);
        border-radius: var(--border-radius-md);
        padding: 20px;
        box-shadow: var(--shadow-sm);
        margin-bottom: 24px;
        transition: var(--transition);
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
    }
    
    .stat-card-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background-color: var(--sidebar-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 16px;
        font-size: 24px;
    }
    
    .stat-card-content {
        flex: 1;
    }
    
    .stat-card-title {
        font-size: 14px;
        color: var(--text-secondary);
        margin-bottom: 4px;
    }
    
    .stat-card-value {
        font-size: 24px;
        font-weight: 600;
    }
    
    .card-body {
        padding: 0 24px 24px;
    }
    
    .badge {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .badge-success {
        background-color: rgba(46, 125, 50, 0.1);
        color: var(--success-color);
    }
    
    .badge-warning {
        background-color: rgba(237, 108, 2, 0.1);
        color: var(--warning-color);
    }
    
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
    }
    
    .quick-action-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background-color: var(--sidebar-bg);
        border-radius: var(--border-radius-md);
        text-decoration: none;
        color: var(--text-primary);
        transition: var(--transition);
    }
    
    .quick-action-card:hover {
        transform: translateY(-5px);
        background-color: var(--hover-color);
        box-shadow: var(--shadow-sm);
    }
    
    .quick-action-icon {
        font-size: 32px;
        margin-bottom: 16px;
        color: var(--accent-color);
    }
    
    .quick-action-title {
        font-weight: 500;
        text-align: center;
    }
    
    @media (max-width: 992px) {
        .col-md-3 {
            width: 50%;
        }
        
        .col-md-6 {
            width: 100%;
        }
        
        .quick-actions {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 576px) {
        .col-md-3 {
            width: 100%;
        }
        
        .quick-actions {
            grid-template-columns: 1fr;
        }
    }
</style>

