<?php
$page_title = "User Management";

// Process delete request
if (isset($_POST['delete_user']) && !empty($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    
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
    
    // Redirect to refresh the page
    header("Location: index.php?page=admin-user");
    exit;
}

// Get search term
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// Prepare query with optional filters
$query = "SELECT u.*, 
          (SELECT COUNT(*) FROM tenants t WHERE t.user_id = u.id AND t.status = 'active') as is_tenant,
          (SELECT r.name FROM rooms r JOIN tenants t ON r.id = t.room_id WHERE t.user_id = u.id AND t.status = 'active' LIMIT 1) as room_name
          FROM users u
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (
                u.first_name LIKE ? OR 
                u.last_name LIKE ? OR 
                u.email LIKE ? OR 
                u.phone LIKE ?
                )";
    $searchParam = "%" . $search . "%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($role_filter)) {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>

<div class="page-content">
    <div class="page-header">
        <h1 class="page-title">User Management</h1>
        <!-- <a href="index.php?page=add-user" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New User
        </a> -->
    </div>
    
    <!-- Success/Error Messages -->
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
            <h2 class="card-title">All Users</h2>
            <div class="card-tools">
                <div class="search-container">
                    <input type="text" id="userSearch" placeholder="Search users..." class="search-input">
                    <i class="fas fa-search search-icon"></i>
                </div>
                <div class="filter-container">
                    <select id="roleFilter" class="filter-select" onchange="filterUsers()">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admins</option>
                        <option value="tenant" <?php echo $role_filter === 'tenant' ? 'selected' : ''; ?>>Tenants</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="usersTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Room</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="6" class="no-results">No users found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr data-role="<?php echo $user['role']; ?>">
                                    <td>
                                        <div class="user-info">
                                            <div class="user-thumbnail">
                                                <?php if (!empty($user['profile_photo'])): ?>
                                                    <img src="uploads/profiles/<?php echo $user['profile_photo']; ?>" alt="<?php echo $user['first_name']; ?>">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder">
                                                        <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="user-details">
                                                <div class="user-name"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="contact-item" title="<?php echo $user['email']; ?>">
                                            <i class="fas fa-envelope"></i>
                                            <span class="contact-text"><?php echo substr($user['email'], 0, 20) . (strlen($user['email']) > 20 ? '...' : ''); ?></span>
                                        </div>
                                        <?php if (!empty($user['phone'])): ?>
                                        <div class="contact-item">
                                            <i class="fas fa-phone"></i>
                                            <span class="contact-text"><?php echo $user['phone']; ?></span>
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['is_tenant'] > 0): ?>
                                            <span class="status-badge status-available">
                                                <?php echo $user['room_name']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-maintenance">
                                                Not a tenant
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="index.php?page=admin-user-detail&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <button type="button" class="btn btn-sm btn-danger" title="Delete User" 
                                                       onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo addslashes($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
            <p>Are you sure you want to delete the user <span id="userName"></span>?</p>
            <p class="warning">This action cannot be undone. All user data will be permanently removed.</p>
        </div>
        <div class="modal-footer">
            <form method="POST" id="deleteForm">
                <input type="hidden" name="user_id" id="deleteUserId">
                <input type="hidden" name="delete_user" value="1">
                <button type="button" class="btn btn-secondary close-modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete User</button>
            </form>
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

    .no-results {
        text-align: center;
        padding: 32px;
        color: var(--text-secondary);
        font-style: italic;
    }

    /* User info cell */
    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-thumbnail {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        overflow: hidden;
        background-color: var(--sidebar-bg);
    }

    .user-thumbnail img {
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
        font-size: 18px;
    }

    .user-details {
        flex: 1;
    }

    .user-name {
        font-weight: 600;
        color: var(--text-primary);
    }

    /* Contact items */
    .contact-item {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 4px;
        color: var(--text-secondary);
        font-size: 13px;
    }

    .contact-item:last-child {
        margin-bottom: 0;
    }

    .contact-item i {
        color: var(--primary-color);
        font-size: 12px;
        width: 14px;
    }

    .contact-text {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 200px;
    }

    /* Status Badge */
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 500;
    }

    .role-admin {
        background-color: rgba(var(--primary-rgb), 0.1);
        color: var(--primary-color);
    }

    .role-tenant {
        background-color: rgba(108, 117, 125, 0.1);
        color: #6c757d;
    }

    .status-available {
        background-color: rgba(46, 125, 50, 0.1);
        color: #2e7d32;
    }

    .status-maintenance {
        background-color: rgba(237, 108, 2, 0.1);
        color: #ed6c02;
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

    /* Responsive styles */
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
        
        .table {
            display: block;
            overflow-x: auto;
        }
        
        .contact-text {
            max-width: 150px;
        }
    }
    
    @media (max-width: 576px) {
        .table th:nth-child(5),
        .table td:nth-child(5) {
            display: none;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // User search functionality
        const userSearch = document.getElementById('userSearch');
        const roleFilter = document.getElementById('roleFilter');
        const usersTable = document.getElementById('usersTable');
        const tableRows = usersTable.querySelectorAll('tbody tr');
        
        userSearch.addEventListener('input', filterUsers);
        roleFilter.addEventListener('change', filterUsers);
        
        function filterUsers() {
            const searchTerm = userSearch.value.toLowerCase();
            const roleValue = roleFilter.value;
            
            tableRows.forEach(row => {
                const userName = row.querySelector('.user-name').textContent.toLowerCase();
                const userEmail = row.querySelector('.contact-text').getAttribute('title').toLowerCase();
                const userRole = row.getAttribute('data-role');
                
                const matchesSearch = userName.includes(searchTerm) || userEmail.includes(searchTerm);
                const matchesRole = roleValue === '' || userRole === roleValue;
                
                row.style.display = matchesSearch && matchesRole ? '' : 'none';
            });
        }
        
        // Modal functions
        window.confirmDelete = function(userId, userName) {
            document.getElementById('userName').textContent = userName;
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
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