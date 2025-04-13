<?php
$page_title = "Dashboard";

// Get user information first
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Check if user has a room_id assigned
$has_room_from_user = !empty($user['room_id']);

// Get tenant information - try both methods to ensure we find the tenant
$tenant = null;

// Method 1: Check tenants table directly
$stmt = $pdo->prepare("
    SELECT t.*, r.name as room_name, r.price, r.id as room_id
    FROM tenants t 
    JOIN rooms r ON t.room_id = r.id 
    WHERE t.user_id = ? AND t.status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$tenant = $stmt->fetch();

// Method 2: If no tenant found but user has room_id, get room info from that
if (!$tenant && $has_room_from_user) {
    $stmt = $pdo->prepare("
        SELECT r.*, r.name as room_name, r.id as room_id
        FROM rooms r
        WHERE r.id = ?
    ");
    $stmt->execute([$user['room_id']]);
    $room = $stmt->fetch();
    
    if ($room) {
        // Create a tenant-like object with the necessary information
        $tenant = [
            'id' => 0, // Placeholder ID
            'user_id' => $_SESSION['user_id'],
            'room_id' => $room['id'],
            'room_name' => $room['name'],
            'price' => $room['price'],
            'start_date' => date('Y-m-d') // Use today as start date if not available
        ];
        
        // Check if there's an actual tenant record we missed
        $stmt = $pdo->prepare("
            SELECT * FROM tenants 
            WHERE user_id = ? AND room_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $room['id']]);
        $actual_tenant = $stmt->fetch();
        
        if ($actual_tenant) {
            $tenant['id'] = $actual_tenant['id'];
            if (!empty($actual_tenant['start_date'])) {
                $tenant['start_date'] = $actual_tenant['start_date'];
            }
        } else {
            // Create a tenant record if it doesn't exist
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO tenants (user_id, room_id, start_date, status)
                    VALUES (?, ?, ?, 'active')
                ");
                $stmt->execute([$_SESSION['user_id'], $room['id'], date('Y-m-d')]);
                $tenant['id'] = $pdo->lastInsertId();
            } catch (Exception $e) {
                // Log the error but continue
                error_log("Error creating tenant record: " . $e->getMessage());
            }
        }
    }
}

// Get payment history
$payments = [];
if ($tenant && !empty($tenant['id'])) {
    $stmt = $pdo->prepare("
        SELECT * FROM payments 
        WHERE tenant_id = ?
        ORDER BY payment_date DESC
        LIMIT 5
    ");
    $stmt->execute([$tenant['id']]);
    $payments = $stmt->fetchAll();
    
    // If no payments found but we have a tenant, create a pending payment
    if (empty($payments) && $tenant['id'] > 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO payments (tenant_id, amount, payment_date, payment_method, status)
                VALUES (?, ?, NOW(), 'transfer', 'pending')
            ");
            $stmt->execute([$tenant['id'], $tenant['price']]);
            
            // Refresh payments
            $stmt = $pdo->prepare("
                SELECT * FROM payments 
                WHERE tenant_id = ?
                ORDER BY payment_date DESC
                LIMIT 5
            ");
            $stmt->execute([$tenant['id']]);
            $payments = $stmt->fetchAll();
        } catch (Exception $e) {
            // Log the error but continue
            error_log("Error creating payment record: " . $e->getMessage());
        }
    }
}

// Get recent announcements
$stmt = $pdo->query("
    SELECT a.*, u.first_name, u.last_name 
    FROM announcements a
    JOIN users u ON a.created_by = u.id
    ORDER BY a.created_at DESC
    LIMIT 3
");
$announcements = $stmt->fetchAll();

// Get recent messages
$stmt = $pdo->prepare("
    SELECT m.*, 
           CONCAT(sender.first_name, ' ', sender.last_name) as sender_name,
           CONCAT(receiver.first_name, ' ', receiver.last_name) as receiver_name
    FROM messages m
    JOIN users sender ON m.sender_id = sender.id
    JOIN users receiver ON m.receiver_id = receiver.id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    ORDER BY m.created_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$messages = $stmt->fetchAll();

// Get unread notifications count
// First, let's check the structure of the notifications table to determine the correct column names
try {
    $stmt = $pdo->query("DESCRIBE notifications");
    $notifColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Determine the correct column names
    $recipientField = in_array('recipient_id', $notifColumns) ? 'recipient_id' : 
                     (in_array('user_id', $notifColumns) ? 'user_id' : 'recipient_id');
    $readField = in_array('is_read', $notifColumns) ? 'is_read' : 
                (in_array('read', $notifColumns) ? '`read`' : 'is_read');
    
    // Get unread notifications count using the correct column names
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE $recipientField = ? AND $readField = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $notification_count = $stmt->fetchColumn();
} catch (Exception $e) {
    // If there's an error, set notification count to 0
    $notification_count = 0;
    error_log("Error getting notification count: " . $e->getMessage());
}

// Get available rooms (if user doesn't have a room)
$available_rooms = [];
if (!$tenant) {
    $stmt = $pdo->query("
        SELECT r.*, 
               (SELECT ri.image_path FROM room_images ri WHERE ri.room_id = r.id AND ri.is_primary = 1 LIMIT 1) as image
        FROM rooms r
        WHERE r.status = 'available'
        ORDER BY r.price ASC
        LIMIT 3
    ");
    $available_rooms = $stmt->fetchAll();
}
?>

<?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger">
        <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']); // Clear the message after displaying
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <?php 
            echo $_SESSION['success_message']; 
            unset($_SESSION['success_message']); // Clear the message after displaying
        ?>
    </div>
<?php endif; ?>

<?php if (!$tenant): ?>
<!-- No Room Banner -->
<div class="welcome-banner">
    <div class="welcome-content">
        <h1>Welcome to Aula Kost!</h1>
        <p>You don't have a room yet. Browse our available rooms and book one today.</p>
        <a href="index.php?page=rooms" class="btn btn-primary">Browse Rooms</a>
    </div>
    <div class="welcome-image">
        <img src="assets/images/welcome-illustration.svg" alt="Welcome">
    </div>
</div>
<?php endif; ?>

<!-- Two-column Layout -->
<div class="content-layout">
    <!-- Left Column - Main Content -->
    <div class="main-column">
        <?php if ($tenant): ?>
        <!-- Dashboard Cards -->
        <div class="dashboard-grid">
            <!-- Cost Card -->
            <div class="card cost-card">
                <div class="card-header">
                    <div class="card-title">Your Cost</div>
                    <a href="index.php?page=payments" class="card-icon">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="cost-amount">IDR <?php echo number_format($tenant['price'], 0, ',', '.'); ?></div>
                <div class="cost-due">Due by <?php echo date('d F Y', strtotime('last day of this month')); ?></div>
            </div>
            
            <!-- Room Card -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Your Room</div>
                    <a href="index.php?page=room-detail&id=<?php echo $tenant['room_id']; ?>" class="card-icon">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="room-number"><?php echo $tenant['room_name']; ?></div>
                <div class="room-location">Active since <?php echo date('d M Y', strtotime($tenant['start_date'])); ?></div>
            </div>
        </div>
        
        <!-- Cost List Table -->
        <div class="card table-card">
            <div class="table-header">
                <h2>Cost List</h2>
                <div class="filter-actions">
                    <div class="filter-btn">
                        <i class="fas fa-filter"></i>
                        Filter
                    </div>
                    <a href="index.php?page=payments" class="more-btn">
                        <i class="fas fa-ellipsis-v"></i>
                    </a>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Monthly Rent</td>
                        <td>Dues</td>
                        <td><?php echo date('d/m/Y', strtotime('last day of this month')); ?></td>
                        <td>IDR <?php echo number_format($tenant['price'], 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Cleaning fee</td>
                        <td>Dues</td>
                        <td><?php echo date('d/m/Y', strtotime('last day of this month')); ?></td>
                        <td>IDR 50.000</td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Utilities</td>
                        <td>Bill</td>
                        <td><?php echo date('d/m/Y', strtotime('last day of this month')); ?></td>
                        <td>IDR 100.000</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Transaction History Table -->
        <div class="card table-card">
            <div class="table-header">
                <h2>Transaction History</h2>
                <div class="filter-actions">
                    <div class="filter-btn">
                        <i class="fas fa-filter"></i>
                        Filter
                    </div>
                    <a href="index.php?page=payments" class="more-btn">
                        <i class="fas fa-ellipsis-v"></i>
                    </a>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($payments)): ?>
                        <?php foreach ($payments as $index => $payment): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>Payment</td>
                                <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                <td>IDR <?php echo number_format($payment['amount'], 0, ',', '.'); ?></td>
                                <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                <td><span class="status status-<?php echo $payment['status']; ?>"><?php echo ucfirst($payment['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No transaction history available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <!-- Available Rooms Section -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">Available Rooms</div>
                <a href="index.php?page=rooms" class="card-icon">
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="available-rooms">
                <?php foreach ($available_rooms as $room): ?>
                <div class="available-room-card">
                    <div class="room-image">
                        <img src="<?php echo $room['image'] ? 'uploads/rooms/' . $room['image'] : 'assets/images/default-room.jpg'; ?>" alt="<?php echo $room['name']; ?>">
                    </div>
                    <div class="room-info">
                        <h3><?php echo $room['name']; ?></h3>
                        <p class="room-price">IDR <?php echo number_format($room['price'], 0, ',', '.'); ?>/month</p>
                        <a href="index.php?page=room-detail&id=<?php echo $room['id']; ?>" class="btn btn-primary btn-sm">View Details</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="view-all-rooms">
                <a href="index.php?page=rooms" class="btn btn-secondary">View All Rooms</a>
            </div>
        </div>
        
        <!-- How to Book Section -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">How to Book a Room</div>
            </div>
            
            <div class="booking-steps">
                <div class="booking-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Browse Rooms</h3>
                        <p>Explore our available rooms and find the one that suits your needs.</p>
                    </div>
                </div>
                
                <div class="booking-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Select a Room</h3>
                        <p>Choose a room and review its details, amenities, and pricing.</p>
                    </div>
                </div>
                
                <div class="booking-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Book Now</h3>
                        <p>Complete the booking process and make your initial payment.</p>
                    </div>
                </div>
                
                <div class="booking-step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Move In</h3>
                        <p>Once your booking is confirmed, you can move into your new room!</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Right Column - Sidebar Content -->
    <div class="side-column">
        <!-- Calendar Section -->
        <div class="card calendar-section">
            <div class="calendar-header">
                <h2><?php echo date('F Y'); ?></h2>
                <div class="month-nav">
                    <div class="nav-btn">
                        <i class="fas fa-chevron-up"></i>
                    </div>
                    <div class="nav-btn">
                        <i class="fas fa-chevron-down"></i>
                    </div>
                </div>
            </div>
            
            <table class="calendar">
                <thead>
                    <tr>
                        <th>Mo</th>
                        <th>Tu</th>
                        <th>We</th>
                        <th>Th</th>
                        <th>Fr</th>
                        <th>Sa</th>
                        <th>Su</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Generate calendar
                    $currentMonth = date('n');
                    $currentYear = date('Y');
                    $daysInMonth = date('t');
                    $firstDayOfMonth = date('N', strtotime("$currentYear-$currentMonth-01"));
                    
                    // Calculate previous month days to display
                    $prevMonthDays = $firstDayOfMonth - 1;
                    
                    // Calculate total cells needed (previous month days + current month days)
                    $totalCells = $prevMonthDays + $daysInMonth;
                    
                    // Calculate rows needed
                    $totalRows = ceil($totalCells / 7);
                    
                    // Current day
                    $currentDay = date('j');
                    
                    // Previous month days
                    $prevMonth = date('t', strtotime("last month"));
                    
                    // Generate calendar rows
                    for ($i = 0; $i < $totalRows; $i++) {
                        echo "<tr>";
                        
                        // Generate cells for each row
                        for ($j = 1; $j <= 7; $j++) {
                            $cellNumber = $i * 7 + $j;
                            
                            if ($cellNumber <= $prevMonthDays) {
                                // Previous month days
                                $prevDay = $prevMonth - ($prevMonthDays - $cellNumber);
                                echo "<td class='other-month'><div>$prevDay</div></td>";
                            } elseif ($cellNumber > $prevMonthDays && $cellNumber <= $totalCells) {
                                // Current month days
                                $day = $cellNumber - $prevMonthDays;
                                
                                if ($day == $currentDay) {
                                    echo "<td class='today'><div>$day</div></td>";
                                } else {
                                    echo "<td><div>$day</div></td>";
                                }
                            } else {
                                // Next month days
                                $nextDay = $cellNumber - $totalCells;
                                echo "<td class='other-month'><div>$nextDay</div></td>";
                            }
                        }
                        
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        
        <!-- Group Chat Section -->
        <div class="card chat-section">
            <div class="section-header">
                <h2>Group Chat</h2>
                <a href="index.php?page=chat" class="see-all">See All</a>
            </div>
            
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $message): ?>
                    <?php 
                    $isOutgoing = $message['sender_id'] == $_SESSION['user_id'];
                    $displayName = $isOutgoing ? 'You' : $message['sender_name'];
                    ?>
                    <div class="chat-message">
                        <?php if (!$isOutgoing): ?>
                        <div class="chat-avatar">
                            <img src="assets/images/default-avatar.jpg" alt="<?php echo $displayName; ?>">
                        </div>
                        <?php endif; ?>
                        <div class="chat-content">
                            <div class="chat-name"><?php echo $displayName; ?></div>
                            <div class="chat-text"><?php echo substr($message['message'], 0, 100) . (strlen($message['message']) > 100 ? '...' : ''); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="chat-message">
                    <div class="chat-content">
                        <div class="chat-text">No messages yet. Start a conversation!</div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="chat-input">
                <input type="text" placeholder="Type Here" disabled>
                <a href="index.php?page=chat" class="send-btn">
                    <i class="fas fa-paper-plane"></i>
                </a>
            </div>
        </div>
        
        <!-- Announcement Section -->
        <div class="card announcement-section">
            <div class="section-header">
                <h2>Announcement</h2>
                <a href="index.php?page=announcements" class="see-all">See All</a>
            </div>
            
            <?php if (!empty($announcements)): ?>
                <?php foreach ($announcements as $announcement): ?>
                    <div class="announcement-card">
                        <div class="announcement-icon">
                            <i class="fas fa-bullhorn"></i>
                        </div>
                        <div class="announcement-date"><?php echo date('d F Y', strtotime($announcement['created_at'])); ?></div>
                        <div class="announcement-title"><?php echo $announcement['title']; ?></div>
                        <a href="index.php?page=announcement-detail&id=<?php echo $announcement['id']; ?>" class="announcement-alert">
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="announcement-card">
                    <div class="announcement-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="announcement-title">No announcements yet</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    /* Welcome Banner */
    .welcome-banner {
        display: flex;
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        overflow: hidden;
        margin-bottom: 24px;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        border: 1px solid var(--border-color);
    }

    .welcome-banner:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .welcome-content {
        flex: 1;
        padding: 32px;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .welcome-content h1 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 16px;
        color: var(--accent-color);
    }

    .welcome-content p {
        font-size: 16px;
        color: var(--text-secondary);
        margin-bottom: 24px;
        line-height: 1.6;
    }

    .welcome-image {
        width: 40%;
        background-color: var(--sidebar-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
    }

    .welcome-image img {
        max-width: 100%;
        max-height: 200px;
    }

    /* Available Rooms */
    .available-rooms {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 16px;
        margin-top: 16px;
    }

    .available-room-card {
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-md);
        overflow: hidden;
        transition: var(--transition);
    }

    .available-room-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-sm);
    }

    .room-image {
        height: 150px;
        overflow: hidden;
    }

    .room-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--transition);
    }

    .available-room-card:hover .room-image img {
        transform: scale(1.05);
    }

    .room-info {
        padding: 16px;
    }

    .room-info h3 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .room-price {
        font-size: 14px;
        color: var(--text-secondary);
        margin-bottom: 12px;
    }

    .view-all-rooms {
        margin-top: 24px;
        text-align: center;
    }

    /* Booking Steps */
    .booking-steps {
        margin-top: 16px;
    }

    .booking-step {
        display: flex;
        gap: 16px;
        margin-bottom: 24px;
    }

    .booking-step:last-child {
        margin-bottom: 0;
    }

    .step-number {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: var(--accent-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        flex-shrink: 0;
    }

    .step-content h3 {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .step-content p {
        font-size: 14px;
        color: var(--text-secondary);
        line-height: 1.5;
    }

    /* Dashboard Layout */
    .content-layout {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }

    .main-column {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .side-column {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 24px;
        margin-bottom: 24px;
    }

    .card {
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 24px;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        border: 1px solid rgba(0, 0, 0, 0.03);
        overflow: hidden;
    }

    .card:hover {
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .card-title {
        font-size: 18px;
        font-weight: 600;
    }

    .card-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: rgba(0, 0, 0, 0.05);
        transition: var(--transition);
        cursor: pointer;
        color: inherit;
        text-decoration: none;
    }

    .card-icon:hover {
        background-color: var(--accent-color);
        color: white;
        transform: rotate(45deg);
    }

    .cost-card {
        background-color: var(--accent-color);
        color: white;
        position: relative;
        overflow: hidden;
    }

    .cost-card::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(0,0,0,0) 70%);
        opacity: 0;
        transition: opacity 0.5s ease;
    }

    .cost-card:hover::before {
        opacity: 1;
    }

    .cost-amount {
        font-size: 42px;
        font-weight: bold;
        margin: 24px 0 12px;
        letter-spacing: -1px;
    }

    .cost-due {
        color: rgba(255, 255, 255, 0.7);
        font-size: 14px;
    }

    .room-number {
        font-size: 42px;
        font-weight: bold;
        margin: 24px 0 12px;
        letter-spacing: -1px;
    }

    .room-location {
        color: var(--text-secondary);
        font-size: 14px;
    }

    /* Table Styles */
    .table-card {
        margin-bottom: 24px;
    }

    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .filter-actions {
        display: flex;
        gap: 12px;
    }

    .filter-btn, .more-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 8px 16px;
        border-radius: 50px;
        border: 1px solid var(--border-color);
        background-color: var(--card-bg);
        cursor: pointer;
        transition: var(--transition);
        text-decoration: none;
        color: inherit;
    }

    .filter-btn:hover, .more-btn:hover {
        background-color: var(--hover-color);
        border-color: #ccc;
    }

    .filter-btn i {
        margin-right: 8px;
    }

    .more-btn {
        width: 40px;
        height: 40px;
        padding: 0;
    }

    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    thead {
        background-color: var(--sidebar-bg);
    }

    th, td {
        padding: 16px;
        text-align: left;
    }

    th {
        font-weight: 600;
        color: var(--text-primary);
        position: relative;
    }

    th:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 1px;
        background-color: var(--border-color);
    }

    td {
        color: var(--text-secondary);
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    tbody tr {
        transition: var(--transition);
    }

    tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    .status {
        padding: 6px 12px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 500;
        display: inline-block;
    }

    .status-paid {
        background-color: rgba(46, 125, 50, 0.1);
        color: var(--success-color);
    }

    .status-pending {
        background-color: rgba(237, 108, 2, 0.1);
        color: var(--warning-color);
    }

    /* Calendar Styles */
    .calendar-section {
        grid-column: 1 / -1;
    }

    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .month-nav {
        display: flex;
        gap: 12px;
    }

    .nav-btn {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 1px solid var(--border-color);
        background-color: var(--card-bg);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition);
    }

    .nav-btn:hover {
        background-color: var(--hover-color);
        transform: translateY(-2px);
    }

    .calendar {
        width: 100%;
        border-collapse: separate;
        border-spacing: 8px;
    }

    .calendar th {
        padding: 12px;
        text-align: center;
        font-weight: 500;
        color: var(--text-secondary);
    }

    .calendar th:after {
        display: none;
    }

    .calendar td {
        padding: 0;
        text-align: center;
        border: none;
        position: relative;
    }

    .calendar td > div {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        border-radius: 50%;
        cursor: pointer;
        transition: var(--transition);
    }

    .calendar td > div:hover {
        background-color: var(--hover-color);
    }

    .calendar .today > div {
        background-color: var(--accent-color);
        color: white;
        font-weight: 500;
    }

    .calendar .other-month > div {
        color: #ccc;
    }

    /* Chat and Announcement Styles */
    .chat-section, .announcement-section {
        margin-top: 24px;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .see-all {
        color: var(--text-secondary);
        text-decoration: none;
        font-size: 14px;
        transition: var(--transition);
    }

    .see-all:hover {
        color: var(--accent-color);
        text-decoration: underline;
    }

    .chat-message {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
        padding: 12px;
        border-radius: var(--border-radius-md);
        transition: var(--transition);
    }

    .chat-message:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    .chat-avatar {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .chat-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--transition);
    }

    .chat-avatar:hover img {
        transform: scale(1.1);
    }

    .chat-content {
        flex: 1;
    }

    .chat-name {
        font-weight: 600;
        margin-bottom: 6px;
    }

    .chat-text {
        color: var(--text-secondary);
        font-size: 14px;
    }

    .chat-input {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-top: 24px;
        padding: 8px;
        border-radius: var(--border-radius-lg);
        background-color: var(--sidebar-bg);
    }

    .chat-input input {
        flex: 1;
        border: none;
        background: transparent;
        padding: 8px;
        font-size: 14px;
        outline: none;
    }

    .send-btn {
        background: none;
        border: none;
        color: var(--accent-color);
        cursor: pointer;
        font-size: 18px;
        transition: transform 0.2s ease;
        padding: 4px;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }

    .send-btn:hover {
        transform: scale(1.1);
        background-color: var(--hover-color);
    }

    .announcement-card {
        background-color: var(--accent-color);
        color: white;
        border-radius: var(--border-radius-lg);
        padding: 24px;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
        transition: var(--transition);
    }

    .announcement-card:hover {
        transform: translateY(-3px);
        box-shadow: var(--shadow-md);
    }

    .announcement-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 100%;
        height: 100%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(0,0,0,0) 70%);
        opacity: 0;
        transition: opacity 0.5s ease;
    }

    .announcement-card:hover::before {
        opacity: 1;
    }

    .announcement-icon {
        margin-bottom: 12px;
        font-size: 18px;
    }

    .announcement-date {
        color: rgba(255, 255, 255, 0.7);
        font-size: 14px;
        margin-bottom: 6px;
    }

    .announcement-title {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .announcement-alert {
        display: flex;
        justify-content: flex-end;
        color: white;
        text-decoration: none;
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

    .btn-sm {
        padding: 8px 16px;
        font-size: 14px;
    }

    /* Responsive adjustments */
    @media (max-width: 1200px) {
        .content-layout {
            grid-template-columns: 1fr;
        }
        
        .dashboard-grid {
            grid-template-columns: 1fr;
        }

        .welcome-banner {
            flex-direction: column;
        }

        .welcome-image {
            width: 100%;
            padding: 16px;
        }
    }

    @media (max-width: 768px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }

        .available-rooms {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add subtle hover effects
        const cards = document.querySelectorAll('.card');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    });
</script>

