-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 06, 2025 at 02:58 PM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nutrition.lk`
--

-- --------------------------------------------------------

--
-- Table structure for table `businessregistration`
--

DROP TABLE IF EXISTS `businessregistration`;
CREATE TABLE IF NOT EXISTS `businessregistration` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `bname` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `date` date NOT NULL,
  `bnumber` int NOT NULL,
  `bregid` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `btype` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `bcertificate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `blogo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `approve` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fk_business_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `businessregistration`
--

INSERT INTO `businessregistration` (`id`, `user_id`, `bname`, `date`, `bnumber`, `bregid`, `btype`, `bcertificate`, `blogo`, `approve`) VALUES
(13, 'U0005', 'RC Nutrition', '2024-01-01', 234876, 'SP-VD-6555', 'Partnership', 'cert_U0005_1758648358.pdf', 'logo_U0005_1758648358.png', 1);

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

DROP TABLE IF EXISTS `cart`;
CREATE TABLE IF NOT EXISTS `cart` (
  `Id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `Userid` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `PID` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `Size` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Standard',
  `Qty` int NOT NULL DEFAULT '1',
  `AddedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`Id`),
  KEY `idx_cart_user` (`Userid`),
  KEY `idx_cart_product` (`PID`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`Id`, `Userid`, `PID`, `Size`, `Qty`, `AddedAt`) VALUES
(11, 'U0005', 'P1758704122917', 'Standard', 1, '2025-10-06 14:55:44');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
CREATE TABLE IF NOT EXISTS `coupons` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `discount_type` enum('percentage','fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `min_order_amount` decimal(10,2) DEFAULT NULL,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int DEFAULT NULL,
  `used_count` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `expiry_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `discount_type`, `discount_value`, `min_order_amount`, `max_discount`, `usage_limit`, `used_count`, `active`, `expiry_date`, `created_at`) VALUES
(1, 'WELCOME10', 'percentage', 1000.00, 15000.00, NULL, NULL, 0, 1, NULL, '2025-09-19 03:12:55'),
(2, 'SAVE5000', 'fixed', 5000.00, 20000.00, NULL, NULL, 0, 1, NULL, '2025-09-19 03:12:55');

-- --------------------------------------------------------

--
-- Table structure for table `deliverydetails`
--

DROP TABLE IF EXISTS `deliverydetails`;
CREATE TABLE IF NOT EXISTS `deliverydetails` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `userid` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `address` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `postal_code` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `province` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `contact1` varchar(15) COLLATE utf8mb4_general_ci NOT NULL,
  `contact2` varchar(15) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `delivery_type` enum('Speed Post','Courier') COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `type` enum('info','success','warning','error','order') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notifications_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 'U0001', 'New business registration from Ravindu Chandeepa (ID: U0002)', 'info', 0, '2025-09-20 01:35:44'),
(3, 'U0001', 'New business registration from Nadeesh Nuwanthya (ID: U0005)', 'info', 0, '2025-09-23 17:25:58'),
(4, 'U0005', 'New order #ORD202510068358 received for MuscleTech Vapor X5 30 Servings (Qty: 1)', 'order', 1, '2025-10-06 13:24:57'),
(5, 'U0005', 'Your order #ORD202510068358 has been placed successfully. Total: Rs. 9,950.00', 'success', 1, '2025-10-06 13:24:57'),
(6, 'U0005', 'Your order #ORD202510068358 for MuscleTech Vapor X5 30 Servings has been confirmed by the seller.', 'order', 1, '2025-10-06 13:29:23'),
(7, 'U0005', 'New order #ORD202510061871 received for ANIMAL PAK (Qty: 1)', 'order', 1, '2025-10-06 13:53:05'),
(8, 'U0005', 'Your order #ORD202510061871 has been placed successfully. Total: Rs. 18,950.00', 'success', 1, '2025-10-06 13:53:05'),
(9, 'U0005', 'Your order #ORD202510061871 for ANIMAL PAK has been confirmed by the seller.', 'order', 1, '2025-10-06 13:53:15');

-- --------------------------------------------------------

--
-- Table structure for table `orderhistory`
--

DROP TABLE IF EXISTS `orderhistory`;
CREATE TABLE IF NOT EXISTS `orderhistory` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pid` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `totalprice` decimal(20,2) NOT NULL,
  `date` datetime NOT NULL,
  `orderid` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pnames` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_oh_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orderhistory`
--

INSERT INTO `orderhistory` (`id`, `user_id`, `pid`, `totalprice`, `date`, `orderid`, `pnames`, `qty`) VALUES
(24, 'U0005', 'P1758703633893,P1758704122917', 29950.00, '2025-09-28 22:13:23', 'ORD202509286072', 'Animal Stak, Carnivor Protein', '2'),
(25, 'U0005', 'P1759425142625', 47950.00, '2025-10-06 13:11:45', 'ORD202510061461', 'MuscleTech Vapor X5 30 Servings', '5'),
(26, 'U0005', 'P1759425142625', 47950.00, '2025-10-06 13:12:31', 'ORD202510064715', 'MuscleTech Vapor X5 30 Servings', '5');

-- --------------------------------------------------------

--
-- Table structure for table `ordertable`
--

DROP TABLE IF EXISTS `ordertable`;
CREATE TABLE IF NOT EXISTS `ordertable` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `orderid` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pid` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `orderdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `pname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `categories` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `discription` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `price` double NOT NULL,
  `size` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `seller_id` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `buyer_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `buyer_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `buyer_postal_code` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `buyer_province` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `buyer_contact1` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `buyer_contact2` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `delivery_type` enum('Speed Post','Courier') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'Speed Post',
  `payment_method` enum('COD','PayHere') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'COD',
  `delivery_fee` decimal(10,2) NOT NULL DEFAULT '450.00',
  `coupon_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `coupon_discount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','confirmed','shipped','delivered','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'pending',
  `confirmed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ot_user` (`user_id`),
  KEY `idx_ot_pid` (`pid`),
  KEY `idx_ot_orderid` (`orderid`)
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ordertable`
--

INSERT INTO `ordertable` (`id`, `orderid`, `user_id`, `pid`, `qty`, `orderdate`, `pname`, `categories`, `discription`, `price`, `size`, `seller_id`, `buyer_name`, `buyer_address`, `buyer_postal_code`, `buyer_province`, `buyer_contact1`, `buyer_contact2`, `delivery_type`, `payment_method`, `delivery_fee`, `coupon_code`, `coupon_discount`, `total_amount`, `status`, `confirmed_at`) VALUES
(47, 'ORD202509281265', 'U0005', 'P1758704122917', 2, '2025-09-28 11:57:29', 'Carnivor Protein', 'Protein Powder', '0', 16500, 'Standard', 'U0005', NULL, NULL, NULL, NULL, NULL, NULL, 'Courier', 'PayHere', 450.00, NULL, 0.00, 33450.00, 'confirmed', '2025-09-28 11:57:29'),
(48, 'ORD202509286072', 'U0005', 'P1758704122917', 1, '2025-09-28 16:43:23', 'Carnivor Protein', 'Protein Powder', '0', 16500, 'Standard', 'U0005', 'Nadeesh Nuwantha', 'no.615,yaya 01, wewa pahala, Sooriyawewa.', '82010', 'Southern Province', '0712345678', '', 'Courier', 'COD', 450.00, '', 0.00, 16950.00, 'confirmed', '2025-09-28 16:43:23'),
(49, 'ORD202509286072', 'U0005', 'P1758703633893', 1, '2025-09-28 16:43:23', 'Animal Stak', 'Vitamins', '0', 13000, 'Standard', 'U0005', 'Nadeesh Nuwantha', 'no.615,yaya 01, wewa pahala, Sooriyawewa.', '82010', 'Southern Province', '0712345678', '', 'Courier', 'COD', 450.00, '', 0.00, 13450.00, 'confirmed', '2025-09-28 16:43:23'),
(50, 'ORD202510068358', 'U0005', 'P1759425142625', 1, '2025-10-06 13:29:23', '', '', '', 9500, 'Standard', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Speed Post', 'COD', 450.00, NULL, 0.00, 0.00, 'confirmed', NULL),
(51, 'ORD202510061871', 'U0005', 'P1758654858558', 1, '2025-10-06 13:53:15', '', '', '', 18500, 'Standard', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Speed Post', 'COD', 450.00, NULL, 0.00, 0.00, 'confirmed', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `production`
--

DROP TABLE IF EXISTS `production`;
CREATE TABLE IF NOT EXISTS `production` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `user_id` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `brand` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '',
  `categories` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `discription` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `price` double NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `qty` int NOT NULL,
  `Add_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approve` tinyint(1) NOT NULL DEFAULT '1',
  `status` enum('active','suspended') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_production_pid` (`pid`),
  KEY `idx_production_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production`
--

INSERT INTO `production` (`id`, `pid`, `user_id`, `pname`, `brand`, `categories`, `discription`, `price`, `image`, `qty`, `Add_date`, `approve`, `status`) VALUES
(43, 'P1758654858558', 'U0005', 'ANIMAL PAK', 'ANIMAL', 'Vitamins', 'Animal Pak is a complete training multivitamin designed for serious athletes. Packed with essential vitamins, minerals, amino acids, antioxidants, and performance blends, it supports muscle growth, recovery, and overall health. Trusted by bodybuilders, it', 18500, '1758654858_Pak44_2000x2000_2a48d156-827e-41ec-a826-af649f7881f3.jpg', 9, '2025-10-06 13:53:05', 1, 'active'),
(44, 'P1758703633893', 'U0005', 'Animal Stak', 'Animal', 'Vitamins', 'Animal Stak is a natural anabolic supplement designed for athletes and bodybuilders. It boosts testosterone, supports growth hormone, and helps build lean muscle, strength, and energy through five unique complexes featuring herbal extracts, vitamins, mine', 13000, '1758703633_Stak_2000x2000_4f98250e-e9d7-4618-ae8f-c33683645989.jpg', 8, '2025-09-28 16:43:23', 1, 'active'),
(45, 'P1758704122917', 'U0005', 'Carnivor Protein', 'MuscleMeds', 'Protein Powder', 'Carnivor Protein is a beef protein isolate supplement known for being lactose-free, fat-free, and cholesterol-free, with each serving providing about 23g of pure beef protein. Clinically proven to support muscle building, it contains higher amino acid con', 16500, '1758704122_CARNIVOR-SHRED-CHOCOLATE-4lb.jpg', 11, '2025-09-29 02:38:20', 1, 'active'),
(46, 'P1759425142625', 'U0005', 'MuscleTech Vapor X5 30 Servings', 'MuscleTech', 'Pre-Workout', 'A Complete Pre-Workout: MuscleTech VaporX5 pre-workout is your all-in-one formula for more lean muscle, strength, energy, focus and insane muscle pumps. We hand-picked ingredients to help you crush your goals. Blue Razz Freeze flavor. 30 servings.', 9500, '1759425142_Muscletech-Vapor-X5-2.jpg', 3, '2025-10-06 13:24:57', 1, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `productsize`
--

DROP TABLE IF EXISTS `productsize`;
CREATE TABLE IF NOT EXISTS `productsize` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `pid` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `size` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `qty` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fk_productsize_product` (`pid`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `productsize`
--

INSERT INTO `productsize` (`id`, `pid`, `size`, `price`, `qty`) VALUES
(1, 'P1758704122917', '2LBS', 16500.00, 5),
(2, 'P1758704122917', '4LBS', 26500.00, 5),
(3, 'P1758704122917', '8LBS', 45000.00, 5);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `firstname` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `lastname` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `approve` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_user_id` (`user_id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `user_id`, `firstname`, `lastname`, `username`, `email`, `password`, `image`, `type`, `approve`) VALUES
(25, 'U0001', 'Ravindu', 'Chandeepa', 'admin', 'infor.abeysekara@gmail.com', '@Ra200400912445', 'Array', 'admin', 1),
(27, 'U0003', 'Ravindu', 'Abeysekara', 'abey123', 'infor.abeyzsekara@gmail.com', 'a3f59181769c08664215713f48babe48', '', 'admin', 1),
(28, 'U0004', 'Janith', 'Damsara', 'janith123', 'janith123@gmail.com', '2c553de2ba4cdb62051fa8ad2cd3fdea', 'user_68d2a5a077d07.png', 'buyer', 1),
(30, 'U0005', 'Nadeesh', 'Nuwantha', 'nadeesh123', 'nadeeshnuwanth@gmail.com', 'b8b40457ee743062c9181959a904776e', 'user_68d36965733bd.jpeg', 'seller', 1),
(31, 'U0006', 'Ravindu', 'Chandeepa', 'ravindu09', 'infor.ravinduchandeepa2004@gmail.com', 'a3f59181769c08664215713f48babe48', 'user_68d2de338effe.jpeg', 'admin', 1),
(32, 'U0007', 'Chamika', 'Sandeepa', 'chami01', 'chamikasandeepa40@gmail.com', 'f1db76e90a53d79f5404da73137e93c3', 'user_68d2dfe37e24f.png', 'seller', 0),
(33, 'U0008', 'Chamika', 'Sandeepa', 'chami0123', 'chamikasandeepa4033@gmail.com', '7159f3da9d3f45164ff0e8d815444778', '', 'seller', 0);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `businessregistration`
--
ALTER TABLE `businessregistration`
  ADD CONSTRAINT `fk_business_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `fk_cart_product` FOREIGN KEY (`PID`) REFERENCES `production` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cart_user` FOREIGN KEY (`Userid`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orderhistory`
--
ALTER TABLE `orderhistory`
  ADD CONSTRAINT `fk_oh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `ordertable`
--
ALTER TABLE `ordertable`
  ADD CONSTRAINT `fk_ot_product` FOREIGN KEY (`pid`) REFERENCES `production` (`pid`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ot_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `production`
--
ALTER TABLE `production`
  ADD CONSTRAINT `fk_production_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `productsize`
--
ALTER TABLE `productsize`
  ADD CONSTRAINT `fk_productsize_product` FOREIGN KEY (`pid`) REFERENCES `production` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
