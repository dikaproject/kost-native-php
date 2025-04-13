<?php
$page_title = "Announcement Management";

// Check if user is admin
if ($user['role'] !== 'admin') {
   header('Location: index.php?page=dashboard');
   exit;
}

// Handle announcement deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
   $announcement_id = intval($_GET['delete']);
   
   try {
       // Begin transaction
       $pdo->beginTransaction();
       
       // Delete announcement images (and actual files)
       $stmt = $pdo->prepare("SELECT image_path FROM announcement_images WHERE announcement_id = ?");
       $stmt->execute([$announcement_id]);
       $images = $stmt->fetchAll();
       
       foreach($images as $image) {
           $image_path = 'uploads/announcements/' . $image['image_path'];
           if (file_exists($image_path)) {
               unlink($image_path);
           }
       }
       
       // Delete image records
       $stmt = $pdo->prepare("DELETE FROM announcement_images WHERE announcement_id = ?");
       $stmt->execute([$announcement_id]);
       
       // Delete announcement
       $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
       $stmt->execute([$announcement_id]);
       
       // Commit transaction
       $pdo->commit();
       
       $_SESSION['success_message'] = "Announcement deleted successfully.";
   } catch (Exception $e) {
       // Rollback transaction on error
       $pdo->rollBack();
       $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
   }
   
   // Redirect to refresh the page
   header('Location: index.php?page=admin-announcements');
   exit;
}

// Get all announcements with their primary image
$stmt = $pdo->query("
   SELECT a.*, u.first_name, u.last_name, 
   MIN(ai.image_path) as image_path 
   FROM announcements a
   JOIN users u ON a.created_by = u.id
   LEFT JOIN announcement_images ai ON a.id = ai.announcement_id
   GROUP BY a.id, a.title, a.content, a.created_by, a.created_at, 
            u.first_name, u.last_name
   ORDER BY a.created_at DESC
");
$announcements = $stmt->fetchAll();
?>

<div class="page-content">
   <div class="page-header">
       <h1 class="page-title">Announcement Management</h1>
       <a href="index.php?page=add-announcement" class="btn btn-primary">
           <i class="fas fa-plus"></i> Add New Announcement
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
           <h2 class="card-title">All Announcements</h2>
           <div class="card-tools">
               <div class="search-container">
                   <input type="text" id="announcementSearch" placeholder="Search announcements..." class="search-input">
                   <i class="fas fa-search search-icon"></i>
               </div>
           </div>
       </div>
       <div class="card-body">
           <?php if (empty($announcements)): ?>
               <div class="empty-state">
                   <div class="empty-state-icon">
                       <i class="fas fa-bullhorn"></i>
                   </div>
                   <h3>No announcements found</h3>
                   <p>You haven't created any announcements yet.</p>
                   <a href="index.php?page=add-announcement" class="btn btn-primary">
                       <i class="fas fa-plus"></i> Add New Announcement
                   </a>
               </div>
           <?php else: ?>
               <div class="table-responsive">
                   <table class="table" id="announcementsTable">
                       <thead>
                           <tr>
                               <th>ID</th>
                               <th>Image</th>
                               <th>Title</th>
                               <th>Date</th>
                               <th>Created By</th>
                               <th>Actions</th>
                           </tr>
                       </thead>
                       <tbody>
                           <?php foreach ($announcements as $announcement): ?>
                               <tr>
                                   <td><?php echo $announcement['id']; ?></td>
                                   <td>
                                       <div class="announcement-thumbnail">
                                           <img src="<?php echo !empty($announcement['image_path']) ? 'uploads/announcements/' . $announcement['image_path'] : 'assets/images/default-announcement.jpg'; ?>" alt="<?php echo $announcement['title']; ?>">
                                       </div>
                                   </td>
                                   <td><?php echo $announcement['title']; ?></td>
                                   <td><?php echo date('d M Y', strtotime($announcement['created_at'])); ?></td>
                                   <td><?php echo $announcement['first_name'] . ' ' . $announcement['last_name']; ?></td>
                                   <td>
                                       <div class="action-buttons">
                                           <a href="index.php?page=edit-announcement&id=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-secondary">
                                               <i class="fas fa-edit"></i>
                                           </a>
                                           <a href="index.php?page=announcement-detail&id=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-info">
                                               <i class="fas fa-eye"></i>
                                           </a>
                                           <a href="index.php?page=admin-announcements&delete=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this announcement?');">
                                               <i class="fas fa-trash"></i>
                                           </a>
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

   /* Announcement Thumbnail */
   .announcement-thumbnail {
       width: 60px;
       height: 60px;
       border-radius: var(--border-radius-md);
       overflow: hidden;
   }

   .announcement-thumbnail img {
       width: 100%;
       height: 100%;
       object-fit: cover;
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
       // Announcement search functionality
       const announcementSearch = document.getElementById('announcementSearch');
       const categoryFilter = document.getElementById('categoryFilter');
       const announcementsTable = document.getElementById('announcementsTable');
       const tableRows = announcementsTable.querySelectorAll('tbody tr');
       
       function filterTable() {
           const searchTerm = announcementSearch.value.toLowerCase();
           const categoryValue = categoryFilter.value;
           
           tableRows.forEach(row => {
               const title = row.cells[2].textContent.toLowerCase();
               const category = row.getAttribute('data-category');
               
               const matchesSearch = title.includes(searchTerm);
               const matchesCategory = categoryValue === '' || category === categoryValue;
               
               row.style.display = matchesSearch && matchesCategory ? '' : 'none';
           });
       }
       
       announcementSearch.addEventListener('input', filterTable);
       categoryFilter.addEventListener('change', filterTable);
   });
</script>

