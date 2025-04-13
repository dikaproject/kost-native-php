<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Aula Kost</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-bg: #f9f9f9;
            --card-bg: #fff;
            --sidebar-bg: #f5f5f5;
            --border-color: #e0e0e0;
            --text-primary: #333;
            --text-secondary: #666;
            --accent-color: #000;
            --success-color: #2e7d32;
            --warning-color: #ed6c02;
            --danger-color: #d32f2f;
            --hover-color: #f0f0f0;
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
            --border-radius-sm: 8px;
            --border-radius-md: 12px;
            --border-radius-lg: 16px;
            --border-radius-xl: 24px;
        }

        body {
            display: flex;
            background-color: var(--primary-bg);
            min-height: 100vh;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #aaa;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 240px;
            height: 100vh;
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            padding: 24px 0;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            transition: var(--transition);
            z-index: 100;
        }

        .sidebar-header {
            padding: 0 24px 24px;
            font-size: 24px;
            font-weight: bold;
            letter-spacing: -0.5px;
        }

        .sidebar-menu {
            margin-top: 20px;
        }

        .menu-category {
            padding: 10px 24px;
            font-size: 11px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 16px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 24px;
            color: var(--text-primary);
            text-decoration: none;
            transition: var(--transition);
            margin-bottom: 4px;
            border-left: 3px solid transparent;
        }

        .menu-item:hover {
            background-color: rgba(0, 0, 0, 0.03);
        }

        .menu-item.active {
            background-color: rgba(0, 0, 0, 0.05);
            border-left: 3px solid var(--accent-color);
            font-weight: 500;
        }

        .menu-item i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 16px;
            color: var(--text-secondary);
            transition: var(--transition);
        }

        .menu-item:hover i,
        .menu-item.active i {
            color: var(--accent-color);
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 240px;
            padding: 24px;
            width: calc(100% - 240px);
            transition: var(--transition);
        }

        /* Header Styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            background-color: var(--card-bg);
            border-radius: var(--border-radius-lg);
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            gap: 20px;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-color) 0%, #333 100%);
        }

        .header:hover {
            box-shadow: var(--shadow-md);
        }

        .search-bar {
            position: relative;
            flex: 1;
            max-width: 450px;
            margin: 0;
        }

        .search-bar input {
            width: 100%;
            padding: 14px 20px 14px 50px;
            border: 1px solid var(--border-color);
            border-radius: 50px;
            font-size: 14px;
            transition: var(--transition);
            background-color: var(--sidebar-bg);
        }

        .search-bar::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%23999" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>');
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            z-index: 1;
        }

        .search-bar input:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
            background-color: var(--card-bg);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .notification-wrapper {
            position: relative;
        }

        .notification {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background-color: var(--card-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            cursor: pointer;
            border: 1px solid var(--border-color);
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background-color: #f44336;
            color: white;
            font-size: 10px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }

        .notification:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            background-color: var(--hover-color);
        }

        /* Notification Dropdown */
        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 320px;
            background-color: var(--card-bg);
            border-radius: var(--border-radius-md);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            z-index: 1000;
            overflow: hidden;
            display: none;
            margin-top: 8px;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-dropdown-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .notification-dropdown-title {
            font-weight: 600;
            font-size: 16px;
        }

        .notification-dropdown-actions {
            display: flex;
            gap: 8px;
        }

        .notification-dropdown-action {
            font-size: 12px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: var(--transition);
        }

        .notification-dropdown-action:hover {
            color: var(--accent-color);
        }

        .notification-dropdown-list {
            max-height: 320px;
            overflow-y: auto;
        }

        .notification-dropdown-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .notification-dropdown-item:last-child {
            border-bottom: none;
        }

        .notification-dropdown-item:hover {
            background-color: var(--sidebar-bg);
        }

        .notification-dropdown-item.unread {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .notification-dropdown-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .notification-dropdown-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .notification-dropdown-content {
            flex: 1;
        }

        .notification-dropdown-message {
            font-size: 14px;
            line-height: 1.5;
            margin-bottom: 4px;
        }

        .notification-dropdown-time {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .notification-dropdown-footer {
            padding: 12px 16px;
            text-align: center;
            border-top: 1px solid var(--border-color);
        }

        .notification-dropdown-footer a {
            color: var(--accent-color);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .notification-dropdown-footer a:hover {
            text-decoration: underline;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            background-color: var(--sidebar-bg);
            padding: 8px 16px 8px 8px;
            border-radius: 50px;
            transition: var(--transition);
            border: 1px solid transparent;
        }

        .user-profile:hover {
            background-color: var(--hover-color);
            border-color: var(--border-color);
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 2px solid white;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .user-avatar:hover {
            transform: scale(1.05);
        }

        .user-info {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 14px;
        }

        .user-email {
            font-size: 12px;
            color: var(--text-secondary);
        }

        .profile-dropdown {
            margin-left: 4px;
            color: var(--text-secondary);
            transition: var(--transition);
        }

        .user-profile:hover .profile-dropdown {
            transform: rotate(180deg);
        }

        /* Card Styles */
        .card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            margin-bottom: 24px;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
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
        }

        .btn-secondary {
            background-color: var(--sidebar-bg);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background-color: var(--hover-color);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #b71c1c;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 14px;
        }

        .btn i {
            margin-right: 0;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-md);
            font-size: 14px;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
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

        .table th,
        .table td {
            padding: 12px 16px;
            text-align: left;
        }

        .table th {
            background-color: var(--sidebar-bg);
            font-weight: 600;
            color: var(--text-primary);
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

        /* Mobile menu */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background-color: var(--accent-color);
            color: white;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-md);
            z-index: 1000;
            cursor: pointer;
            border: none;
            transition: var(--transition);
        }

        .mobile-menu-toggle:hover {
            transform: scale(1.1);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                padding: 20px 0;
            }

            .sidebar-header,
            .menu-item span,
            .menu-category {
                display: none;
            }

            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
                padding: 20px;
            }

            .menu-item {
                justify-content: center;
                padding: 15px;
            }

            .menu-item i {
                margin-right: 0;
            }

            .menu-item.active {
                border-left: none;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                margin: 5px auto;
                padding: 0;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: row;
                flex-wrap: wrap;
                gap: 16px;
                padding: 16px;
            }

            .search-bar {
                order: 2;
                width: 100%;
                max-width: 100%;
                margin: 0;
            }

            .header-right {
                order: 1;
                margin-left: auto;
            }
        }

        @media (max-width: 576px) {
            .user-profile {
                padding: 6px;
            }

            .user-info {
                display: none;
            }

            .profile-dropdown {
                display: none;
            }

            .mobile-menu-toggle {
                display: flex;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            Aula Kost
        </div>

        <div class="sidebar-menu">
        <?php if ($user['role'] === 'admin'): ?>
    <!-- Admin Menu -->
    <div class="menu-category">ADMIN MENU</div>
    <a href="index.php?page=admin-dashboard" class="menu-item <?php echo $page === 'admin-dashboard' ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
    </a>
    <a href="index.php?page=income-overview" class="menu-item <?php echo $page === 'income-overview' ? 'active' : ''; ?>">
        <i class="fas fa-chart-line"></i>
        <span>Income Overview</span>
    </a>
    <a href="index.php?page=admin-rooms" class="menu-item <?php echo $page === 'add-room' || $page === 'edit-room' ? 'active' : ''; ?>">
        <i class="fas fa-door-open"></i>
        <span>Manage Rooms</span>
    </a>
    <a href="index.php?page=admin-announcements" class="menu-item <?php echo $page === 'add-announcement' || $page === 'edit-announcement' ? 'active' : ''; ?>">
        <i class="fas fa-bullhorn"></i>
        <span>Manage Announcements</span>
    </a>
    <a href="index.php?page=admin-notifications" class="menu-item <?php echo $page === 'add-notification' ? 'active' : ''; ?>">
        <i class="fas fa-bell"></i>
        <span>Manage Notifications</span>
    </a>
    <a href="index.php?page=admin-user" class="menu-item <?php echo $page === 'admin-user' || $page === 'admin-user-detail' ? 'active' : ''; ?>">
        <i class="fas fa-users"></i>
        <span>User Management</span>
    </a>
    <a href="index.php?page=admin-chat" class="menu-item <?php echo $page === 'admin-chat' ? 'active' : ''; ?>">
        <i class="fas fa-comments"></i>
        <span>Chat with Tenants</span>
    </a>
<?php else: ?>
                <!-- Tenant Menu -->
                <div class="menu-category">MAIN MENU</div>
                <a href="index.php?page=dashboard" class="menu-item <?php echo $page === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Overview</span>
                </a>
                <a href="index.php?page=payments" class="menu-item <?php echo $page === 'payments' ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i>
                    <span>Payment</span>
                </a>
                <a href="index.php?page=rooms" class="menu-item <?php echo $page === 'rooms' || $page === 'room-detail' ? 'active' : ''; ?>">
                    <i class="fas fa-door-open"></i>
                    <span>Rooms</span>
                </a>

                <div class="menu-category">OTHER</div>
                <a href="index.php?page=announcements" class="menu-item <?php echo $page === 'announcements' || $page === 'announcement-detail' ? 'active' : ''; ?>">
                    <i class="fas fa-bullhorn"></i>
                    <span>Announcement</span>
                </a>
                <a href="index.php?page=chat" class="menu-item <?php echo $page === 'chat' ? 'active' : ''; ?>">
                    <i class="fas fa-comments"></i>
                    <span>Chat</span>
                </a>
            <?php endif; ?>

            <div class="menu-category">GENERAL</div>
            <a href="index.php?page=profile" class="menu-item <?php echo $page === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i>
                <span>Profile</span>
            </a>
            <a href="index.php?page=logout" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Log Out</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="search-bar">
                <input type="text" placeholder="Search Anything in Aula Kost">
            </div>

            <div class="header-right">
            <div class="notification-wrapper">
            <a href="index.php?page=notifications" class="notification">
                <i class="far fa-bell"></i>
            </a>
            <?php
            // Get unread notifications count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE recipient_id = ? AND is_read = 0");
            $stmt->execute([$_SESSION['user_id']]);
            $notification_count = $stmt->fetchColumn();
            
            if ($notification_count > 0):
            ?>
            <div class="notification-badge"><?php echo $notification_count; ?></div>
            <?php endif; ?>
        </div>

                <div class="user-profile">
                    <div class="user-avatar">
                        <img src="<?php echo $user['profile_image'] ? 'uploads/profiles/' . $user['profile_image'] : 'assets/images/default-avatar.jpg'; ?>" alt="User">
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></div>
                        <div class="user-email"><?php echo $user['email']; ?></div>
                    </div>
                    <div class="profile-dropdown">
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Get reference to notification elements
                const notificationWrapper = document.querySelector('.notification-wrapper');
                const notificationLink = document.querySelector('.notification');

                if (notificationWrapper && notificationLink) {
                    // Remove any existing event listeners (just to be safe)
                    const newNotificationLink = notificationLink.cloneNode(true);
                    notificationLink.parentNode.replaceChild(newNotificationLink, notificationLink);

                    // Add proper event listener to the notification link
                    newNotificationLink.addEventListener('click', function(e) {
                        e.preventDefault(); // Prevent default to ensure our navigation works
                        window.location.href = 'index.php?page=notifications';
                    });

                    // Add event listener to the badge if it exists
                    const notificationBadge = document.querySelector('.notification-badge');
                    if (notificationBadge) {
                        notificationBadge.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            window.location.href = 'index.php?page=notifications';
                        });
                    }
                }
            });
        </script>
</body>

</html>