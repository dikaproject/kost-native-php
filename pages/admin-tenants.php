<?php
$page_title = "Tenant Management";

// Check if user is admin
if ($user['role'] !== 'admin') {
    header('Location: index.php?page=dashboard');
    exit;
}

// Handle tenant status change
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $tenant_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action === 'activate' || $action === 'deactivate') {
        $status = ($action === 'activate') ? 'active' : 'inactive';
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update tenant status
            $stmt = $pdo->prepare("
                UPDATE tenants 
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $tenant_id]);
            
            // If deactivating, update room status to available
            if ($action === 'deactivate') {
                $stmt = $pdo->prepare("
                    UPDATE rooms r
                    JOIN tenants t ON r.id = t.room_id
                    SET r.status = 'available'
                    WHERE t.id = ?
                ");
                $stmt->execute([$tenant_id]);
            }
            
            // If activating, update room status to occupied
            if ($action === 'activate') {
                $stmt = $pdo->prepare("
                    UPDATE rooms r
                    JOIN tenants t ON r.id = t.room_id
                    SET r.status = 'occupied'
                    WHERE t.id = ?
                ");
                $stmt->execute([$tenant_id]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success_message'] = "Tenant status updated successfully.";
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
        }
    }
    
    // Redirect to refresh the page
    header('Location: index.php?page=admin-tenants');
    exit;
}

// Get all tenants with additional info
$stmt = $pdo->query("
    SELECT t.*, 
           u.first_name, u.last_name, u.email, u.phone, u.profile_image,
           r.name as room_name, r.floor, r.price
    FROM tenants t
    JOIN users u ON t.user_id = u.id
    JOIN rooms r ON t.room_id = r.id
    ORDER BY t.status DESC, t.start_date DESC
");
$tenants = $stmt->fetchAll();
?>

<div class="page-content">
    <div class="page-header">
        <h1 class="page-title">Tenant Management</h1>
        <a href="index.php?page=add-tenant" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Tenant
        </a>
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
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">All Tenants</h2>
            <div class="card-tools">
                <div class="search-container">
                    <input type="text" id="tenantSearch" placeholder="Search tenants..." class="search-input">
                    <i class="fas fa-search search-icon"></i>
                </div>
                <div class="filter-container">
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="tenantsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tenant</th>
                            <th>Room</th>
                            <th>Start Date</th>
                            <th>Monthly Rent</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $tenant): ?>
                            <tr data-status="<?php echo $tenant['status']; ?>">
                                <td><?php echo $tenant['id']; ?></td>
                                <td>
                                    <div class="tenant-info">
                                        <div class="tenant-avatar">
                                            <img src="<?php echo $tenant['profile_image'] ? 'uploads/profiles/' . $tenant['profile_image'] : 'assets/images/default-avatar.jpg'; ?>" alt="<?php echo $tenant['first_name'] . ' ' . $tenant['last_name']; ?>">
                                        </div>
                                        <div class="tenant-details">
                                            <div class="tenant-name"><?php echo $tenant['first_name'] . ' ' . $tenant['last_name']; ?></div>
                                            <div class="tenant-contact"><?php echo $tenant['email']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $tenant['room_name']; ?> (<?php echo $tenant['floor']; ?>)</td>
                                <td><?php echo date('d M Y', strtotime($tenant['start_date'])); ?></td>
                                <td>IDR <?php echo number_format($tenant['price'], 0, ',', '.'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $tenant['status']; ?>">
                                        <?php echo ucfirst($tenant['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="index.php?page=edit-tenant&id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="index.php?page=view-tenant&id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($tenant['status'] === 'active'): ?>
                                            <a href="index.php?page=admin-tenants&action=deactivate&id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-warning" onclick="return confirm('Are you sure you want to deactivate this tenant?');">
                                                <i class="fas fa-user-slash"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="index.php?page=admin-tenants&action=activate&id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to activate this tenant?');">
                                                <i class="fas fa-user-check"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
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

    /* Tenant Info */
    .tenant-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .tenant-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        overflow: hidden;
    }

    .tenant-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .tenant-details {
        display: flex;
        flex-direction: column;
    }

    .tenant-name {
        font-weight: 500;
    }

    .tenant-contact {
        font-size: 12px;
        color: var(--text-secondary);
    }

    /* Status Badge */
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-active {
        background-color: rgba(46, 125, 50, 0.1);
        color: #2e7d32;
    }

    .status-inactive {
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

    .btn-secondary {
        background-color: var(--sidebar-bg);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }

    .btn-secondary:hover {
        background-color: var(--hover-color);
        transform: translateY(-2px);
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

    .btn-warning {
        background-color: rgba(237, 108, 2, 0.1);
        color: #ed6c02;
        border: 1px solid rgba(237, 108, 2, 0.2);
    }

    .btn-warning:hover {
        background-color: rgba(237, 108, 2, 0.2);
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
        // Tenant search functionality
        const tenantSearch = document.getElementById('tenantSearch');
        const statusFilter = document.getElementById('statusFilter');
        const tenantsTable = document.getElementById('tenantsTable');
        const tableRows = tenantsTable.querySelectorAll('tbody tr');
        
        function filterTable() {
            const searchTerm = tenantSearch.value.toLowerCase();
            const statusValue = statusFilter.value;
            
            tableRows.forEach(row => {
                const tenantName = row.querySelector('.tenant-name').textContent.toLowerCase();
                const tenantEmail = row.querySelector('.tenant-contact').textContent.toLowerCase();
                const roomName = row.cells[2].textContent.toLowerCase();
                const status = row.getAttribute('data-status');
                
                const matchesSearch = tenantName.includes(searchTerm) || tenantEmail.includes(searchTerm) || roomName.includes(searchTerm);
                const matchesStatus = statusValue === '' || status === statusValue;
                
                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            });
        }
        
        tenantSearch.addEventListener('input', filterTable);
        statusFilter.addEventListener('change', filterTable);
    });
</script>

