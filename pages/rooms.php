<?php
$page_title = "Rooms";

// Get all available rooms
$stmt = $pdo->query("
    SELECT r.*, 
           (SELECT ri.image_path FROM room_images ri WHERE ri.room_id = r.id AND ri.is_primary = 1 LIMIT 1) as image
    FROM rooms r
    ORDER BY r.id
");
$rooms = $stmt->fetchAll();
?>

<div class="page-title-container">
    <h1 class="page-title">List Rooms</h1>
    <div class="filter-container">
        <div class="filter-button">
            <i class="fas fa-filter"></i>
            <span>Filter</span>
        </div>
        <div class="more-options">
            <i class="fas fa-ellipsis-v"></i>
        </div>
    </div>
</div>

<!-- Rooms Container -->
<div class="rooms-container">
    <!-- Room Grid -->
    <div class="rooms-grid">
        <?php foreach ($rooms as $room): ?>
            <a href="index.php?page=room-detail&id=<?php echo $room['id']; ?>" class="room-card" data-room-id="<?php echo $room['id']; ?>">
                <img src="<?php echo $room['image'] ? 'uploads/rooms/' . $room['image'] : 'assets/images/default-room.jpg'; ?>" alt="<?php echo $room['name']; ?>" class="room-image">
                <div class="room-details">
                    <h3 class="room-title"><?php echo $room['name']; ?></h3>
                    <div class="room-amenities">
                        <?php
                        // Get room features
                        $stmt = $pdo->prepare("SELECT feature_name FROM room_features WHERE room_id = ? LIMIT 3");
                        $stmt->execute([$room['id']]);
                        $features = $stmt->fetchAll();
                        
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
                        
                        foreach ($features as $feature):
                            $icon = isset($icons[$feature['feature_name']]) ? $icons[$feature['feature_name']] : 'fas fa-check';
                        ?>
                            <div class="amenity">
                                <i class="<?php echo $icon; ?>"></i>
                                <span><?php echo $feature['feature_name']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="room-price">
                        IDR <?php echo number_format($room['price'], 0, ',', '.'); ?><span class="price-period">/month</span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<style>
    /* Rooms Page Specific Styles */
    .page-title-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .page-title {
        font-size: 24px;
        font-weight: 600;
        color: var(--text-primary);
    }

    .filter-container {
        display: flex;
        gap: 12px;
    }

    .filter-button {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        cursor: pointer;
        transition: var(--transition);
    }

    .filter-button:hover {
        background-color: var(--hover-color);
    }

    .more-options {
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        cursor: pointer;
        transition: var(--transition);
    }

    .more-options:hover {
        background-color: var(--hover-color);
    }

    /* Room Grid Layout */
    .rooms-container {
        display: block;
        width: 100%;
        height: calc(100vh - 180px); /* Adjust height to fit the viewport minus header and title */
        overflow-y: auto;
    }

    .rooms-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
        padding-right: 12px; /* Add padding for scrollbar */
    }

    .room-card {
        background-color: var(--card-bg);
        border-radius: var(--border-radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
        border: 1px solid var(--border-color);
        cursor: pointer;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .room-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-md);
        border-color: #ccc;
    }

    .room-image {
        width: 100%;
        height: 160px;
        object-fit: cover;
        transition: var(--transition);
    }

    .room-card:hover .room-image {
        filter: brightness(1.05);
    }

    .room-details {
        padding: 12px 16px;
        display: flex;
        flex-direction: column;
        flex-grow: 1;
    }

    .room-title {
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--text-primary);
        letter-spacing: -0.3px;
    }

    .room-amenities {
        display: flex;
        gap: 12px;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .amenity {
        display: flex;
        align-items: center;
        gap: 4px;
        color: var(--text-secondary);
        font-size: 12px;
    }

    .amenity i {
        font-size: 12px;
        width: 14px;
        text-align: center;
        color: var(--accent-color);
    }

    .room-price {
        font-weight: 600;
        font-size: 16px;
        color: var(--text-primary);
        margin-top: auto;
    }

    .price-period {
        font-size: 13px;
        color: var(--text-secondary);
        font-weight: normal;
    }

    /* Add scrollbar styling for the rooms-container */
    .rooms-container::-webkit-scrollbar {
        width: 6px;
    }

    .rooms-container::-webkit-scrollbar-track {
        background: transparent;
    }

    .rooms-container::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 3px;
    }

    .rooms-container::-webkit-scrollbar-thumb:hover {
        background: #aaa;
    }

    /* Responsive adjustments */
    @media (max-width: 1200px) {
        .rooms-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .rooms-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add subtle hover effects
        const cards = document.querySelectorAll('.room-card');
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

