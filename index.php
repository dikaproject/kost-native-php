<?php
// Add this at the very beginning of the file, before any other code
ob_start();

// Start session
session_start();

// Database connection
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
  // Redirect to landing page instead of login
  header('Location: landing.php');
  exit;
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Check if user is admin
$is_admin = ($user['role'] === 'admin');

// Get the requested page
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// Define allowed pages
$allowed_pages = [
  'dashboard', 'rooms', 'room-detail', 'payments', 'announcements', 'announcement-detail',
  'chat', 'profile', 'logout', 'edit-profile', 'change-password', 'change-room',
  'profile-edit-modal', 'change-room-modal', 'notifications', 
  // Add Midtrans related pages
  'direct-book-room', 'midtrans-payment', 'midtrans-callback'
];

// Admin-only pages
$admin_pages = [
  'admin-dashboard', 'income-overview', 'add-room', 'edit-room', 'add-announcement', 
  'edit-announcement', 'add-notification', 'admin-chat', 'admin-rooms', 'admin-announcements', 'edit-notification',
  'admin-notifications', 'admin-user', 'admin-user-detail'
];

// Add admin pages to allowed pages if user is admin
if ($is_admin) {
  $allowed_pages = array_merge($allowed_pages, $admin_pages);
}

// Validate page
if (!in_array($page, $allowed_pages)) {
  $page = $is_admin ? 'admin-dashboard' : 'dashboard';
}

// Check if page requires admin privileges
if (in_array($page, $admin_pages) && !$is_admin) {
  // Redirect to dashboard
  header('Location: index.php?page=dashboard');
  exit;
}

// Include the requested page
$file_path = "pages/{$page}.php";

// Set page title
$page_titles = [
  'dashboard' => 'Dashboard',
  'rooms' => 'Rooms',
  'room-detail' => 'Room Detail',
  'payments' => 'Payments',
  'admin-announcements' => 'Announcements',
  'announcement-detail' => 'Announcement Detail',
  'chat' => 'Chat',
  'profile' => 'Profile',
  'edit-profile' => 'Edit Profile',
  'change-password' => 'Change Password',
  'change-room' => 'Change Room',
  'notifications' => 'Notifications',
  'admin-dashboard' => 'Admin Dashboard',
  'income-overview' => 'Income Overview',
  'admin-rooms' => 'Admin Room',
  'add-room' => 'Add Room',
  'edit-room' => 'Edit Room',
  'add-announcement' => 'Add Announcement',
  'edit-announcement' => 'Edit Announcement',
  'add-notification' => 'Add Notification',
  'edit-notification' => 'Edit Notification',
  'admin-notifications' => 'Admin Notifications',
  'admin-chat' => 'Admin Chat',
  // Add Midtrans related page titles
  'direct-book-room' => 'Book Room',
  'midtrans-payment' => 'Complete Payment',
  'midtrans-callback' => 'Payment Verification',
  'admin-user' => 'User Management',
  'admin-user-detail' => 'User Details'
];

$page_title = isset($page_titles[$page]) ? $page_titles[$page] : 'Aula Kost';

// Include header (except for modal pages)
if (!in_array($page, ['profile-edit-modal', 'change-room-modal'])) {
  include 'includes/header.php';
}

// Include the page content
if (file_exists($file_path)) {
  include $file_path;
} else {
  echo "<div class='container mt-5'><div class='alert alert-danger'>Page not found.</div></div>";
}

// Include footer (except for modal pages)
if (!in_array($page, ['profile-edit-modal', 'change-room-modal'])) {
  include 'includes/footer.php';
}
?>