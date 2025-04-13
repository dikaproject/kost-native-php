-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 12, 2025 at 06:44 PM
-- Server version: 8.0.30
-- PHP Version: 8.3.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aula_kost`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `content` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `title`, `content`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Maintenance Schedule', 'There will be a scheduled maintenance for the water supply system on Saturday, December 15, 2023, from 9 AM to 12 PM. Please prepare accordingly.', 1, '2025-03-19 13:39:30', '2025-04-12 17:42:05'),
(2, 'New Facility: Laundry Room', 'We are pleased to announce that a new laundry room has been added to the building. It is located on the ground floor and is available for use 24/7.', 1, '2025-03-19 13:39:30', '2025-04-12 17:42:14'),
(3, 'test', 'test', 1, '2025-04-12 17:48:19', '2025-04-12 17:48:19');

-- --------------------------------------------------------

--
-- Table structure for table `announcement_images`
--

CREATE TABLE `announcement_images` (
  `id` int NOT NULL,
  `announcement_id` int NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcement_images`
--

INSERT INTO `announcement_images` (`id`, `announcement_id`, `image_path`) VALUES
(2, 2, 'announcement_67faa5f6f03f0.png'),
(3, 1, 'announcement_67faa5ede8abc.png'),
(4, 3, 'announcement_67faa763e49ab.png');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `room_id` int NOT NULL,
  `booking_date` datetime NOT NULL,
  `move_in_date` date NOT NULL,
  `duration` int NOT NULL COMMENT 'Duration in months',
  `status` enum('pending','confirmed','cancelled','completed') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','partially_paid','paid') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'unpaid',
  `total_amount` decimal(10,2) NOT NULL,
  `deposit_amount` decimal(10,2) NOT NULL,
  `special_requests` text COLLATE utf8mb4_general_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int NOT NULL,
  `tenant_id` int NOT NULL,
  `room_id` int NOT NULL,
  `invoice_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `paid_amount` decimal(10,2) DEFAULT '0.00',
  `status` enum('unpaid','partially_paid','paid','overdue') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'unpaid',
  `payment_method` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_general_ci,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `tenant_id`, `room_id`, `invoice_number`, `issue_date`, `due_date`, `total_amount`, `paid_amount`, `status`, `payment_method`, `payment_date`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'INV-2023-001', '2023-10-01', '2023-10-15', 650000.00, 0.00, 'paid', NULL, NULL, NULL, '2025-04-03 19:15:17', '2025-04-03 19:15:17'),
(2, 2, 2, 'INV-2023-002', '2023-10-01', '2023-10-15', 650000.00, 0.00, 'unpaid', NULL, NULL, NULL, '2025-04-03 19:15:17', '2025-04-03 19:15:17');

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int NOT NULL,
  `invoice_id` int NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `description`, `amount`, `quantity`, `created_at`) VALUES
(1, 1, 'Monthly Rent - October 2023', 600000.00, 1, '2025-04-03 19:15:17'),
(2, 1, 'Cleaning Service', 50000.00, 1, '2025-04-03 19:15:17'),
(3, 2, 'Monthly Rent - October 2023', 600000.00, 1, '2025-04-03 19:15:17'),
(4, 2, 'Cleaning Service', 50000.00, 1, '2025-04-03 19:15:17');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `id` int NOT NULL,
  `tenant_id` int NOT NULL,
  `room_id` int NOT NULL,
  `request_type` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('pending','in_progress','completed','rejected') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'medium',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `staff_notes` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_requests`
--

INSERT INTO `maintenance_requests` (`id`, `tenant_id`, `room_id`, `request_type`, `description`, `status`, `priority`, `created_at`, `updated_at`, `completed_at`, `staff_notes`) VALUES
(1, 1, 1, 'Plumbing', 'Leaking faucet in the bathroom', 'pending', 'medium', '2025-04-03 19:15:17', '2025-04-03 19:15:17', NULL, NULL),
(2, 1, 1, 'Electrical', 'Power outlet not working', 'in_progress', 'high', '2025-04-03 19:15:17', '2025-04-03 19:15:17', NULL, NULL),
(3, 2, 2, 'Furniture', 'Broken chair needs replacement', 'completed', 'low', '2025-04-03 19:15:17', '2025-04-03 19:15:17', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int NOT NULL,
  `sender_id` int NOT NULL,
  `receiver_id` int NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 1, 'Hi, I have a question about the rent payment.', 1, '2025-03-19 13:39:30'),
(2, 1, 2, 'Hello John, what would you like to know?', 0, '2025-03-19 13:39:30'),
(3, 2, 1, 'Can I pay the rent in installments this month?', 1, '2025-03-19 13:39:30'),
(4, 1, 2, 'Yes, that\'s possible. Please let me know your preferred payment schedule.', 0, '2025-03-19 13:39:30'),
(5, 3, 1, 'Thank you for the information.', 1, '2025-03-19 13:39:30'),
(6, 4, 1, 'When will the maintenance be done?', 1, '2025-03-19 13:39:30'),
(7, 6, 7, 'hai', 1, '2025-03-26 12:20:35'),
(8, 6, 7, 'hai', 1, '2025-03-27 05:02:36'),
(9, 6, 7, 'hai', 1, '2025-03-27 05:06:08'),
(10, 6, 7, 'hai', 1, '2025-03-27 05:08:54'),
(13, 6, 7, 'anjay mabar gacor kang', 1, '2025-03-30 05:30:17'),
(14, 12, 1, 'hai admin', 1, '2025-04-12 16:34:18'),
(15, 12, 1, 'hello?', 1, '2025-04-12 16:41:54'),
(16, 12, 1, 'hello?', 1, '2025-04-12 16:46:55'),
(17, 12, 5, 'hello', 0, '2025-04-12 16:53:13'),
(18, 1, 12, 'hai kak ada yang bisa di bantu?', 1, '2025-04-12 16:59:45'),
(19, 12, 1, 'kamar yang bagus kost apa ya kak', 1, '2025-04-12 16:59:59'),
(20, 1, 12, 'hmm tergantung kak, dana kakanya berapa', 1, '2025-04-12 17:00:31');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Notification',
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `recipient_id` int DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `scheduled_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `title`, `message`, `recipient_id`, `is_read`, `created_by`, `created_at`, `scheduled_at`) VALUES
(8, '', 'New payment: asd asd has made a payment of IDR 550.000', 1, 1, 6, '2025-03-25 03:19:15', NULL),
(10, '', 'New payment: asd asd has made a payment of IDR 550.000', 1, 1, 6, '2025-03-25 03:19:45', NULL),
(12, '', 'New payment: asd asd has made a payment of IDR 550.000', 1, 1, 6, '2025-03-25 03:21:14', NULL),
(14, '', 'New payment: asd asd has made a payment of IDR 550.000', 1, 1, 6, '2025-03-25 03:21:26', NULL),
(16, '', 'New payment: asd asd has made a payment of IDR 550.000', 1, 1, 6, '2025-03-25 03:22:04', NULL),
(17, '', 'Your payment of IDR 550.000 has been received and is being processed.', 6, 0, 1, '2025-03-25 03:22:04', NULL),
(18, '', 'New payment: asd asd has made a payment of IDR 550.000', 1, 1, 6, '2025-03-26 11:30:51', NULL),
(19, '', 'Your payment of IDR 550.000 has been successfully processed and marked as paid.', 6, 0, 1, '2025-03-26 11:30:51', NULL),
(20, '', 'New message from asd asd: hai', 7, 0, 6, '2025-03-27 05:06:08', NULL),
(21, '', 'New message from asd asd: hai', 7, 0, 6, '2025-03-27 05:08:54', NULL),
(22, '', 'New payment: dawha asd has made a payment of IDR 550.000', 1, 1, 6, '2025-03-29 13:22:21', NULL),
(23, '', 'Your payment of IDR 550.000 has been successfully processed and marked as paid.', 6, 0, 1, '2025-03-29 13:22:21', NULL),
(24, 'Room Change', 'Room change: dawha asd has changed from Room 15 to Room 17', 1, 1, 6, '2025-03-29 13:31:53', NULL),
(25, 'Payment Reminder', 'bayar blough', 6, 0, 7, '2025-04-03 12:38:26', '2025-04-03 19:38:00'),
(26, '', 'New payment: dawha asd has made a payment of IDR 650.000', 1, 1, 6, '2025-04-07 08:28:27', NULL),
(27, '', 'Your payment of IDR 650.000 has been successfully processed and marked as paid.', 6, 0, 1, '2025-04-07 08:28:27', NULL),
(28, 'Room Change', 'Room change: sadad asdad has changed from Room 13 to Room 15', 1, 1, 10, '2025-04-07 08:50:09', NULL),
(29, '', 'New payment: dawha asd has made a payment of IDR 650.000', 1, 1, 6, '2025-04-08 04:51:51', NULL),
(30, '', 'Your payment of IDR 650.000 has been successfully processed and marked as paid.', 6, 0, 1, '2025-04-08 04:51:51', NULL),
(31, 'test', 'test', 10, 0, 1, '2025-04-10 15:15:03', NULL),
(32, 'New Message', 'New message from test aja: hai admin', 1, 1, 12, '2025-04-12 16:34:18', NULL),
(33, 'New Message', 'New message from test aja: hello?', 1, 1, 12, '2025-04-12 16:41:54', NULL),
(34, 'New Message', 'New message from test aja: hello?', 1, 1, 12, '2025-04-12 16:46:55', NULL),
(35, 'New Message', 'New message from test aja: hello', 5, 0, 12, '2025-04-12 16:53:13', NULL),
(36, 'New Message', 'New message from Admin User: hai kak ada yang bisa di bantu...', 12, 1, 1, '2025-04-12 16:59:45', NULL),
(37, 'New Message', 'New message from test aja: kamar yang bagus kost apa ya k...', 1, 1, 12, '2025-04-12 16:59:59', NULL),
(38, 'New Message', 'New message from Admin User: hmm tergantung kak, dana kakan...', 12, 1, 1, '2025-04-12 17:00:31', NULL),
(39, 'Payment Reminder', 'Bayar woi', 12, 1, 1, '2025-04-12 17:01:25', NULL),
(40, 'Welcome to Aula Kost', 'welkomm', 12, 1, 1, '2025-04-12 17:02:19', NULL),
(42, 'New Announcement', 'New announcement: test', 3, 0, 1, '2025-04-12 17:48:19', NULL),
(43, 'New Announcement', 'New announcement: test', 4, 0, 1, '2025-04-12 17:48:19', NULL),
(44, 'New Announcement', 'New announcement: test', 6, 0, 1, '2025-04-12 17:48:19', NULL),
(45, 'New Announcement', 'New announcement: test', 8, 0, 1, '2025-04-12 17:48:19', NULL),
(46, 'New Announcement', 'New announcement: test', 9, 0, 1, '2025-04-12 17:48:19', NULL),
(47, 'New Announcement', 'New announcement: test', 10, 0, 1, '2025-04-12 17:48:19', NULL),
(48, 'New Announcement', 'New announcement: test', 11, 0, 1, '2025-04-12 17:48:19', NULL),
(49, 'New Announcement', 'New announcement: test', 12, 0, 1, '2025-04-12 17:48:19', NULL),
(56, 'Notification', 'New booking: User ID 12 has booked room Room 13', 1, 0, 12, '2025-04-12 18:26:45', NULL),
(57, 'Notification', 'Your booking for room Room 13 has been confirmed. Please complete your payment.', 12, 0, 1, '2025-04-12 18:26:45', NULL),
(58, 'Notification', 'New payment: test aja has made a payment of IDR 600.000', 1, 0, 12, '2025-04-12 18:35:00', NULL),
(59, 'Notification', 'Your payment of IDR 600.000 has been successfully processed and marked as paid.', 12, 0, 1, '2025-04-12 18:35:00', NULL),
(60, 'Notification', 'New booking: User ID 13 has booked room Room 18', 1, 0, 13, '2025-04-12 18:36:33', NULL),
(61, 'Notification', 'Your booking for room Room 18 has been confirmed. Please complete your payment.', 13, 0, 1, '2025-04-12 18:36:33', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int NOT NULL,
  `tenant_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` datetime NOT NULL,
  `payment_method` enum('cash','transfer','qris','midtrans') COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('paid','unpaid','pending') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `order_id` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `transaction_id` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `transaction_time` datetime DEFAULT NULL,
  `transaction_status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `payment_link` text COLLATE utf8mb4_general_ci,
  `midtrans_token` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `tenant_id`, `amount`, `payment_date`, `payment_method`, `status`, `created_at`, `order_id`, `transaction_id`, `payment_type`, `transaction_time`, `transaction_status`, `payment_link`, `midtrans_token`) VALUES
(1, 1, 500000.00, '2023-12-01 10:30:00', 'transfer', 'paid', '2025-03-19 13:39:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 2, 600000.00, '2023-11-30 14:45:00', 'cash', 'paid', '2025-03-19 13:39:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 3, 700000.00, '2023-12-05 09:15:00', 'qris', 'paid', '2025-03-19 13:39:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 4, 550000.00, '2025-03-20 09:13:03', 'qris', 'paid', '2025-03-20 02:13:03', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 4, 550000.00, '2025-03-25 10:19:15', 'qris', 'unpaid', '2025-03-25 03:19:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 4, 550000.00, '2025-03-25 10:19:45', 'qris', 'unpaid', '2025-03-25 03:19:45', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 4, 550000.00, '2025-03-25 10:21:14', 'qris', 'unpaid', '2025-03-25 03:21:14', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 4, 550000.00, '2025-03-25 10:21:26', 'qris', 'unpaid', '2025-03-25 03:21:26', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 4, 550000.00, '2025-03-25 10:22:04', 'cash', 'unpaid', '2025-03-25 03:22:04', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 4, 550000.00, '2025-03-26 18:30:51', 'qris', 'paid', '2025-03-26 11:30:51', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 4, 550000.00, '2025-03-29 20:22:21', 'qris', 'paid', '2025-03-29 13:22:21', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(24, 4, 650000.00, '2025-04-07 15:28:27', 'qris', 'paid', '2025-04-07 08:28:27', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(25, 5, 600000.00, '2025-04-07 15:49:20', 'transfer', '', '2025-04-07 08:49:20', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(26, 4, 650000.00, '2025-04-08 11:51:51', 'cash', 'paid', '2025-04-08 04:51:51', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(32, 8, 600000.00, '2025-04-13 01:34:22', 'transfer', 'pending', '2025-04-12 18:34:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(33, 8, 600000.00, '2025-04-13 01:35:00', 'transfer', 'paid', '2025-04-12 18:35:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(34, 9, 800000.00, '2025-04-13 01:36:33', 'midtrans', 'unpaid', '2025-04-12 18:36:33', 'ROOM-6-1744482993', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('available','occupied') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'available',
  `description` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `name`, `price`, `status`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Room 12', 500000.00, 'occupied', 'Comfortable room with all amenities including AC, private bathroom, and study desk.', '2025-03-19 13:39:30', '2025-04-12 17:49:09'),
(2, 'Room 14', 600000.00, 'occupied', 'Spacious room with large windows, AC, private bathroom, and study area.', '2025-03-19 13:39:30', '2025-04-12 17:49:09'),
(3, 'Room 15', 550000.00, 'occupied', 'Cozy room with natural lighting, AC, shared bathroom, and study desk.', '2025-03-19 13:39:30', '2025-04-12 17:49:09'),
(4, 'Room 16', 700000.00, 'occupied', 'Premium room with AC, private bathroom, study desk, and small balcony.', '2025-03-19 13:39:30', '2025-04-12 17:49:09'),
(5, 'Room 17', 650000.00, 'occupied', 'Modern room with AC, private bathroom, study area, and good view.', '2025-03-19 13:39:30', '2025-04-12 17:49:09'),
(6, 'Room 18', 800000.00, 'occupied', 'Deluxe room with AC, private bathroom, large study area, and balcony.', '2025-03-19 13:39:30', '2025-04-12 18:36:33'),
(7, 'Room 13', 600000.00, 'occupied', 'asdasdad', '2025-04-03 12:24:56', '2025-04-12 18:26:45');

-- --------------------------------------------------------

--
-- Table structure for table `room_change_logs`
--

CREATE TABLE `room_change_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `old_room_id` int DEFAULT NULL,
  `new_room_id` int NOT NULL,
  `change_reason` text COLLATE utf8mb4_general_ci,
  `change_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `room_features`
--

CREATE TABLE `room_features` (
  `id` int NOT NULL,
  `room_id` int NOT NULL,
  `feature_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_features`
--

INSERT INTO `room_features` (`id`, `room_id`, `feature_name`) VALUES
(1, 1, 'Air Conditioning'),
(2, 1, 'Private Bathroom'),
(3, 1, 'Study Desk'),
(4, 1, 'Free WiFi'),
(5, 2, 'Air Conditioning'),
(6, 2, 'Private Bathroom'),
(7, 2, 'Study Desk'),
(8, 2, 'Free WiFi'),
(9, 2, 'Cleaning Service'),
(10, 3, 'Air Conditioning'),
(11, 3, 'Shared Bathroom'),
(12, 3, 'Study Desk'),
(13, 3, 'Free WiFi');

-- --------------------------------------------------------

--
-- Table structure for table `room_images`
--

CREATE TABLE `room_images` (
  `id` int NOT NULL,
  `room_id` int NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room_images`
--

INSERT INTO `room_images` (`id`, `room_id`, `image_path`, `is_primary`, `created_at`) VALUES
(1, 1, 'room1.jpg', 1, '2025-04-12 17:53:38'),
(2, 1, 'room2.jpg', 0, '2025-04-12 17:53:38'),
(3, 1, 'room3.jpg', 0, '2025-04-12 17:53:38'),
(4, 2, 'room2.jpg', 1, '2025-04-12 17:53:38'),
(5, 2, 'room3.jpg', 0, '2025-04-12 17:53:38'),
(6, 3, 'room3.jpg', 1, '2025-04-12 17:53:38'),
(7, 3, 'room4.jpg', 0, '2025-04-12 17:53:38'),
(8, 4, 'room4.jpg', 1, '2025-04-12 17:53:38'),
(9, 5, 'room5.jpg', 1, '2025-04-12 17:53:38'),
(10, 6, 'room6.jpg', 1, '2025-04-12 17:53:38'),
(12, 7, 'room_7_67faa8b45a813.png', 1, '2025-04-12 17:53:56');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `room_id` int NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `user_id`, `room_id`, `start_date`, `end_date`, `status`) VALUES
(1, 2, 1, '2023-01-01', NULL, 'active'),
(2, 3, 2, '2023-02-01', NULL, 'active'),
(3, 4, 4, '2023-03-01', NULL, 'active'),
(4, 6, 5, '2025-03-30', NULL, 'active'),
(5, 10, 3, '2025-04-08', NULL, 'active'),
(8, 12, 7, '2025-04-12', NULL, 'active'),
(9, 13, 6, '2025-04-12', NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `bio` text COLLATE utf8mb4_general_ci,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('admin','tenant') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'tenant',
  `profile_image` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other') COLLATE utf8mb4_general_ci DEFAULT NULL,
  `street` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `postal_code` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `state` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `room_id` int DEFAULT NULL,
  `profile_photo` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `phone`, `bio`, `password`, `role`, `profile_image`, `dob`, `gender`, `street`, `city`, `postal_code`, `state`, `country`, `created_at`, `room_id`, `profile_photo`) VALUES
(1, 'Admin', 'User', 'admin@aulakost.com', '+6281234567890', NULL, 'admin123', 'admin', 'profile_1_1744480855.jpeg', NULL, NULL, 'test', 'test', '23442', 'test', 'test', '2025-03-19 13:39:30', NULL, 'profile_1_1744480502.png'),
(2, 'John', 'Doe', 'john.doe@example.com', '+6281234567891', NULL, 'johndue123\r\n', 'tenant', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-03-19 13:39:30', NULL, NULL),
(3, 'Jane', 'Smith', 'jane.smith@example.com', '+6281234567892', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tenant', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-03-19 13:39:30', NULL, NULL),
(4, 'Mike', 'Johnson', 'mike.johnson@example.com', '+6281234567893', NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'tenant', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-03-19 13:39:30', NULL, NULL),
(5, 'Dawha', 'Daud', 'dawha@gmail.com', '087869577856', NULL, '$2y$10$/fajGC9.2CfxN9shtjmvSeCF8uJOWGGa/Bc3kdXds3cJ7qMMhiU4S', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-03-19 13:41:26', NULL, NULL),
(6, 'dawha', 'asd', 'asd@gmail.com', '1233212313', NULL, '$2y$10$Gp6bl7QK1KN4iPrbEtLCQe/AQ6XfwNqcG8YblWX.BfMhgHrlQXYQW', 'tenant', NULL, NULL, NULL, '', '', '', NULL, '', '2025-03-19 13:59:46', NULL, NULL),
(7, 'dsa', 'dsa', 'dsa@gmail.com', '12312312313', NULL, '$2y$10$IUlOb3GHpME19No3eM8bJe4w7pBUeXzXFUzE94m1q6Mlr9AyWyDne', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-03-19 18:31:10', NULL, NULL),
(8, 'Dawha', 'asd', 'dawhaasd@gmail.com', '9834147192', NULL, '$2y$10$EpGJf8koXo5zlqXDlZe4S.WdsaFrnt6PcPN..dXyr8KylCewG7mk2', 'tenant', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-03-28 05:29:50', NULL, NULL),
(9, 'Dawha', 'dsa', '123@gmail.com', '2323', NULL, '$2y$10$8z5E1RO/5MM3kFOYc78Mle6.TZk9mI2wL/zlWvq4ZEQtwpdMPKKA6', 'tenant', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-03-30 05:49:02', NULL, NULL),
(10, 'sadad', 'asdad', 'kl@gmail.com', '1231231323', NULL, '$2y$10$vQVPgINAhNkYjxSgpRlPx.VfiKvYKtcYt9DF.F32g7Jds5JLhHZuG', 'tenant', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-07 07:42:40', 7, NULL),
(11, 'Dawha', 'Daud', 'daud@gmail.com', '293812389', NULL, '$2y$10$O4tRx0hOu8RoA4R6XV/FZuduezMKV..cYQJLboftA82j99zj3vVG.', 'tenant', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-08 04:50:38', NULL, NULL),
(12, 'test', 'aja', 'test@gmail.com', '081227848422', NULL, '$2y$10$hq0FqJSKd31i4Mtn0qQUCu4R4mot9ftAw/66UBN2hi6bLGeEWSaMy', 'tenant', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-12 16:24:44', 7, NULL),
(13, 'test2', 'test2', 'test2@gmail.com', '12345676543', NULL, '$2y$10$C4x1ejUx8DcamRL4SJtq8uWLda1KDtVU70T1KINxvQXBLYhpiyLT.', 'tenant', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-04-12 18:36:08', 6, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `announcement_images`
--
ALTER TABLE `announcement_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `announcement_id` (`announcement_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipient_id` (`recipient_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `idx_payments_order_id` (`order_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `room_change_logs`
--
ALTER TABLE `room_change_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `old_room_id` (`old_room_id`),
  ADD KEY `new_room_id` (`new_room_id`);

--
-- Indexes for table `room_features`
--
ALTER TABLE `room_features`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `room_images`
--
ALTER TABLE `room_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `room_id` (`room_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `announcement_images`
--
ALTER TABLE `announcement_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `room_change_logs`
--
ALTER TABLE `room_change_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `room_features`
--
ALTER TABLE `room_features`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `room_images`
--
ALTER TABLE `room_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `announcement_images`
--
ALTER TABLE `announcement_images`
  ADD CONSTRAINT `announcement_images_ibfk_1` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD CONSTRAINT `maintenance_requests_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_requests_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `notifications_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `room_features`
--
ALTER TABLE `room_features`
  ADD CONSTRAINT `room_features_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_images`
--
ALTER TABLE `room_images`
  ADD CONSTRAINT `room_images_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tenants`
--
ALTER TABLE `tenants`
  ADD CONSTRAINT `tenants_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `tenants_ibfk_2` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
