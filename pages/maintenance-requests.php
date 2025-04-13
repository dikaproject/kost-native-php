<?php
$page_title = "Maintenance Requests";

// Get tenant information if user is not admin
$tenant_id = null;
if ($user['role'] !== 'admin') {
    $stmt = $pdo->prepare("
        SELECT id FROM tenants 
        WHERE user_id = ? AND status = 'active'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $tenant_id = $stmt->fetchColumn();
    
    // If not a tenant, show message
    if (!$tenant_id) {
        $_SESSION['error_message'] = "You must be an active tenant to submit maintenance requests.";
    }
}

// Get maintenance requests
$requests = [];
if ($user['role'] === 'admin') {
    // Admin sees all requests
    $stmt = $pdo->query("
        SELECT mr.*, 
               CONCAT(u.first_name, ' ', u.last_name) as tenant_name,
               r.name as room_name
        FROM maintenance_requests mr
        JOIN tenants t ON mr.tenant_id = t.id
        JOIN users u ON t.user_id = u.id
        JOIN rooms r ON mr.room_id = r.id
        ORDER BY 
            CASE 
                WHEN mr.status = 'pending' THEN 1
                WHEN mr.status = 'in_progress' THEN 2
                WHEN mr.status = 'completed' THEN 3
                WHEN mr.status = 'rejected' THEN 4
            END,
            CASE 
                WHEN mr.priority = 'urgent' THEN 1
                WHEN mr.priority = 'high' THEN 2
                WHEN mr.priority = 'medium' THEN 3
                WHEN mr.priority = 'low' THEN 4
            END,
            mr.created_at DESC
    ");
    $requests = $stmt->fetchAll();
} elseif ($tenant_id) {
    // Tenant sees only their requests
    $stmt = $pdo->prepare("
        SELECT mr.*, 
               CONCAT(u.first_name, ' ', u.last_name) as tenant_name,
               r.name as room_name
        FROM maintenance_requests mr
        JOIN tenants t ON mr.tenant_id = t.id
        JOIN users u ON t.user_id = u.id
        JOIN rooms r ON mr.room_id = r.id
        WHERE mr.tenant_id = ?
        ORDER BY mr.created_at DESC
    ");
    $stmt->execute([$tenant_id]);
    $requests = $stmt->fetchAll();
}

// Handle status update (admin only)
if ($user['role'] === 'admin' && isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $request_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if (in_array($action, ['approve', 'complete', 'reject'])) {
        $status = '';
        switch ($action) {
            case 'approve':
                $status = 'in_progress';
                break;
            case 'complete':
                $status = 'completed';
                break;
            case 'reject':
                $status = 'rejected';
                break;
        }
        
        try {
            $stmt = $pdo->prepare("
                UPDATE maintenance_requests 
                SET status = ?, updated_at = NOW(), completed_at = ?
                WHERE id = ?
            ");
            
            $completed_at = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
            $stmt->execute([$status, $completed_at, $request_id]);
            
            // Create notification for tenant
            $stmt = $pdo->prepare("
                SELECT t.user_id, mr.request_type
                FROM maintenance_requests mr
                JOIN tenants t ON mr.tenant_id = t.id
                WHERE mr.id = ?
            ");
            $stmt->execute([$request_id]);
            $request_info = $stmt->fetch();
            
            if ($request_info) {
                $message = "Your maintenance request for {$request_info['request_type']} has been ";
                switch ($status) {
                    case 'in_progress':
                        $message .= "approved and is now in progress.";
                        break;
                    case 'completed':
                        $message .= "completed.";
                        break;
                    case 'rejected':
                        $message .= "rejected.";
                        break;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (
                        recipient_id, sender_id, type, message, 
                        is_read, created_at, updated_at
                    ) VALUES (
                        ?, ?, 'maintenance', ?, 0, NOW(), NOW()
                    )
                ");
                $stmt->execute([$request_info['user_id'], $_SESSION['user_id'], $message]);
            }
            
            $_SESSION['success_message'] = "Maintenance request updated successfully.";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
        }
        
        // Redirect to refresh the page
        header('Location: index.php?page=maintenance-requests');
        exit;
    }
}
?>

<div class="page-content">
    <div class="page-header">
        <h1 class="page-title">Maintenance Requests</h1>
        <?php if ($user['role'] !== 'admin' && $tenant_id): ?>
            <a href="index.php?page=add-maintenance-request" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Request
            </a>
        <?php endif; ?>
    </div>
    
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
    
    <?php if ($user['role'] !== 'admin' && !$tenant_id): ?>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <h3>No Active Room</h3>
                    <p>You need to have an active room to submit maintenance requests.</p>
                    <a href="index.php?page=rooms" class="btn btn-primary">Browse Rooms</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <?php echo $user['role'] === 'admin' ? 'All Maintenance Requests' : 'Your Maintenance Requests'; ?>
                </h2>
                <div class="card-tools">
                    <div class="search-container">
                        <input type="text" id="requestSearch" placeholder="Search requests..." class="search-input">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                    <div class="filter-container">
                        <select id="statusFilter" class="filter-select">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($requests)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <h3>No Maintenance Requests</h3>
                        <p>You haven't submitted any maintenance requests yet.</p>
                        <?php if ($user['role'] !== 'admin'): ?>
                            <a href="index.php?page=add-maintenance-request" class="btn btn-primary">Submit Request</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table" id="requestsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <th>Tenant</th>
                                    <?php endif; ?>
                                    <th>Room</th>
                                    <th>Type</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr data-status="<?php echo $request['status']; ?>">
                                        <td><?php echo $request['id']; ?></td>
                                        <?php if ($user['role'] === 'admin'): ?>
                                            <td><?php echo $request['tenant_name']; ?></td>
                                        <?php endif; ?>
                                        <td><?php echo $request['room_name']; ?></td>
                                        <td><?php echo $request['request_type']; ?></td>
                                        <td>
                                            <span class="priority-badge priority-<?php echo $request['priority']; ?>">
                                                <?php echo ucfirst($request['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $request['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($request['created_at'])); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="index.php?page=view-maintenance-request&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($user['role'] === 'admin' && $request['status'] === 'pending'): ?>
                                                    <a href="index.php?page=maintenance-requests&action=approve&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to approve this request?');">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                    <a href="index.php?page=maintenance-requests&action=reject&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to reject this request?');">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($user['role'] === 'admin' && $request['status'] === 'in_progress'): ?>
                                                    <a href="index.php?page=maintenance-requests&action=complete&id=<?php echo $request['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to mark this request as completed?');">
                                                        <i class="fas fa-check-double"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    /* Page content */
    .page-content {
        margin-bottom: 24px;
    }

    .page-header {
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

    .alert-danger {
        background-color: rgba(211, 47, 47, 0.1);
        color: #d32f2f;
        border: 1px solid rgba(211, 47, 47, 0.2);
    }

    /* Card Styles */
    .card {
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        border: 1px solid var(--border-color);
        margin-bottom: 24px;
        overflow: hidden;
    }

    .card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .card-title {
        font-size: 18px;
        font-weight: 600;
        margin: 0;
    }

    .card-tools {
        display: flex;
        gap: 16px;
        align-items: center;
    }

    .search-container {
        position: relative;
    }

    .search-input {
        padding: 8px 16px 8px 36px;
        border: 1px solid var(--border-color);
        border-radius: 50px;
        font-size: 14px;
        width: 200px;
        transition: var(--transition);
    }

    .search-input:focus {
        outline: none;
        border-color: var(--accent-color);
        width: 250px;
    }

    .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-secondary);
    }

    .filter-select {
        padding: 8px 16px;
        border: 1px solid var(--border-color);
        border-radius: 50px;
        font-size: 14px;
        background-color: var(--card-bg);
        transition: var(--transition);
    }

    .filter-select:focus {
        outline: none;
        border-color: var(--accent-color);
    }

    .card-body {
        padding: 24px;
    }

    /* Empty State */
    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 48px 24px;
        text-align: center;
    }

    .empty-state-icon {
        font-size: 48px;
        color: var(--text-secondary);
        margin-bottom: 16px;
        opacity: 0.5;
    }

    .empty-state h3 {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .empty-state p {
        color: var(--text-secondary);
        margin-bottom: 24px;
    }

    /* Table Styles */
    .table-responsive {
        overflow-x: auto;
    }

    .table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .table th, .table td {
        padding: 12px 16px;
        text-align: left;
    }

    .table th {
        background-color: var(--sidebar-bg);
        font-weight: 600;
        color: var(--text-primary);
        position: sticky;
        top: 0;
    }

    .table tbody tr {
        transition: var(--transition);
    }

    .table tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    .table td {
        border-bottom: 1px solid var(--border-color);
    }

    /* Status Badge */
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-pending {
        background-color: rgba(33, 150, 243, 0.1);
        color: #2196f3;
    }

    .status-in_progress {
        background-color: rgba(237, 108, 2, 0.1);
        color: #ed6c02;
    }

    .status-completed {
        background-color: rgba(46, 125, 50, 0.1);
        color: #2e7d32;
    }

    .status-rejected {
        background-color: rgba(211, 47, 47, 0.1);
        color: #d32f2f;
    }

    /* Priority Badge */
    .priority-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 500;
    }

    .priority-low {
        background-color: rgba(46, 125, 50, 0.1);
        color: #2e7d32;
    }

    .priority-medium {
        background-color: rgba(33, 150, 243, 0.1);
        color: #2196f3;
    }

    .priority-high {
        background-color: rgba(237, 108, 2, 0.1);
        color: #ed6c02;
    }

    .priority-urgent {
        background-color: rgba(211, 47, 47, 0.1);
        color: #d32f2f;
    }

    /* Action Buttons */
    .action-buttons {
        display: flex;
        gap: 8px;
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

    .btn-info {
        background-color: rgba(33, 150, 243, 0.1);
        color: #2196f3;
        border: 1px solid rgba(33, 150, 243, 0.2);
    }

    .btn-info:hover {
        background-color: rgba(33, 150, 243, 0.2);
        transform: translateY(-2px);
    }

    .btn-success {
        background-color: rgba(46, 125, 50, 0.1);
        color: #2e7d32;
        border: 1px solid rgba(46, 125, 50, 0.2);
    }

    .btn-success:hover {
        background-color: rgba(46, 125, 50, 0.2);
        transform: translateY(-2px);
    }

    .btn-danger {
        background-color: rgba(211, 47, 47, 0.1);
        color: #d32f2f;
        border: 1px solid rgba(211, 47, 47, 0.2);
    }

    .btn-danger:hover {
        background-color: rgba(211, 47, 47, 0.2);
        transform: translateY(-2px);
    }

    .btn-sm {
        padding: 8px;
        font-size: 14px;
    }

    .btn-sm i {
        margin-right: 0;
    }

    @media (max-width: 768px) {
        .page-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        
        .card-tools {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }
        
        .search-input, .search-input:focus {
            width: 100%;
        }
        
        .filter-select {
            width: 100%;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Request search functionality
        const requestSearch = document.getElementById('requestSearch');
        const statusFilter = document.getElementById('statusFilter');
        const requestsTable = document.getElementById('requestsTable');
        
        if (requestSearch && statusFilter && requestsTable) {
            const tableRows = requestsTable.querySelectorAll('tbody tr');
            
            function filterTable() {
                const searchTerm = requestSearch.value.toLowerCase();
                const statusValue = statusFilter.value;
                
                tableRows.forEach(row => {
                    const type = row.cells[<?php echo $user['role'] === 'admin' ? 3 : 2; ?>].textContent.toLowerCase();
                    const status = row.getAttribute('data-status');
                    
                    const matchesSearch = type.includes(searchTerm);
                    const matchesStatus = statusValue === '' || status === statusValue;
                    
                    row.style.display = matchesSearch && matchesStatus ? '' : 'none';
                });
            }
            
            requestSearch.addEventListener('input', filterTable);
            statusFilter.addEventListener('change', filterTable);
        }
    });
</script>

