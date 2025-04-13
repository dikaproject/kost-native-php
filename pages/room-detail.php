<?php
// Get room ID from URL
$room_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if user already has an active room
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM tenants 
    WHERE user_id = ? AND status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$has_active_room = ($stmt->fetchColumn() > 0);

// Get room details
$stmt = $pdo->prepare("
    SELECT r.* 
    FROM rooms r
    WHERE r.id = ?
");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

// If room not found, redirect to rooms page
if (!$room) {
    header('Location: index.php?page=rooms');
    exit;
}

// Get room images
$stmt = $pdo->prepare("
    SELECT * FROM room_images 
    WHERE room_id = ?
    ORDER BY is_primary DESC
");
$stmt->execute([$room_id]);
$images = $stmt->fetchAll();

// Get room features
$stmt = $pdo->prepare("
    SELECT feature_name FROM room_features 
    WHERE room_id = ?
");
$stmt->execute([$room_id]);
$features = $stmt->fetchAll();

$page_title = $room['name'];
?>

<!-- Page Header with Back Button -->
<div class="page-header">
    <a href="index.php?page=rooms" class="back-button">
        <i class="fas fa-arrow-left"></i>
        <span>Back to Rooms</span>
    </a>
</div>

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

<!-- Room Detail Container -->
<div class="room-detail-container">
    <!-- Image Gallery -->
    <div class="gallery-container">
        <div class="gallery-main">
            <img src="<?php echo !empty($images) ? 'uploads/rooms/' . $images[0]['image_path'] : 'assets/images/default-room.jpg'; ?>" alt="<?php echo $room['name']; ?>" class="main-image" id="main-image">
        </div>
        <?php if (count($images) > 1): ?>
        <div class="gallery-thumbnails">
            <?php foreach ($images as $index => $image): ?>
            <div class="thumbnail-wrapper <?php echo $index === 0 ? 'active' : ''; ?>" data-image="uploads/rooms/<?php echo $image['image_path']; ?>">
                <img src="uploads/rooms/<?php echo $image['image_path']; ?>" alt="<?php echo $room['name']; ?>" class="thumbnail">
            </div>
            <?php endforeach; ?>
        </div>
        <div class="gallery-controls">
            <div class="gallery-control" id="prev-image">
                <i class="fas fa-chevron-left"></i>
            </div>
            <div class="gallery-control" id="next-image">
                <i class="fas fa-chevron-right"></i>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Content Section -->
    <div class="detail-content">
        <!-- Left Column - Details -->
        <div class="detail-left">
            <div class="detail-header">
                <h1 class="detail-title" id="detail-title"><?php echo $room['name']; ?></h1>
                <div class="detail-location">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Aulia Kost</span>
                </div>
                <div class="detail-tags">
                    <div class="detail-tag <?php echo $room['status'] === 'available' ? 'tag-available' : 'tag-occupied'; ?>">
                        <?php echo ucfirst($room['status']); ?>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h2 class="section-title">Amenities</h2>
                <div class="amenities-list">
                    <?php foreach ($features as $feature): ?>
                    <div class="amenity-item">
                        <?php
                        $icons = [
                            'Air Conditioning' => 'fas fa-wind',
                            'Private Bathroom' => 'fas fa-shower',
                            'Shared Bathroom' => 'fas fa-shower',
                            'Study Desk' => 'fas fa-desk',
                            'Free WiFi' => 'fas fa-wifi',
                            'Single Bed' => 'fas fa-bed',
                            'Double Bed' => 'fas fa-bed',
                            'TV' => 'fas fa-tv',
                            'Cleaning Service' => 'fas fa-broom'
                        ];
                        $icon = isset($icons[$feature['feature_name']]) ? $icons[$feature['feature_name']] : 'fas fa-check';
                        ?>
                        <i class="<?php echo $icon; ?>"></i>
                        <span><?php echo $feature['feature_name']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="detail-section">
                <h2 class="section-title">Description</h2>
                <p class="description">
                    <?php echo $room['description'] ?: 'No description available.'; ?>
                </p>
            </div>
        </div>
        
        <!-- Right Column - Price and Actions -->
        <div class="detail-right">
            <div class="price-card">
                <div class="price-header">
                    <span class="price-amount">IDR <?php echo number_format($room['price'], 0, ',', '.'); ?></span>
                    <span class="price-period">/month</span>
                </div>
                
                <div class="price-features">
                    <div class="price-feature">
                        <i class="fas fa-check-circle"></i>
                        <span>All utilities included</span>
                    </div>
                    <div class="price-feature">
                        <i class="fas fa-check-circle"></i>
                        <span>Free WiFi</span>
                    </div>
                    <div class="price-feature">
                        <i class="fas fa-check-circle"></i>
                        <span>Weekly cleaning</span>
                    </div>
                    <div class="price-feature">
                        <i class="fas fa-check-circle"></i>
                        <span>24/7 security</span>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <?php if ($room['status'] === 'available'): ?>
                        <?php if ($has_active_room): ?>
                            <button class="order-btn" disabled style="opacity: 0.6;">You Already Have a Room</button>
                        <?php else: ?>
                            <!-- Update to use the new Midtrans booking flow -->
                            <a href="index.php?page=direct-book-room&id=<?php echo $room['id']; ?>" class="order-btn" onclick="return confirm('Are you sure you want to book this room? You will need to complete payment through our secure payment gateway.');">Book Now</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="order-btn" disabled style="opacity: 0.6;">Currently Occupied</button>
                    <?php endif; ?>
                    <a href="index.php?page=contact" class="contact-btn">Contact Host</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Room Detail Page Specific Styles */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
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

    /* Alert Styles */
    .alert {
        padding: 16px;
        border-radius: var(--border-radius-md);
        margin-bottom: 24px;
    }

    .alert-danger {
        background-color: rgba(211, 47, 47, 0.1);
        color: #d32f2f;
        border: 1px solid rgba(211, 47, 47, 0.2);
    }

    .alert-success {
        background-color: rgba(46, 125, 50, 0.1);
        color: #2e7d32;
        border: 1px solid rgba(46, 125, 50, 0.2);
    }

    .room-detail-container {
        display: grid;
        grid-template-columns: 1fr;
        gap: 32px;
    }

    /* Modern Image Gallery */
    .gallery-container {
        border-radius: var(--border-radius-lg);
        overflow: hidden;
        position: relative;
        height: 500px;
        box-shadow: var(--shadow-sm);
    }

    .gallery-main {
        width: 100%;
        height: 100%;
        position: relative;
    }

    .main-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: var(--transition);
    }

    .gallery-thumbnails {
        position: absolute;
        bottom: 20px;
        left: 20px;
        display: flex;
        gap: 10px;
    }

    .thumbnail-wrapper {
        width: 60px;
        height: 60px;
        border-radius: 8px;
        overflow: hidden;
        border: 2px solid white;
        cursor: pointer;
        transition: var(--transition);
        opacity: 0.7;
    }

    .thumbnail-wrapper:hover, .thumbnail-wrapper.active {
        opacity: 1;
        transform: translateY(-3px);
    }

    .thumbnail {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .gallery-controls {
        position: absolute;
        bottom: 20px;
        right: 20px;
        display: flex;
        gap: 10px;
    }

    .gallery-control {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: var(--transition);
    }

    .gallery-control:hover {
        background-color: white;
        transform: scale(1.1);
    }

    /* Content Section */
    .detail-content {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 32px;
    }

    .detail-left {
        display: flex;
        flex-direction: column;
        gap: 32px;
    }

    .detail-right {
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        padding: 24px;
        height: fit-content;
        box-shadow: var(--shadow-sm);
        position: sticky;
        top: 24px;
    }

    .detail-header {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .detail-title {
        font-size: 28px;
        font-weight: 600;
        letter-spacing: -0.5px;
    }

    .detail-location {
        display: flex;
        align-items: center;
        gap: 6px;
        color: var(--text-secondary);
        font-size: 14px;
    }

    .detail-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 16px;
    }

    .detail-tag {
        padding: 6px 12px;
        border-radius: 50px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .tag-available {
        background-color: rgba(46, 125, 50, 0.1);
        color: #2e7d32;
    }
    
    .tag-occupied {
        background-color: rgba(211, 47, 47, 0.1);
        color: #d32f2f;
    }

    .detail-section {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .section-title {
        font-size: 18px;
        font-weight: 600;
        letter-spacing: -0.3px;
    }

    .amenities-list {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .amenity-item {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 14px;
    }

    .amenity-item i {
        color: var(--accent-color);
        font-size: 16px;
        width: 16px;
    }

    .description {
        color: var(--text-secondary);
        line-height: 1.7;
        font-size: 15px;
    }

    .price-card {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .price-header {
        display: flex;
        align-items: baseline;
        gap: 4px;
    }

    .price-amount {
        font-size: 24px;
        font-weight: 600;
    }

    .price-period {
        font-size: 14px;
        color: var(--text-secondary);
    }

    .price-features {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin: 16px 0;
    }

    .price-feature {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
    }

    .price-feature i {
        color: var(--success-color);
        font-size: 14px;
    }

    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 8px;
    }

    .order-btn {
        padding: 14px;
        border: none;
        border-radius: var(--border-radius-md);
        background-color: var(--accent-color);
        color: white;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        text-align: center;
        text-decoration: none;
    }

    .order-btn:hover {
        opacity: 0.9;
        transform: translateY(-2px);
    }

    .contact-btn {
        padding: 14px;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-md);
        background-color: transparent;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition);
        text-align: center;
        text-decoration: none;
        color: var(--text-primary);
    }

    .contact-btn:hover {
        background-color: var(--hover-color);
    }

    /* Responsive adjustments */
    @media (max-width: 1200px) {
        .detail-content {
            grid-template-columns: 1fr;
        }
        
        .detail-right {
            position: static;
        }
        
        .gallery-container {
            height: 400px;
        }
    }

    @media (max-width: 768px) {
        .amenities-list {
            grid-template-columns: 1fr;
        }
        
        .gallery-container {
            height: 300px;
        }
        
        .gallery-thumbnails {
            display: none;
        }
    }

    @media (max-width: 576px) {
        .gallery-container {
            height: 250px;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Image gallery functionality
        const thumbnails = document.querySelectorAll('.thumbnail-wrapper');
        const mainImage = document.getElementById('main-image');
        const prevButton = document.getElementById('prev-image');
        const nextButton = document.getElementById('next-image');
        
        if (thumbnails.length > 0) {
            let currentImageIndex = 0;
            const images = Array.from(thumbnails).map(thumb => thumb.getAttribute('data-image'));
            
            // Set active thumbnail and update main image
            function setActiveImage(index) {
                // Update active class
                thumbnails.forEach(thumb => thumb.classList.remove('active'));
                if (thumbnails[index]) {
                    thumbnails[index].classList.add('active');
                }
                
                // Update main image
                mainImage.src = images[index];
                currentImageIndex = index;
            }
            
            // Add click event to thumbnails
            thumbnails.forEach((thumb, index) => {
                thumb.addEventListener('click', () => {
                    setActiveImage(index);
                });
            });
            
            // Previous and next buttons
            if (prevButton && nextButton) {
                prevButton.addEventListener('click', () => {
                    let newIndex = currentImageIndex - 1;
                    if (newIndex < 0) {
                        newIndex = images.length - 1;
                    }
                    setActiveImage(newIndex);
                });
                
                nextButton.addEventListener('click', () => {
                    let newIndex = currentImageIndex + 1;
                    if (newIndex >= images.length) {
                        newIndex = 0;
                    }
                    setActiveImage(newIndex);
                });
            }
        }
    });
</script>