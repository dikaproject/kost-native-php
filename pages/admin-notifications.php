<?php
$page_title = "Notification Management";

// Check if user is admin
if ($user['role'] !== 'admin') {
   header('Location: index.php?page=dashboard');
   exit;
}

// Handle notification deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
   $notification_id = intval($_GET['delete']);
   
   try {
       $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
       $stmt->execute([$notification_id]);
       
       $_SESSION['success_message'] = "Notification deleted successfully.";
   } catch (Exception $e) {
       $_SESSION['error_message'] = "An error occurred: " . $e->getMessage();
   }
   
   // Redirect to refresh the page
   header('Location: index.php?page=admin-notifications');
   exit;
}

// Get all notifications sent by admin
$stmt = $pdo->prepare("
   SELECT n.*, 
          u.first_name as recipient_first_name, 
          u.last_name as recipient_last_name,
          c.first_name as creator_first_name, 
          c.last_name as creator_last_name
   FROM notifications n
   JOIN users u ON n.recipient_id = u.id
   JOIN users c ON n.created_by = c.id
   WHERE n.created_by = ?
   ORDER BY n.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();
?>

<div class="page-content">
   <div class="page-header">
       <h1 class="page-title">Notification Management</h1>
       <a href="index.php?page=add-notification" class="btn btn-primary">
           <i class="fas fa-plus"></i> Send New Notification
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
           <h2 class="card-title">Sent Notifications</h2>
           <div class="card-tools">
               <div class="search-container">
                   <input type="text" id="notificationSearch" placeholder="Search notifications..." class="search-input">
                   <i class="fas fa-search search-icon"></i>
               </div>
           </div>
       </div>
       <div class="card-body">
           <?php if (empty($notifications)): ?>
               <div class="empty-state">
                   <div class="empty-state-icon">
                       <i class="fas fa-bell-slash"></i>
                   </div>
                   <h3>No notifications found</h3>
                   <p>You haven't sent any notifications yet.</p>
                   <a href="index.php?page=add-notification" class="btn btn-primary">
                       <i class="fas fa-plus"></i> Send New Notification
                   </a>
               </div>
           <?php else: ?>
               <div class="table-responsive">
                   <table class="table" id="notificationsTable">
                       <thead>
                           <tr>
                               <th>Title</th>
                               <th>Message</th>
                               <th>Recipient</th>
                               <th>Sent Date</th>
                               <th>Status</th>
                               <th>Actions</th>
                           </tr>
                       </thead>
                       <tbody>
                           <?php foreach ($notifications as $notification): ?>
                               <tr>
                                   <td><?php echo $notification['title']; ?></td>
                                   <td><?php echo substr($notification['message'], 0, 50) . (strlen($notification['message']) > 50 ? '...' : ''); ?></td>
                                   <td><?php echo $notification['recipient_first_name'] . ' ' . $notification['recipient_last_name']; ?></td>
                                   <td><?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?></td>
                                   <td>
                                       <span class="read-status <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                                           <?php echo $notification['is_read'] ? 'Read' : 'Unread'; ?>
                                       </span>
                                   </td>
                                   <td>
                                       <div class="action-buttons">
                                           <button class="btn btn-sm btn-info view-notification" data-id="<?php echo $notification['id']; ?>" data-title="<?php echo htmlspecialchars($notification['title']); ?>" data-message="<?php echo htmlspecialchars($notification['message']); ?>" data-recipient="<?php echo htmlspecialchars($notification['recipient_first_name'] . ' ' . $notification['recipient_last_name']); ?>" data-date="<?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?>">
                                               <i class="fas fa-eye"></i>
                                           </button>
                                           <a href="index.php?page=admin-notifications&delete=<?php echo $notification['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this notification?');">
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

<!-- Notification View Modal -->
<div class="modal" id="viewNotificationModal">
   <div class="modal-overlay"></div>
   <div class="modal-container">
       <div class="modal-header">
           <h3>Notification Details</h3>
           <button type="button" class="modal-close">&times;</button>
       </div>
       <div class="modal-body">
           <div class="notification-detail">
               <div class="detail-row">
                   <div class="detail-label">Title:</div>
                   <div class="detail-value" id="modal-title"></div>
               </div>
               <div class="detail-row">
                   <div class="detail-label">Message:</div>
                   <div class="detail-value message" id="modal-message"></div>
               </div>
               <div class="detail-row">
                   <div class="detail-label">Recipient:</div>
                   <div class="detail-value" id="modal-recipient"></div>
               </div>
               <div class="detail-row">
                   <div class="detail-label">Sent Date:</div>
                   <div class="detail-value" id="modal-date"></div>
               </div>
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

   /* Type Badge */
   .type-badge {
       display: inline-block;
       padding: 4px 12px;
       border-radius: 50px;
       font-size: 12px;
       font-weight: 500;
   }

   .type-announcement {
       background-color: rgba(33, 150, 243, 0.1);
       color: #2196f3;
   }

   .type-message {
       background-color: rgba(46, 125, 50, 0.1);
       color: #2e7d32;
   }

   .type-maintenance {
       background-color: rgba(237, 108, 2, 0.1);
       color: #ed6c02;
   }

   .type-payment {
       background-color: rgba(156, 39, 176, 0.1);
       color: #9c27b0;
   }

   .type-room_change {
       background-color: rgba(211, 47, 47, 0.1);
       color: #d32f2f;
   }

   .type-other {
       background-color: rgba(97, 97, 97, 0.1);
       color: #616161;
   }

   /* Read Status */
   .read-status {
       display: inline-block;
       padding: 4px 12px;
       border-radius: 50px;
       font-size: 12px;
       font-weight: 500;
   }

   .read-status.read {
       background-color: rgba(46, 125, 50, 0.1);
       color: #2e7d32;
   }

   .read-status.unread {
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

   /* Modal Styles */
   .modal {
       display: none;
       position: fixed;
       top: 0;
       left: 0;
       right: 0;
       bottom: 0;
       z-index: 1000;
   }

   .modal.show {
       display: block;
   }

   .modal-overlay {
       position: absolute;
       top: 0;
       left: 0;
       right: 0;
       bottom: 0;
       background-color: rgba(0, 0, 0, 0.5);
   }

   .modal-container {
       position: absolute;
       top: 50%;
       left: 50%;
       transform: translate(-50%, -50%);
       background-color: var(--card-bg);
       border-radius: var(--border-radius-lg);
       width: 90%;
       max-width: 500px;
       box-shadow: var(--shadow-md);
   }

   .modal-header {
       padding: 20px 24px;
       border-bottom: 1px solid var(--border-color);
       display: flex;
       justify-content: space-between;
       align-items: center;
   }

   .modal-header h3 {
       font-size: 18px;
       font-weight: 600;
       margin: 0;
   }

   .modal-close {
       background: none;
       border: none;
       font-size: 20px;
       cursor: pointer;
       color: var(--text-secondary);
       transition: var(--transition);
   }

   .modal-close:hover {
       color: var(--text-primary);
       transform: rotate(90deg);
   }

   .modal-body {
       padding: 24px;
   }

   /* Notification Detail */
   .notification-detail {
       display: flex;
       flex-direction: column;
       gap: 16px;
   }

   .detail-row {
       display: flex;
   }

   .detail-label {
       width: 100px;
       font-weight: 500;
       flex-shrink: 0;
   }

   .detail-value {
       flex: 1;
   }

   .detail-value.message {
       white-space: pre-wrap;
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
       // Search functionality
       const notificationSearch = document.getElementById('notificationSearch');
       if (notificationSearch) {
           notificationSearch.addEventListener('input', function() {
               const searchTerm = this.value.toLowerCase();
               const tableRows = document.querySelectorAll('#notificationsTable tbody tr');
               
               tableRows.forEach(row => {
                   const title = row.cells[0].textContent.toLowerCase();
                   const message = row.cells[1].textContent.toLowerCase();
                   const recipient = row.cells[2].textContent.toLowerCase();
                   
                   if (title.includes(searchTerm) || message.includes(searchTerm) || recipient.includes(searchTerm)) {
                       row.style.display = '';
                   } else {
                       row.style.display = 'none';
                   }
               });
           });
       }
       
       // View notification modal
       const viewButtons = document.querySelectorAll('.view-notification');
       const modal = document.getElementById('viewNotificationModal');
       const modalClose = document.querySelector('.modal-close');
       const modalOverlay = document.querySelector('.modal-overlay');
       
       function openModal(title, message, recipient, date) {
           document.getElementById('modal-title').textContent = title;
           document.getElementById('modal-message').textContent = message;
           document.getElementById('modal-recipient').textContent = recipient;
           document.getElementById('modal-date').textContent = date;
           modal.classList.add('show');
       }
       
       function closeModal() {
           modal.classList.remove('show');
       }
       
       viewButtons.forEach(button => {
           button.addEventListener('click', function() {
               const title = this.getAttribute('data-title');
               const message = this.getAttribute('data-message');
               const recipient = this.getAttribute('data-recipient');
               const date = this.getAttribute('data-date');
               openModal(title, message, recipient, date);
           });
       });
       
       if (modalClose) {
           modalClose.addEventListener('click', closeModal);
       }
       
       if (modalOverlay) {
           modalOverlay.addEventListener('click', closeModal);
       }
   });
</script>

