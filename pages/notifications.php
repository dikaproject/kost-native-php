<?php
$page_title = "Notifications";

// Get user notifications
$stmt = $pdo->prepare("
   SELECT n.*, 
          u.first_name as creator_first_name, 
          u.last_name as creator_last_name,
          u.profile_image
   FROM notifications n
   JOIN users u ON n.created_by = u.id
   WHERE n.recipient_id = ?
   ORDER BY n.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();

// Mark all as read if requested
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 1) {
   $stmt = $pdo->prepare("
       UPDATE notifications 
       SET is_read = 1
       WHERE recipient_id = ? AND is_read = 0
   ");
   $stmt->execute([$_SESSION['user_id']]);
   
   // Redirect to remove the query parameter
   header("Location: index.php?page=notifications");
   exit;
}

// Mark single notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
   $notification_id = intval($_GET['mark_read']);
   
   $stmt = $pdo->prepare("
       UPDATE notifications 
       SET is_read = 1
       WHERE id = ? AND recipient_id = ?
   ");
   $stmt->execute([$notification_id, $_SESSION['user_id']]);
   
   // Redirect to remove the query parameter
   header("Location: index.php?page=notifications");
   exit;
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
   $notification_id = intval($_GET['delete']);
   
   $stmt = $pdo->prepare("
       DELETE FROM notifications 
       WHERE id = ? AND recipient_id = ?
   ");
   $stmt->execute([$notification_id, $_SESSION['user_id']]);
   
   // Redirect to remove the query parameter
   header("Location: index.php?page=notifications");
   exit;
}

// Count unread notifications
$unread_count = 0;
foreach ($notifications as $notification) {
   if ($notification['is_read'] == 0) {
       $unread_count++;
   }
}
?>

<div class="page-content">
   <!-- Page Header with Back Button -->
   <div class="page-header">
       <a href="index.php?page=dashboard" class="back-button">
           <i class="fas fa-arrow-left"></i>
           <span>Back to Dashboard</span>
       </a>
       
       <?php if ($unread_count > 0): ?>
       <a href="index.php?page=notifications&mark_all_read=1" class="mark-all-read">
           <i class="fas fa-check-double"></i>
           Mark all as read
       </a>
       <?php endif; ?>
   </div>

   <!-- Notification Detail Container -->
   <div class="notification-detail-container">
       <!-- Main Content -->
       <div class="notification-main">
           <div class="notification-header">
               <h1 class="notification-title">Your Notifications</h1>
               <p class="notification-subtitle">Stay updated with the latest information</p>
           </div>
           
           <div class="notification-content">
               <?php if (empty($notifications)): ?>
                   <div class="empty-notifications">
                       <div class="empty-icon">
                           <i class="fas fa-bell-slash"></i>
                       </div>
                       <p>You don't have any notifications yet.</p>
                   </div>
               <?php else: ?>
                   <div class="notification-list">
                       <?php foreach ($notifications as $notification): ?>
                           <div class="notification-card <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                               <div class="notification-card-header">
                                   <div class="notification-sender">
                                       <div class="sender-avatar">
                                           <img src="<?php echo $notification['profile_image'] ? 'uploads/profiles/' . $notification['profile_image'] : 'assets/images/default-avatar.jpg'; ?>" alt="<?php echo $notification['creator_first_name'] . ' ' . $notification['creator_last_name']; ?>">
                                       </div>
                                       <div class="sender-info">
                                           <div class="sender-name"><?php echo $notification['creator_first_name'] . ' ' . $notification['creator_last_name']; ?></div>
                                           <div class="notification-time"><?php echo date('F j, Y, g:i a', strtotime($notification['created_at'])); ?></div>
                                       </div>
                                   </div>
                                   <div class="notification-actions">
                                       <?php if (!$notification['is_read']): ?>
                                           <a href="index.php?page=notifications&mark_read=<?php echo $notification['id']; ?>" class="notification-action" title="Mark as read">
                                               <i class="fas fa-check"></i>
                                           </a>
                                       <?php endif; ?>
                                       
                                       <a href="index.php?page=notifications&delete=<?php echo $notification['id']; ?>" class="notification-action" title="Delete" onclick="return confirm('Are you sure you want to delete this notification?');">
                                           <i class="fas fa-trash"></i>
                                       </a>
                                   </div>
                               </div>
                               <div class="notification-card-body">
                                   <div class="notification-card-title"><?php echo $notification['title']; ?></div>
                                   <div class="notification-card-message"><?php echo $notification['message']; ?></div>
                               </div>
                               <?php if (!$notification['is_read']): ?>
                                   <div class="unread-indicator"></div>
                               <?php endif; ?>
                           </div>
                       <?php endforeach; ?>
                   </div>
               <?php endif; ?>
           </div>
       </div>
       
       <!-- Sidebar -->
       <div class="notification-sidebar">
           <div class="sidebar-card">
               <h3 class="sidebar-card-title">Notification Stats</h3>
               
               <div class="info-item">
                   <div class="info-icon">
                       <i class="fas fa-bell"></i>
                   </div>
                   <div class="info-content">
                       <div class="info-label">Total Notifications</div>
                       <div class="info-value"><?php echo count($notifications); ?></div>
                   </div>
               </div>
               
               <div class="info-item">
                   <div class="info-icon">
                       <i class="fas fa-envelope-open"></i>
                   </div>
                   <div class="info-content">
                       <div class="info-label">Unread Notifications</div>
                       <div class="info-value"><?php echo $unread_count; ?></div>
                   </div>
               </div>
               
               <div class="info-item">
                   <div class="info-icon">
                       <i class="fas fa-calendar-alt"></i>
                   </div>
                   <div class="info-content">
                       <div class="info-label">Last Updated</div>
                       <div class="info-value"><?php echo !empty($notifications) ? date('F j, Y', strtotime($notifications[0]['created_at'])) : 'N/A'; ?></div>
                   </div>
               </div>
           </div>
           
           <div class="sidebar-card">
               <h3 class="sidebar-card-title">Quick Actions</h3>
               
               <div class="quick-actions">
                   <?php if ($unread_count > 0): ?>
                   <a href="index.php?page=notifications&mark_all_read=1" class="quick-action-btn">
                       <i class="fas fa-check-double"></i>
                       <span>Mark All as Read</span>
                   </a>
                   <?php endif; ?>
                   
                   <a href="index.php?page=dashboard" class="quick-action-btn">
                       <i class="fas fa-home"></i>
                       <span>Go to Dashboard</span>
                   </a>
                   
                   <a href="index.php?page=chat" class="quick-action-btn">
                       <i class="fas fa-comments"></i>
                       <span>Go to Chat</span>
                   </a>
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

   /* Page Header */
   .page-header {
       display: flex;
       justify-content: space-between;
       align-items: center;
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

   .mark-all-read {
       display: flex;
       align-items: center;
       gap: 8px;
       padding: 10px 16px;
       background-color: var(--sidebar-bg);
       border: 1px solid var(--border-color);
       border-radius: 50px;
       font-size: 14px;
       color: var(--text-primary);
       text-decoration: none;
       transition: var(--transition);
   }

   .mark-all-read:hover {
       background-color: var(--hover-color);
       transform: translateY(-2px);
   }

   /* Notification Detail Layout */
   .notification-detail-container {
       display: grid;
       grid-template-columns: 1fr;
       gap: 24px;
   }

   @media (min-width: 1024px) {
       .notification-detail-container {
           grid-template-columns: 1.2fr 0.8fr;
           align-items: start;
       }
   }

   .notification-main {
       display: flex;
       flex-direction: column;
       gap: 24px;
   }

   .notification-header {
       background-color: var(--card-bg);
       border-radius: var(--border-radius-lg);
       padding: 24px;
       box-shadow: var(--shadow-sm);
       border: 1px solid var(--border-color);
   }

   .notification-title {
       font-size: 28px;
       font-weight: 700;
       margin-bottom: 8px;
       letter-spacing: -0.5px;
   }

   .notification-subtitle {
       color: var(--text-secondary);
       font-size: 16px;
   }

   .notification-content {
       background-color: var(--card-bg);
       border-radius: var(--border-radius-lg);
       padding: 24px;
       box-shadow: var(--shadow-sm);
       border: 1px solid var(--border-color);
   }

   /* Empty Notifications */
   .empty-notifications {
       display: flex;
       flex-direction: column;
       align-items: center;
       justify-content: center;
       padding: 48px 24px;
       text-align: center;
   }

   .empty-icon {
       font-size: 48px;
       color: var(--text-secondary);
       margin-bottom: 16px;
       opacity: 0.5;
   }

   .empty-notifications p {
       color: var(--text-secondary);
       font-size: 16px;
   }

   /* Notification List */
   .notification-list {
       display: flex;
       flex-direction: column;
       gap: 16px;
   }

   /* Notification Card */
   .notification-card {
       background-color: var(--sidebar-bg);
       border-radius: var(--border-radius-md);
       overflow: hidden;
       box-shadow: var(--shadow-sm);
       transition: var(--transition);
       border: 1px solid var(--border-color);
       position: relative;
   }

   .notification-card:hover {
       transform: translateY(-5px);
       box-shadow: var(--shadow-md);
   }

   .notification-card.unread {
       background-color: rgba(255, 255, 255, 0.9);
       border-left: 3px solid var(--accent-color);
   }

   .notification-card-header {
       display: flex;
       justify-content: space-between;
       align-items: center;
       padding: 16px;
       border-bottom: 1px solid var(--border-color);
   }

   .notification-sender {
       display: flex;
       align-items: center;
       gap: 12px;
   }

   .sender-avatar {
       width: 40px;
       height: 40px;
       border-radius: 50%;
       overflow: hidden;
   }

   .sender-avatar img {
       width: 100%;
       height: 100%;
       object-fit: cover;
   }

   .sender-info {
       display: flex;
       flex-direction: column;
   }

   .sender-name {
       font-weight: 600;
       font-size: 14px;
   }

   .notification-time {
       font-size: 12px;
       color: var(--text-secondary);
   }

   .notification-actions {
       display: flex;
       gap: 8px;
   }

   .notification-action {
       width: 32px;
       height: 32px;
       border-radius: 50%;
       display: flex;
       align-items: center;
       justify-content: center;
       background-color: var(--card-bg);
       color: var(--text-primary);
       text-decoration: none;
       transition: var(--transition);
       border: 1px solid var(--border-color);
   }

   .notification-action:hover {
       background-color: var(--hover-color);
       transform: translateY(-2px);
   }

   .notification-card-body {
       padding: 16px;
   }

   .notification-card-title {
       font-weight: 600;
       font-size: 16px;
       margin-bottom: 8px;
   }

   .notification-card-message {
       font-size: 14px;
       line-height: 1.6;
       color: var(--text-secondary);
   }

   .unread-indicator {
       position: absolute;
       top: 16px;
       right: 16px;
       width: 8px;
       height: 8px;
       border-radius: 50%;
       background-color: var(--accent-color);
   }

   /* Sidebar */
   .notification-sidebar {
       display: flex;
       flex-direction: column;
       gap: 24px;
   }

   .sidebar-card {
       background-color: var(--card-bg);
       border-radius: var(--border-radius-lg);
       padding: 24px;
       box-shadow: var(--shadow-sm);
       border: 1px solid var(--border-color);
   }

   .sidebar-card-title {
       font-size: 20px;
       font-weight: 600;
       margin-bottom: 16px;
       padding-bottom: 12px;
       border-bottom: 1px solid var(--border-color);
   }

   .info-item {
       display: flex;
       align-items: center;
       gap: 12px;
       margin-bottom: 16px;
   }

   .info-item:last-child {
       margin-bottom: 0;
   }

   .info-icon {
       width: 44px;
       height: 44px;
       border-radius: 50%;
       background-color: var(--sidebar-bg);
       display: flex;
       align-items: center;
       justify-content: center;
       flex-shrink: 0;
   }

   .info-icon i {
       color: var(--accent-color);
       font-size: 18px;
   }

   .info-content {
       flex: 1;
   }

   .info-label {
       font-size: 14px;
       color: var(--text-secondary);
       margin-bottom: 4px;
   }

   .info-value {
       font-weight: 600;
       font-size: 16px;
   }

   /* Quick Actions */
   .quick-actions {
       display: flex;
       flex-direction: column;
       gap: 12px;
   }

   .quick-action-btn {
       display: flex;
       align-items: center;
       gap: 12px;
       padding: 12px 16px;
       background-color: var(--sidebar-bg);
       border: 1px solid var(--border-color);
       border-radius: var(--border-radius-md);
       color: var(--text-primary);
       text-decoration: none;
       transition: var(--transition);
   }

   .quick-action-btn:hover {
       background-color: var(--hover-color);
       transform: translateX(5px);
   }

   .quick-action-btn i {
       color: var(--accent-color);
       width: 20px;
       text-align: center;
   }

   /* Responsive */
   @media (max-width: 768px) {
       .page-header {
           flex-direction: column;
           align-items: flex-start;
           gap: 16px;
       }
       
       .notification-card-header {
           flex-direction: column;
           align-items: flex-start;
           gap: 12px;
       }
       
       .notification-actions {
           align-self: flex-end;
       }
   }
</style>

<script>
   document.addEventListener('DOMContentLoaded', function() {
       // Add subtle hover effects
       const cards = document.querySelectorAll('.notification-card, .sidebar-card');
       cards.forEach(card => {
           card.addEventListener('mouseenter', function() {
               this.style.transform = 'translateY(-5px)';
               this.style.boxShadow = 'var(--shadow-md)';
           });
           card.addEventListener('mouseleave', function() {
               this.style.transform = 'translateY(0)';
               this.style.boxShadow = 'var(--shadow-sm)';
           });
       });
   });
</script>

