-- Create the database
CREATE DATABASE IF NOT EXISTS aula_kost;
USE aula_kost;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    bio TEXT,
    country VARCHAR(100),
    city VARCHAR(100),
    village VARCHAR(100),
    postal_code VARCHAR(20),
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    remember_token VARCHAR(100),
    remember_expires DATETIME,
    reset_token VARCHAR(100),
    reset_expires DATETIME,
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (email),
    INDEX (remember_token),
    INDEX (reset_token)
);

-- Rooms table
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(20) NOT NULL UNIQUE,
    floor VARCHAR(20) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    description TEXT,
    status ENUM('available', 'occupied', 'maintenance') NOT NULL DEFAULT 'available',
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    room_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_date DATETIME NOT NULL,
    payment_method ENUM('credit_card', 'bank_transfer', 'e_wallet', 'cash') NOT NULL,
    transaction_id VARCHAR(100),
    status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
);

-- Announcements table
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    image VARCHAR(255),
    date DATE NOT NULL,
    time VARCHAR(50),
    location VARCHAR(100),
    category VARCHAR(50),
    contact VARCHAR(100),
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Messages table
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT,
    channel_id VARCHAR(50),
    content TEXT NOT NULL,
    is_read BOOLEAN NOT NULL DEFAULT FALSE,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert admin user
INSERT INTO users (
    email, 
    password, 
    first_name, 
    last_name, 
    phone, 
    country, 
    city, 
    village, 
    postal_code, 
    role, 
    created_at
) VALUES (
    'admin@auliakost.com', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'Admin', 
    'User', 
    '123456789', 
    'Indonesia', 
    'Jakarta', 
    'Central Jakarta', 
    '10110', 
    'admin', 
    NOW()
);

-- Insert sample rooms
INSERT INTO rooms (room_number, floor, price, description, status, created_at) VALUES
('Room 1', '1st Floor', 600000, 'Single room with private bathroom and AC', 'available', NOW()),
('Room 2', '1st Floor', 600000, 'Single room with private bathroom and AC', 'available', NOW()),
('Room 3', '2nd Floor', 600000, 'Single room with private bathroom and AC', 'available', NOW()),
('Room 4', '2nd Floor', 600000, 'Single room with private bathroom and AC', 'available', NOW()),
('Room 5', '3rd Floor', 600000, 'Single room with private bathroom and AC', 'available', NOW()),
('Room 6', '3rd Floor', 600000, 'Single room with private bathroom and AC', 'available', NOW());

-- Insert sample announcements
INSERT INTO announcements (title, content, image, date, time, location, category, contact, created_at) VALUES
('If you have overnight guests, kindly inform the management', 'For the comfort and security of all residents, we are updating the guest visiting hours to 08:00 AM - 10:00 PM. All overnight guests must be registered with the management office at least 24 hours in advance.', 'announcements/guest-policy.jpg', '2024-09-01', '08:00 AM - 10:00 PM', 'All Buildings', 'Policy Update', 'Management Office', NOW()),
('Scheduled maintenance for water supply', 'We will be conducting maintenance on the water supply system on August 25, 2024. Water will be unavailable from 9:00 AM to 6:00 PM.', 'announcements/water-maintenance.jpg', '2024-08-25', '09:00 AM - 06:00 PM', 'Building A & B', 'Maintenance', 'Technical Support', NOW()),
('New WiFi router installation', 'We\'re upgrading our WiFi system with new routers for better coverage and speed. The installation will take place on August 20, 2024.', 'announcements/wifi-upgrade.jpg', '2024-08-20', 'All Day', 'All Buildings', 'Upgrade', 'IT Support', NOW());

