<?php
$page_title = "Room Management";

// Check if user is admin
if ($user['role'] !== 'admin') {
    header('Location: index.php?page=dashboard');
    exit;
}

// Handle room deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $room_id = intval($_GET['delete']);
    
    // Check if room is occupied
    $stmt = $pdo->prepare("SELECT status FROM rooms WHERE id = ?");
    $stmt->execute([$room_id]);
    $room_status = $stmt->fetchColumn();
    
    if ($room_status === 'occupied') {
        $_SESSION['error_message'] = "Cannot delete an occupied room. Please relocate the tenant first.";
    } else {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Delete room features
            $stmt = $pdo->prepare("DELETE FROM room_features WHERE room_id = ?");
            $stmt->execute([$room_id]);
            
            // Delete room images (and actual files)
            $stmt = $pdo->prepare("SELECT image_path FROM room_images WHERE room_id = ?");
            $stmt->execute([$room_id]);
            $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($images as $image) {
                $image_path = 'uploads/rooms/' . $image;
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM room_images WHERE room_id = ?");
            $stmt->execute([$room_id]);
            
            // Delete room
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->execute([$room_id]);
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success_message'] = "Room deleted successfully.";
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
        }
    }
    
    // Redirect to refresh the page
    header('Location: index.php?page=admin-rooms');
    exit;
}

// Get all rooms with additional info
$stmt = $pdo->query("
    SELECT r.*, 
           (SELECT COUNT(*) FROM room_features WHERE room_id = r.id) as feature_count,
           (SELECT COUNT(*) FROM room_images WHERE room_id = r.id) as image_count,
           (SELECT image_path FROM room_images WHERE room_id = r.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM rooms r
    ORDER BY r.id DESC
");
$rooms = $stmt->fetchAll();
?>

<div class="page-content">
    <div class="page-header">
        <h1 class="page-title">Room Management</h1>
        <a href="index.php?page=add-room" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Room
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
            <h2 class="card-title">All Rooms</h2>
            <div class="card-tools">
                <div class="search-container">
                    <input type="text" id="roomSearch" placeholder="Search rooms..." class="search-input">
                    <i class="fas fa-search search-icon"></i>
                </div>
                <div class="filter-container">
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Statuses</option>
                        <option value="available">Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table" id="roomsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Room Number</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Description</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room): ?>
                            <tr data-status="<?php echo $room['status']; ?>">
                                <td><?php echo $room['id']; ?></td>
                                <td>
                                    <div class="room-thumbnail">
                                        <img src="<?php echo $room['primary_image'] ? 'uploads/rooms/' . $room['primary_image'] : 'assets/images/default-room.jpg'; ?>" alt="<?php echo $room['name']; ?>">
                                    </div>
                                </td>
                                <td><?php echo $room['name']; ?></td>
                                <td>IDR <?php echo number_format($room['price'], 0, ',', '.'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $room['status']; ?>">
                                        <?php echo ucfirst($room['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo substr($room['description'], 0, 50) . (strlen($room['description']) > 50 ? '...' : ''); ?></td>
                                <td><?php echo date('d M Y H:i', strtotime($room['created_at'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="index.php?page=edit-room&id=<?php echo $room['id']; ?>" class="btn btn-sm btn-secondary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="index.php?page=room-detail&id=<?php echo $room['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="index.php?page=admin-rooms&delete=<?php echo $room['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this room?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
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

    /* Room Thumbnail */
    .room-thumbnail {
        width: 60px;
        height: 60px;
        border-radius: var(--border-radius-md);
        overflow: hidden;
    }

    .room-thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Status Badge */
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-available {
        background-color: rgba(46, 125, 50, 0.1);
        color: #2e7d32;
    }

    .status-occupied {
        background-color: rgba(33, 150, 243, 0.1);
        color: #2196f3;
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
        // Room search functionality
        const roomSearch = document.getElementById('roomSearch');
        const statusFilter = document.getElementById('statusFilter');
        const roomsTable = document.getElementById('roomsTable');
        const tableRows = roomsTable.querySelectorAll('tbody tr');
        
        function filterTable() {
            const searchTerm = roomSearch.value.toLowerCase();
            const statusValue = statusFilter.value;
            
            tableRows.forEach(row => {
                const roomNumber = row.cells[2].textContent.toLowerCase();
                const description = row.cells[5].textContent.toLowerCase();
                const status = row.getAttribute('data-status');
                
                const matchesSearch = roomNumber.includes(searchTerm) || description.includes(searchTerm);
                const matchesStatus = statusValue === '' || status === statusValue;
                
                row.style.display = matchesSearch && matchesStatus ? '' : 'none';
            });
        }
        
        roomSearch.addEventListener('input', filterTable);
        statusFilter.addEventListener('change', filterTable);
    });
</script>

