-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 21, 2026 at 03:05 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `posv1`
--

-- --------------------------------------------------------

--
-- Table structure for table `addons`
--

CREATE TABLE `addons` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_global` tinyint(1) DEFAULT 0,
  `product_id` int(11) DEFAULT NULL,
  `max_quantity` int(11) DEFAULT 1,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addons`
--

INSERT INTO `addons` (`id`, `name`, `description`, `price`, `is_global`, `product_id`, `max_quantity`, `is_available`, `created_at`) VALUES
(1, 'Cheese', 'Yummy', 12.00, 0, NULL, 1, 1, '2026-01-19 15:38:43'),
(2, 'Cheese', 'Cheesier', 15.00, 0, NULL, 1, 1, '2026-01-19 15:51:50'),
(3, 'Veggies', 'Salad Veggies', 5.00, 0, NULL, 1, 1, '2026-01-19 16:10:54'),
(4, 'Cesar Salad', 'Fresh', 15.00, 0, NULL, 1, 1, '2026-01-19 16:27:37');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `icon_class` varchar(50) DEFAULT NULL,
  `color_code` varchar(7) DEFAULT '#6c757d',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `display_order`, `is_active`, `icon_class`, `color_code`, `created_at`) VALUES
(1, 'Burgers', NULL, 1, 1, '', '#d0e628', '2026-01-19 12:38:16'),
(2, 'Sides', NULL, 2, 1, NULL, '#6c757d', '2026-01-19 12:38:16'),
(3, 'Drinks', NULL, 3, 1, NULL, '#6c757d', '2026-01-19 12:38:16'),
(4, 'Pizza', NULL, 4, 1, NULL, '#6c757d', '2026-01-19 12:38:16'),
(5, 'Desserts', NULL, 5, 1, NULL, '#6c757d', '2026-01-19 12:38:16'),
(6, 'Vape', 'Single Flavor', 6, 1, '', '#6c757d', '2026-01-21 11:49:44'),
(7, 'Milktea', 'All Flavors', 7, 1, 'fas fa-coffee', '#6c757d', '2026-01-21 11:52:28');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `change_type` enum('sale','restock','adjustment','waste','online_order') NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `new_stock_level` int(11) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_logs`
--

INSERT INTO `inventory_logs` (`id`, `product_id`, `user_id`, `change_type`, `quantity_change`, `new_stock_level`, `reference_id`, `notes`, `created_at`) VALUES
(1, 13, NULL, 'sale', -1, 0, 1, 'Order completed', '2026-01-21 05:10:22'),
(2, 14, NULL, 'sale', -1, 0, 1, 'Order completed', '2026-01-21 05:10:22'),
(4, 13, NULL, 'sale', -1, 0, 2, 'Order completed', '2026-01-21 06:13:18'),
(5, 13, NULL, 'sale', -1, 0, 3, 'Order completed', '2026-01-21 06:20:42'),
(6, 14, NULL, 'sale', -1, 0, 3, 'Order completed', '2026-01-21 06:20:42'),
(8, 13, NULL, 'sale', -1, 0, 4, 'Order completed', '2026-01-21 06:39:05'),
(9, 13, NULL, 'sale', -1, 0, 10, 'Order completed', '2026-01-21 08:08:46'),
(10, 14, NULL, 'sale', -2, 0, 10, 'Order completed', '2026-01-21 08:08:46'),
(12, 13, NULL, 'sale', -1, 0, 5, 'Order completed', '2026-01-21 08:10:58');

-- --------------------------------------------------------

--
-- Table structure for table `kitchen_stations`
--

CREATE TABLE `kitchen_stations` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(100) DEFAULT NULL,
  `color_code` varchar(7) DEFAULT '#3498db',
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kitchen_stations`
--

INSERT INTO `kitchen_stations` (`id`, `name`, `description`, `color_code`, `display_order`, `is_active`, `created_at`) VALUES
(1, 'Burger Station', 'All burger preparations', '#e74c3c', 1, 1, '2026-01-19 11:49:51'),
(2, 'Fry Station', 'Fries and sides', '#f39c12', 2, 1, '2026-01-19 11:49:51'),
(3, 'Drink Station', 'Beverages and drinks', '#3498db', 3, 1, '2026-01-19 11:49:51'),
(4, 'Pizza Station', 'Pizza preparation', '#2ecc71', 4, 1, '2026-01-19 11:49:51'),
(5, 'Dessert Station', 'Desserts and sweets', '#9b59b6', 5, 1, '2026-01-19 11:49:51');

-- --------------------------------------------------------

--
-- Table structure for table `online_orders`
--

CREATE TABLE `online_orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(20) DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `customer_nickname` varchar(50) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `tracking_pin` char(4) DEFAULT NULL,
  `order_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`order_data`)),
  `status` enum('pending','confirmed','preparing','ready','completed','cancelled') DEFAULT NULL,
  `estimated_time` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `online_orders`
--

INSERT INTO `online_orders` (`id`, `order_number`, `session_id`, `customer_nickname`, `customer_phone`, `tracking_pin`, `order_data`, `status`, `estimated_time`, `created_at`, `expires_at`) VALUES
(1, 'ONLINE-260121-0001', '376l8ql5n24mc3rr51uhl6es7q', '', '', '0874', '{\"cart\":{\"13\":{\"product_id\":13,\"name\":\"Bacon Burger\",\"price\":220,\"quantity\":1,\"addons\":[],\"special_request\":\"Hot Sauce please.\"},\"14\":{\"product_id\":14,\"name\":\"French Fries\",\"price\":90,\"quantity\":2,\"addons\":[],\"special_request\":\"\"}},\"totals\":{\"subtotal\":400,\"tax\":48,\"total\":448},\"items_count\":2}', 'pending', 15, '2026-01-21 08:01:28', NULL),
(2, 'ONLINE-260121-0002', '376l8ql5n24mc3rr51uhl6es7q', 'Iwi', '', '3204', '{\"cart\":{\"14\":{\"product_id\":14,\"name\":\"French Fries\",\"price\":90,\"quantity\":1,\"addons\":[],\"special_request\":\"\"}},\"totals\":{\"subtotal\":90,\"tax\":10.799999999999999,\"total\":100.8},\"items_count\":1}', 'pending', 15, '2026-01-21 13:15:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(20) NOT NULL,
  `order_type` enum('walkin','online') NOT NULL,
  `customer_nickname` varchar(50) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','preparing','ready','completed','cancelled') DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `confirmed_by` int(11) DEFAULT NULL,
  `order_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `estimated_completion` time DEFAULT NULL,
  `notification_count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `order_type`, `customer_nickname`, `customer_phone`, `subtotal`, `tax_amount`, `discount_amount`, `total_amount`, `status`, `created_by`, `confirmed_by`, `order_date`, `created_at`, `confirmed_at`, `completed_at`, `special_instructions`, `estimated_completion`, `notification_count`) VALUES
(1, 'WALK-IN-2601001', 'walkin', NULL, NULL, 322.00, 38.64, 0.00, 360.64, 'completed', 1, NULL, '2026-01-21', '2026-01-21 05:04:45', NULL, NULL, NULL, NULL, 0),
(2, 'WALK-IN-2601002', 'walkin', 'Louie', NULL, 220.00, 26.40, 0.00, 246.40, 'completed', 1, NULL, '2026-01-21', '2026-01-21 05:12:52', NULL, NULL, NULL, NULL, 0),
(3, 'ONLINE-2601001', 'online', 'Cali', NULL, 322.00, 38.64, 0.00, 360.64, 'completed', 1, NULL, '2026-01-21', '2026-01-21 06:14:23', NULL, NULL, NULL, NULL, 1),
(4, 'ONLINE-2601002', 'online', 'Cali', NULL, 232.00, 27.84, 0.00, 259.84, 'completed', 1, NULL, '2026-01-21', '2026-01-21 06:32:08', NULL, NULL, NULL, NULL, 1),
(5, 'ONLINE-2601003', 'online', 'test', NULL, 220.00, 26.40, 0.00, 246.40, 'completed', 1, NULL, '2026-01-21', '2026-01-21 06:41:35', NULL, NULL, NULL, NULL, 0),
(10, 'ONLINE-260121-0001', 'online', '', '', 400.00, 48.00, 0.00, 448.00, 'completed', NULL, NULL, '2026-01-21', '2026-01-21 08:01:28', NULL, NULL, NULL, NULL, 0),
(11, 'ONLINE-260121-0002', 'online', 'Iwi', '', 90.00, 10.80, 0.00, 100.80, 'pending', NULL, NULL, '2026-01-21', '2026-01-21 13:15:11', NULL, NULL, NULL, NULL, 0);

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `after_order_status_change` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    -- When order changes to COMPLETED
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        -- Reduce stock for all items in this order
        UPDATE products p
        JOIN order_items oi ON p.id = oi.product_id
        SET p.stock = p.stock - oi.quantity
        WHERE oi.order_id = NEW.id
        AND p.stock IS NOT NULL;
        
        -- Log the stock reduction
        INSERT INTO inventory_logs (product_id, change_type, quantity_change, reference_id, notes)
        SELECT 
            oi.product_id,
            'sale',
            -oi.quantity,
            NEW.id,
            'Order completed'
        FROM order_items oi
        WHERE oi.order_id = NEW.id;
    
    -- When order changes to CANCELLED (restore stock if it was previously completed)
    ELSEIF NEW.status = 'cancelled' AND OLD.status = 'completed' THEN
        -- Restore stock that was deducted when order was completed
        UPDATE products p
        JOIN order_items oi ON p.id = oi.product_id
        SET p.stock = p.stock + oi.quantity
        WHERE oi.order_id = NEW.id
        AND p.stock IS NOT NULL;
        
        -- Log the stock restoration
        INSERT INTO inventory_logs (product_id, change_type, quantity_change, reference_id, notes)
        SELECT 
            oi.product_id,
            'adjustment',
            oi.quantity,
            NEW.id,
            'Order cancelled - stock restored'
        FROM order_items oi
        WHERE oi.order_id = NEW.id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `order_display_status`
--

CREATE TABLE `order_display_status` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `order_number` varchar(20) NOT NULL,
  `status` enum('waiting','preparing','ready','completed') DEFAULT 'waiting',
  `estimated_time` int(11) DEFAULT NULL,
  `display_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_display_status`
--

INSERT INTO `order_display_status` (`id`, `order_id`, `display_name`, `order_number`, `status`, `estimated_time`, `display_until`, `created_at`, `updated_at`) VALUES
(1, 1, 'Counter Order', 'WALK-IN-2601001', 'completed', 15, '2026-01-21 05:10:22', '2026-01-21 05:04:45', '2026-01-21 05:10:22'),
(2, 2, 'Louie', 'WALK-IN-2601002', 'completed', 15, '2026-01-21 06:13:18', '2026-01-21 05:12:52', '2026-01-21 06:13:18'),
(3, 3, 'Cali', 'ONLINE-2601001', 'completed', 15, '2026-01-21 06:20:42', '2026-01-21 06:14:23', '2026-01-21 06:20:42'),
(4, 4, 'Cali', 'ONLINE-2601002', 'completed', 15, '2026-01-21 06:39:05', '2026-01-21 06:32:08', '2026-01-21 06:39:05'),
(5, 5, 'test', 'ONLINE-2601003', 'completed', 15, '2026-01-21 08:10:58', '2026-01-21 06:41:35', '2026-01-21 08:10:58'),
(6, 10, 'Online Customer', 'ONLINE-260121-0001', 'completed', 15, '2026-01-21 08:08:46', '2026-01-21 08:01:28', '2026-01-21 08:08:46'),
(7, 11, 'Iwi', 'ONLINE-260121-0002', 'waiting', 15, NULL, '2026-01-21 13:15:11', '2026-01-21 13:15:11');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `special_request` text DEFAULT NULL,
  `item_notes` text DEFAULT NULL,
  `addons_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`addons_data`)),
  `status` enum('pending','preparing','ready','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `unit_price`, `total_price`, `special_request`, `item_notes`, `addons_data`, `status`, `created_at`) VALUES
(1, 1, 13, 1, 220.00, 232.00, 'with Cheese', NULL, NULL, 'ready', '2026-01-21 05:04:45'),
(2, 1, 14, 1, 90.00, 90.00, NULL, NULL, NULL, 'ready', '2026-01-21 05:04:45'),
(3, 2, 13, 1, 220.00, 220.00, 'No addons.', NULL, NULL, 'ready', '2026-01-21 05:12:52'),
(4, 3, 13, 1, 220.00, 232.00, 'With cheese and add hot sauce', NULL, NULL, 'ready', '2026-01-21 06:14:23'),
(5, 3, 14, 1, 90.00, 90.00, NULL, NULL, NULL, 'ready', '2026-01-21 06:14:23'),
(6, 4, 13, 1, 220.00, 232.00, 'wITH cHEESE and hOt saUce', NULL, NULL, 'ready', '2026-01-21 06:32:08'),
(7, 5, 13, 1, 220.00, 220.00, NULL, NULL, NULL, 'ready', '2026-01-21 06:41:35'),
(16, 10, 13, 1, 220.00, 220.00, 'Hot Sauce please.', NULL, NULL, 'ready', '2026-01-21 08:01:28'),
(17, 10, 14, 2, 90.00, 180.00, '', NULL, NULL, 'ready', '2026-01-21 08:01:28'),
(18, 11, 14, 1, 90.00, 90.00, '', NULL, NULL, 'pending', '2026-01-21 13:15:11');

-- --------------------------------------------------------

--
-- Table structure for table `order_item_addons`
--

CREATE TABLE `order_item_addons` (
  `id` int(11) NOT NULL,
  `order_item_id` int(11) NOT NULL,
  `addon_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `price_at_time` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_item_addons`
--

INSERT INTO `order_item_addons` (`id`, `order_item_id`, `addon_id`, `quantity`, `price_at_time`) VALUES
(1, 1, 1, 1, 12.00),
(2, 4, 1, 1, 12.00),
(3, 6, 1, 1, 12.00);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `payment_method` enum('cash','card','ewallet','online') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `payment_method`, `amount`, `reference_number`, `status`, `payment_date`, `processed_by`) VALUES
(1, 1, 'cash', 360.64, NULL, 'completed', '2026-01-21 05:04:45', NULL),
(2, 2, 'cash', 246.40, NULL, 'completed', '2026-01-21 05:12:52', NULL),
(3, 3, 'cash', 360.64, NULL, 'completed', '2026-01-21 06:14:23', NULL),
(4, 4, 'cash', 259.84, NULL, 'completed', '2026-01-21 06:32:08', NULL),
(5, 5, 'cash', 246.40, NULL, 'completed', '2026-01-21 06:41:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `min_stock` int(11) DEFAULT 5,
  `image_url` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `is_popular` tinyint(1) DEFAULT 0,
  `has_addons` tinyint(1) DEFAULT 0,
  `preparation_time` int(11) DEFAULT NULL,
  `calories` int(11) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `station_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `description`, `price`, `cost`, `stock`, `min_stock`, `image_url`, `is_available`, `is_popular`, `has_addons`, `preparation_time`, `calories`, `display_order`, `created_at`, `updated_at`, `station_id`) VALUES
(11, 1, NULL, 'Classic Burger', NULL, 180.00, NULL, 0, 5, NULL, 1, 0, 0, 10, NULL, 0, '2026-01-19 12:38:16', '2026-01-21 12:29:44', 1),
(12, 1, NULL, 'Cheeseburger', NULL, 200.00, NULL, 0, 5, NULL, 1, 0, 0, 10, NULL, 0, '2026-01-19 12:38:16', '2026-01-19 12:38:16', 1),
(13, 1, '', 'Bacon Burger', '', 220.00, 0.00, 0, 5, NULL, 1, 0, 1, 10, 0, 0, '2026-01-19 12:38:16', '2026-01-21 12:29:46', 1),
(14, 2, NULL, 'French Fries', NULL, 90.00, NULL, 10, 5, NULL, 1, 0, 0, 5, NULL, 0, '2026-01-19 12:38:16', '2026-01-21 08:08:46', 2),
(15, 2, NULL, 'Onion Rings', NULL, 110.00, NULL, 0, 5, NULL, 1, 0, 0, 5, NULL, 0, '2026-01-19 12:38:16', '2026-01-19 12:38:16', 2),
(16, 3, NULL, 'Soft Drink', NULL, 60.00, NULL, 0, 5, NULL, 1, 0, 0, 0, NULL, 0, '2026-01-19 12:38:16', '2026-01-21 12:29:49', 3),
(17, 3, NULL, 'Iced Tea', NULL, 70.00, NULL, 0, 5, NULL, 1, 0, 0, 0, NULL, 0, '2026-01-19 12:38:16', '2026-01-19 12:38:16', 3),
(18, 4, NULL, 'Pepperoni Pizza', NULL, 220.00, NULL, 0, 5, NULL, 1, 0, 0, 15, NULL, 0, '2026-01-19 12:38:16', '2026-01-19 12:38:16', 4),
(19, 5, NULL, 'Chocolate Cake', NULL, 120.00, NULL, 0, 5, NULL, 1, 0, 0, 10, NULL, 0, '2026-01-19 12:38:16', '2026-01-19 12:38:16', 5);

-- --------------------------------------------------------

--
-- Table structure for table `product_addons`
--

CREATE TABLE `product_addons` (
  `product_id` int(11) NOT NULL,
  `addon_id` int(11) NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_addons`
--

INSERT INTO `product_addons` (`product_id`, `addon_id`, `is_default`, `display_order`) VALUES
(13, 2, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `promos`
--

CREATE TABLE `promos` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_type` enum('percentage','fixed','bogo') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `valid_from` date NOT NULL,
  `valid_until` date NOT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `public_display_settings`
--

CREATE TABLE `public_display_settings` (
  `id` int(11) NOT NULL,
  `display_type` enum('kitchen','customer','counter') NOT NULL,
  `refresh_interval` int(11) DEFAULT 30,
  `max_display_items` int(11) DEFAULT 10,
  `show_completed_for` int(11) DEFAULT 5,
  `auto_remove_after` int(11) DEFAULT 120,
  `theme` varchar(50) DEFAULT 'default',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `setting_key` varchar(50) DEFAULT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `public_display_settings`
--

INSERT INTO `public_display_settings` (`id`, `display_type`, `refresh_interval`, `max_display_items`, `show_completed_for`, `auto_remove_after`, `theme`, `is_active`, `created_at`, `setting_key`, `setting_value`, `setting_type`, `description`, `updated_at`) VALUES
(1, 'kitchen', 15, 20, 5, 120, 'default', 1, '2026-01-19 09:45:40', NULL, NULL, 'string', NULL, '2026-01-19 10:05:17'),
(2, 'customer', 15, 15, 5, 120, 'default', 1, '2026-01-19 09:45:40', NULL, NULL, 'string', NULL, '2026-01-21 06:28:19'),
(3, 'counter', 15, 10, 5, 120, 'default', 1, '2026-01-19 09:45:40', NULL, NULL, 'string', NULL, '2026-01-21 06:28:21');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
  `category` varchar(50) DEFAULT 'general',
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `category`, `description`, `updated_at`) VALUES
(1, 'tax_rate', '0.12', 'number', 'business', NULL, '2026-01-19 09:41:59'),
(2, 'company_name', 'My Restaurant', 'string', 'business', NULL, '2026-01-19 09:41:59'),
(3, 'currency', 'PHP', 'string', 'business', NULL, '2026-01-19 09:41:59'),
(4, 'enable_online_orders', 'true', 'boolean', 'features', NULL, '2026-01-19 09:41:59'),
(5, 'low_stock_threshold', '10', 'number', 'inventory', NULL, '2026-01-19 09:41:59'),
(6, 'pos_theme', 'light', 'string', 'pos', 'POS interface theme', '2026-01-19 14:40:07'),
(7, 'pos_default_order_type', 'walkin', 'string', 'pos', 'Default order type', '2026-01-19 14:40:07'),
(8, 'pos_auto_print', 'false', 'boolean', 'pos', 'Auto print receipts', '2026-01-19 14:40:07'),
(9, 'pos_show_stock', 'true', 'boolean', 'pos', 'Show stock levels', '2026-01-19 14:40:07'),
(10, 'pos_tax_inclusive', 'false', 'boolean', 'pos', 'Prices include tax', '2026-01-19 14:40:07'),
(11, 'pos_rounding', 'true', 'boolean', 'pos', 'Round total amounts', '2026-01-19 14:40:07'),
(12, 'pos_order_prefix', 'POS-', 'string', 'pos', 'Order number prefix', '2026-01-19 14:40:07'),
(13, 'pos_order_counter', '100', 'number', 'pos', 'Next order number', '2026-01-19 14:40:07'),
(14, 'pos_kitchen_print', 'true', 'boolean', 'pos', 'Print kitchen tickets', '2026-01-19 14:40:07'),
(15, 'pos_customer_display', 'true', 'boolean', 'pos', 'Show customer display', '2026-01-19 14:40:07');

-- --------------------------------------------------------

--
-- Table structure for table `status_change_logs`
--

CREATE TABLE `status_change_logs` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `old_status` varchar(50) NOT NULL,
  `new_status` varchar(50) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','cashier','kitchen') DEFAULT 'cashier',
  `full_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`, `full_name`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@restaurant.com', '$2y$10$d3ZS1d6Xom3x9EGgPme3mu5fphfNiFkmAjw004VzyUBbeOmpbaFL.a', 'admin', 'Administrator', 1, '2026-01-21 01:43:43', '2026-01-19 14:40:07', '2026-01-21 05:26:58'),
(2, 'cashier1', 'cashier1@restaurant.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cashier', 'Cashier One', 1, NULL, '2026-01-19 14:40:07', '2026-01-19 14:40:07'),
(3, 'kitchen', 'kitchen@restaurant.com', '$2y$10$d3ZS1d6Xom3x9EGgPme3mu5fphfNiFkmAjw004VzyUBbeOmpbaFL.a', 'kitchen', 'Kitchen One', 1, NULL, '2026-01-21 05:25:57', '2026-01-21 05:27:00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addons`
--
ALTER TABLE `addons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_product_stock` (`product_id`,`created_at`);

--
-- Indexes for table `kitchen_stations`
--
ALTER TABLE `kitchen_stations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `online_orders`
--
ALTER TABLE `online_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_tracking` (`order_number`,`tracking_pin`),
  ADD KEY `idx_session` (`session_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `confirmed_by` (`confirmed_by`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_status` (`status`,`order_type`),
  ADD KEY `idx_date` (`order_date`,`created_at`);

--
-- Indexes for table `order_display_status`
--
ALTER TABLE `order_display_status`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `idx_display` (`status`,`display_until`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_item_status` (`status`);

--
-- Indexes for table `order_item_addons`
--
ALTER TABLE `order_item_addons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_item_id` (`order_item_id`),
  ADD KEY `addon_id` (`addon_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_availability` (`is_available`,`stock`),
  ADD KEY `station_id` (`station_id`);

--
-- Indexes for table `product_addons`
--
ALTER TABLE `product_addons`
  ADD PRIMARY KEY (`product_id`,`addon_id`),
  ADD KEY `addon_id` (`addon_id`);

--
-- Indexes for table `promos`
--
ALTER TABLE `promos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `public_display_settings`
--
ALTER TABLE `public_display_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `status_change_logs`
--
ALTER TABLE `status_change_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_order_status` (`order_id`,`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addons`
--
ALTER TABLE `addons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `kitchen_stations`
--
ALTER TABLE `kitchen_stations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `online_orders`
--
ALTER TABLE `online_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `order_display_status`
--
ALTER TABLE `order_display_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `order_item_addons`
--
ALTER TABLE `order_item_addons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `promos`
--
ALTER TABLE `promos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `public_display_settings`
--
ALTER TABLE `public_display_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `status_change_logs`
--
ALTER TABLE `status_change_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addons`
--
ALTER TABLE `addons`
  ADD CONSTRAINT `addons_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_display_status`
--
ALTER TABLE `order_display_status`
  ADD CONSTRAINT `order_display_status_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `order_item_addons`
--
ALTER TABLE `order_item_addons`
  ADD CONSTRAINT `order_item_addons_ibfk_1` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_item_addons_ibfk_2` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`station_id`) REFERENCES `kitchen_stations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_3` FOREIGN KEY (`station_id`) REFERENCES `kitchen_stations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_addons`
--
ALTER TABLE `product_addons`
  ADD CONSTRAINT `product_addons_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_addons_ibfk_2` FOREIGN KEY (`addon_id`) REFERENCES `addons` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `promos`
--
ALTER TABLE `promos`
  ADD CONSTRAINT `promos_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `status_change_logs`
--
ALTER TABLE `status_change_logs`
  ADD CONSTRAINT `status_change_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `status_change_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
