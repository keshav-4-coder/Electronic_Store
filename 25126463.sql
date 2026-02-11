-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jan 24, 2026 at 06:00 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `25126463`
--

-- --------------------------------------------------------

--
-- Table structure for table `CATEGORY`
--

CREATE TABLE `CATEGORY` (
  `category_id` int(10) UNSIGNED NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `category_image` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `CATEGORY`
--

INSERT INTO `CATEGORY` (`category_id`, `category_name`, `description`, `category_image`, `created_at`) VALUES
(1, 'Mobile Phones', 'Leatest Smart Phones, Iphone, Android', 'https://images.pexels.com/photos/18311092/pexels-photo-18311092.jpeg\n', '2026-01-24 20:44:49'),
(2, 'Laptops', 'Portable computers for work, study, and gaming', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8', '2026-01-24 20:50:21'),
(3, 'Smartphones', 'Latest Android and iOS smartphones', 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9', '2026-01-24 20:50:21'),
(4, 'Tablets', 'Touchscreen tablets for entertainment and productivity', 'https://images.unsplash.com/photo-1587825140708-dfaf72ae4b04', '2026-01-24 20:50:21'),
(5, 'Desktop Computers', 'Powerful desktop PCs for home and office use', 'https://images.unsplash.com/photo-1587202372775-e229f172b9d7', '2026-01-24 20:50:21'),
(6, 'Computer Accessories', 'Keyboards, mouse, cables, and other accessories', 'https://images.unsplash.com/photo-1587825140909-2f24c62f20a3', '2026-01-24 20:50:21'),
(7, 'Gaming', 'Gaming consoles, controllers, and gaming gear', 'https://images.unsplash.com/photo-1606813907291-d86efa9b94db', '2026-01-24 20:50:21'),
(8, 'Televisions', 'Smart TVs, LED, OLED, and QLED televisions', 'https://images.unsplash.com/photo-1593359677879-a4bb92f829d1', '2026-01-24 20:50:21'),
(9, 'Audio Devices', 'Headphones, speakers, and sound systems', 'https://images.unsplash.com/photo-1518441980987-6d7d0c1c6e8f', '2026-01-24 20:50:21'),
(10, 'Cameras', 'Digital cameras, DSLRs, and accessories', 'https://images.unsplash.com/photo-1519183071298-a2962be96c8f', '2026-01-24 20:50:21'),
(11, 'Wearable Tech', 'Smartwatches, fitness bands, and wearables', 'https://images.unsplash.com/photo-1516574187841-cb9cc2ca948b', '2026-01-24 20:50:21'),
(12, 'Networking Devices', 'Routers, modems, and networking equipment', 'https://images.unsplash.com/photo-1580894908361-967195033215', '2026-01-24 20:50:21'),
(13, 'Home Appliances', 'Essential appliances for everyday home use', 'https://images.unsplash.com/photo-1581579184689-1b3b6cdb5c15', '2026-01-24 20:50:43'),
(14, 'Kitchen Appliances', 'Appliances to make cooking faster and easier', 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c', '2026-01-24 20:50:43'),
(15, 'IoT Devices', 'Smart Internet of Things devices for automation and control', 'https://images.unsplash.com/photo-1518770660439-4636190af475', '2026-01-24 20:50:43');

-- --------------------------------------------------------

--
-- Table structure for table `ORDERS`
--

CREATE TABLE `ORDERS` (
  `order_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `order_date` datetime NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(12,2) NOT NULL,
  `shipping_address` text NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ORDERS`
--

INSERT INTO `ORDERS` (`order_id`, `user_id`, `order_date`, `total_amount`, `shipping_address`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 3, '2026-01-24 21:00:10', 2600.00, 'maitidevi, kathmandu\nPhone: 9800000000', 'delivered', NULL, '2026-01-24 21:00:10', '2026-01-24 21:21:36'),
(2, 3, '2026-01-24 21:44:50', 48100.00, 'Maitidevi , Kathmandu, Kathmandu\nPhone: 9800000000', 'pending', NULL, '2026-01-24 21:44:50', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ORDER_ITEMS`
--

CREATE TABLE `ORDER_ITEMS` (
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ORDER_ITEMS`
--

INSERT INTO `ORDER_ITEMS` (`order_item_id`, `order_id`, `product_id`, `quantity`, `unit_price`, `created_at`) VALUES
(1, 1, 3, 1, 2500.00, '2026-01-24 21:00:10'),
(2, 2, 1, 1, 48000.00, '2026-01-24 21:44:50');

-- --------------------------------------------------------

--
-- Table structure for table `PAYMENTS`
--

CREATE TABLE `PAYMENTS` (
  `payment_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('cod','esewa','khalti','fonepay','imepay','card','bank') NOT NULL,
  `payment_status` enum('pending','pending_verification','paid','failed','rejected') DEFAULT 'pending',
  `payment_screenshot` varchar(255) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `PAYMENTS`
--

INSERT INTO `PAYMENTS` (`payment_id`, `order_id`, `amount`, `payment_method`, `payment_status`, `payment_screenshot`, `transaction_id`, `payment_date`, `notes`, `created_at`, `verified_at`, `verified_by`, `rejection_reason`) VALUES
(9, 1, 2600.00, 'esewa', 'paid', 'payment_1_1769267961.jpg', NULL, NULL, NULL, '2026-01-24 21:00:10', '2026-01-24 15:36:26', 2, NULL),
(10, 2, 48100.00, 'khalti', 'pending', NULL, NULL, NULL, NULL, '2026-01-24 21:44:50', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `PRODUCTS`
--

CREATE TABLE `PRODUCTS` (
  `product_id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED DEFAULT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `slug` varchar(250) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `product_image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `PRODUCTS`
--

INSERT INTO `PRODUCTS` (`product_id`, `seller_id`, `category_id`, `product_name`, `slug`, `description`, `price`, `stock`, `product_image`, `is_active`, `created_at`, `updated_at`) VALUES
(1, NULL, 1, 'infinix gt 30 pro', 'infinix-gt-30-pro', 'The budget Gaming Smart Phone with cooler.', 48000.00, 14, 'https://imgs.search.brave.com/DBcGKAkJlgeOXvkekA15zOqds_5TQirmBfXEiu3zzpE/rs:fit:500:0:1:0/g:ce/aHR0cHM6Ly9jZG4u/bW9zLmNtcy5mdXR1/cmVjZG4ubmV0L3VZ/Y21teFdpc1ZWNFph/NXNOZndmVmcuanBn', 1, '2026-01-24 20:47:03', '2026-01-24 21:44:50'),
(2, 2, 1, 'Iphone 17 pro max', 'iphone-17-pro-max', 'The Professional Best Smartphone.', 225000.00, 25, 'product_6974e0c99853b8.73764140.webp', 1, '2026-01-24 20:55:01', NULL),
(3, 2, 13, 'Heater', 'heater', 'The best room heater.', 2500.00, 39, 'product_6974e15010ae37.81178265.jpg', 0, '2026-01-24 20:57:16', '2026-01-24 22:20:27'),
(29, 2, 6, 'Mechanical Keyboard', 'mechanical-keyboard', 'RGB mechanical keyboard', 12000.00, 50, 'product_6974f77367ab53.80705975.jpg', 1, '2026-01-24 22:08:01', '2026-01-24 22:31:43'),
(34, 2, 7, 'PlayStation 5', 'playstation-5', 'Sony gaming console', 225000.00, 7, 'product_6974f7e3d2e097.58244673.jpg', 1, '2026-01-24 22:08:01', '2026-01-24 22:33:35'),
(36, 2, 7, 'Gaming Headset', 'gaming-headset', 'Surround sound headset', 18000.00, 35, 'product_6974f7b9e49af7.24204680.jpg', 1, '2026-01-24 22:08:01', '2026-01-24 22:32:53'),
(38, 2, 7, 'RGB Controller', 'rgb-controller', 'Wireless controller', 14000.00, 40, 'product_6974f84b70e4b0.84918242.png', 1, '2026-01-24 22:08:01', '2026-01-24 22:35:19');

-- --------------------------------------------------------

--
-- Table structure for table `USERS`
--

CREATE TABLE `USERS` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone_no` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `role` enum('buyer','seller','admin') NOT NULL DEFAULT 'buyer',
  `security_question` varchar(255) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `USERS`
--

INSERT INTO `USERS` (`user_id`, `full_name`, `username`, `email`, `password`, `phone_no`, `address`, `profile_picture`, `role`, `security_question`, `security_answer`, `is_active`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'System Administrator', 'admin', 'admin@gmail.com', '$2y$10$U5PAi0waGWwwpuQbiQ/0q..izW/MZj/PBwPJDpcH2./7g09Xsnr32', '9841871781', '', 'profile_1_1769270603.webp', 'admin', 'What city were you born in?', '$2y$10$G3eQjg6C7wM17RNDq8dKZ.RHEGerVbAO9MSpg9FsvnK4kHntJ8BfW', 1, '2026-01-24 20:33:43', '2026-01-24 22:41:18', '2026-01-24 22:41:18'),
(2, 'sumit kc', 'sumit123', 'sumit@gmail.com', '$2y$10$zf26SmxCTvnxAkW7FEEXEOyxBbY3Xob0jtHQWNpfses9kYOZZYn0a', '9800000000', 'baneshwor, kathmandu', 'profile_2_1769271054.jpg', 'seller', 'What city were you born in?', '$2y$10$4TRvAoAy8G4R7UVSe85uzO5gxnLFKzcqGRiEphdKnQyzTiVU/xQQC', 1, '2026-01-24 20:48:27', '2026-01-24 22:41:46', '2026-01-24 22:41:46'),
(3, 'Bishal Bista', 'bisu', 'bisal@gmail.com', '$2y$10$6l3NJ5yeDRNoYwzhyDTlYesXkKWNK0TP9xLjcrDgLII6NZ8ZJQG8m', '9800000000', 'Maitidevi , Kathmandu', 'profile_3_1769270527.jpg', 'buyer', 'What city were you born in?', '$2y$10$VdTwAV.TWQzqGOxQShUFUOHr6SxTsCBCWlETWIKFSfrHEvREpcZVS', 1, '2026-01-24 20:58:58', '2026-01-24 22:42:23', '2026-01-24 22:42:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `CATEGORY`
--
ALTER TABLE `CATEGORY`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_name` (`category_name`);

--
-- Indexes for table `ORDERS`
--
ALTER TABLE `ORDERS`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `ORDER_ITEMS`
--
ALTER TABLE `ORDER_ITEMS`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `PAYMENTS`
--
ALTER TABLE `PAYMENTS`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_order_payment` (`order_id`);

--
-- Indexes for table `PRODUCTS`
--
ALTER TABLE `PRODUCTS`
  ADD PRIMARY KEY (`product_id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_seller` (`seller_id`),
  ADD KEY `idx_category` (`category_id`);

--
-- Indexes for table `USERS`
--
ALTER TABLE `USERS`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `CATEGORY`
--
ALTER TABLE `CATEGORY`
  MODIFY `category_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `ORDERS`
--
ALTER TABLE `ORDERS`
  MODIFY `order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `ORDER_ITEMS`
--
ALTER TABLE `ORDER_ITEMS`
  MODIFY `order_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `PAYMENTS`
--
ALTER TABLE `PAYMENTS`
  MODIFY `payment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `PRODUCTS`
--
ALTER TABLE `PRODUCTS`
  MODIFY `product_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `USERS`
--
ALTER TABLE `USERS`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ORDERS`
--
ALTER TABLE `ORDERS`
  ADD CONSTRAINT `fk_order_user` FOREIGN KEY (`user_id`) REFERENCES `USERS` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ORDER_ITEMS`
--
ALTER TABLE `ORDER_ITEMS`
  ADD CONSTRAINT `fk_item_order` FOREIGN KEY (`order_id`) REFERENCES `ORDERS` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_item_product` FOREIGN KEY (`product_id`) REFERENCES `PRODUCTS` (`product_id`);

--
-- Constraints for table `PAYMENTS`
--
ALTER TABLE `PAYMENTS`
  ADD CONSTRAINT `fk_payment_order` FOREIGN KEY (`order_id`) REFERENCES `ORDERS` (`order_id`) ON DELETE CASCADE;

--
-- Constraints for table `PRODUCTS`
--
ALTER TABLE `PRODUCTS`
  ADD CONSTRAINT `fk_product_category` FOREIGN KEY (`category_id`) REFERENCES `CATEGORY` (`category_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_product_seller` FOREIGN KEY (`seller_id`) REFERENCES `USERS` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
