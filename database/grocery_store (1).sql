-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 05, 2026 at 03:51 PM
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
-- Database: `grocery_store`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `label` varchar(50) DEFAULT 'Home',
  `recipient_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Bangladesh',
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addresses`
--

INSERT INTO `addresses` (`id`, `user_id`, `label`, `recipient_name`, `phone`, `address_line1`, `address_line2`, `city`, `state`, `postal_code`, `country`, `is_default`, `created_at`, `updated_at`) VALUES
(8, 5, 'Office', 'sourav', '01782427035', 'barishal sadar', NULL, 'Bhola', 'Barishal', '8200', 'Bangladesh', 0, '2026-07-05 05:37:45', '2026-07-05 05:38:11'),
(9, 5, 'Home', 'sourav', '01782427035', 'barishal sadar', NULL, 'Barishal', 'Barishal', '8200', 'Bangladesh', 1, '2026-07-05 05:38:11', '2026-07-05 05:38:11');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `role_id`, `username`, `email`, `password`, `full_name`, `phone`, `avatar`, `remember_token`, `password_reset_token`, `reset_token_expires_at`, `last_login_at`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'superadmin', 'sourav.cse9.bu@gmail.com', '$2y$10$yPnpxeC/oQ.wEEeYyAHlyeRt/zyqg0v5Mgl3zWcA4FTGCbnQMc8Om', 'Super Administrator', NULL, NULL, NULL, '1521c3411f2e373b29d472524692872084718b439c5710da4647012458f37d82', '2026-07-05 14:48:48', NULL, 1, '2026-07-05 06:53:07', '2026-07-05 13:48:48');

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_logs`
--

CREATE TABLE `admin_activity_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_login_logs`
--

CREATE TABLE `admin_login_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `login_identity` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `browser` varchar(50) DEFAULT NULL,
  `os` varchar(50) DEFAULT NULL,
  `device` varchar(50) DEFAULT NULL,
  `success` tinyint(1) NOT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `logout_time` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_permissions`
--

CREATE TABLE `admin_permissions` (
  `id` int(11) NOT NULL,
  `permission_key` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_permissions`
--

INSERT INTO `admin_permissions` (`id`, `permission_key`, `description`, `created_at`) VALUES
(1, 'dashboard.view', 'View Admin Dashboard', '2026-07-05 06:53:07'),
(2, 'products.view', 'View Products', '2026-07-05 06:53:07'),
(3, 'products.create', 'Create New Products', '2026-07-05 06:53:07'),
(4, 'products.edit', 'Edit Existing Products', '2026-07-05 06:53:07'),
(5, 'products.delete', 'Delete Products', '2026-07-05 06:53:07'),
(6, 'orders.view', 'View Customer Orders', '2026-07-05 06:53:07'),
(7, 'orders.edit', 'Manage & Update Orders', '2026-07-05 06:53:07'),
(8, 'customers.view', 'View Customer Profiles', '2026-07-05 06:53:07'),
(9, 'customers.edit', 'Manage Customer Accounts', '2026-07-05 06:53:07'),
(10, 'reviews.manage', 'Moderate Product Reviews', '2026-07-05 06:53:07'),
(11, 'reports.view', 'Allows viewing administrative sales, inventory, and activity reports.', '2026-07-05 06:53:07'),
(12, 'settings.manage', 'Allows managing system settings, social links, and SMTP configurations.', '2026-07-05 06:53:07'),
(13, 'admins.manage', 'Allows managing administrative accounts (CRUD).', '2026-07-05 06:53:07'),
(14, 'coupons.manage', 'Allows creating, editing, and deleting discount coupons.', '2026-07-05 12:24:25'),
(15, 'flashsales.manage', 'Allows configuring flash sale countdown campaigns.', '2026-07-05 12:24:25'),
(16, 'cms.manage', 'Allows managing static CMS pages (About, Terms, etc.).', '2026-07-05 12:24:25'),
(17, 'newsletter.manage', 'Allows viewing and exporting newsletter subscribers.', '2026-07-05 12:24:25'),
(18, 'contacts.manage', 'Allows viewing and responding to support contacts messages.', '2026-07-05 12:24:25'),
(19, 'banners.manage', 'Allows scheduling and uploading promotional sliders and banners.', '2026-07-05 12:24:25'),
(20, 'faq.manage', 'Allows creating and ordering FAQ questions.', '2026-07-05 12:24:25'),
(21, 'testimonials.manage', 'Allows moderating client testimonials feedback.', '2026-07-05 12:24:25'),
(25, 'roles.manage', 'Allows editing roles and dynamic permissions matrix.', '2026-07-05 12:42:57'),
(26, 'activity.view', 'Allows inspecting activity logs and security audit logs.', '2026-07-05 12:42:57'),
(27, 'backup.manage', 'Allows creating, downloading, and restoring database backups.', '2026-07-05 12:42:57'),
(28, 'security.manage', 'Allows blocking IPs, inspecting active sessions, and setting security rules.', '2026-07-05 12:42:57'),
(29, 'cache.manage', 'Allows clearing image, template, and database queries cache.', '2026-07-05 12:42:57'),
(30, 'notifications.manage', 'Allows broadcasting notifications to admins and customers.', '2026-07-05 12:42:57'),
(31, 'inventory.manage', 'Allows managing stock in, stock out, transfers, adjustments, and damaged items.', '2026-07-05 12:50:47'),
(32, 'purchases.manage', 'Allows creating purchase orders and receiving supplier shipments.', '2026-07-05 12:50:48'),
(33, 'finance.manage', 'Allows managing ledger, expenses, profits, cashflows, and daily closings.', '2026-07-05 12:53:56'),
(34, 'delivery.manage', 'Allows managing delivery boys, assignment of orders, and routes.', '2026-07-05 12:56:22'),
(35, 'pos.manage', 'Allows accessing the Point of Sale terminal and shifting registers.', '2026-07-05 12:58:51'),
(36, 'pos.access', 'Allows accessing the POS terminal interface dashboard.', '2026-07-05 13:02:33'),
(37, 'pos.sale', 'Allows finalizing retail checkouts and printing invoices.', '2026-07-05 13:02:33'),
(38, 'pos.return', 'Allows processing customer merchandise exchanges and returns.', '2026-07-05 13:02:33'),
(39, 'pos.discount', 'Allows applying cashier override discounts on products and carts.', '2026-07-05 13:02:33'),
(40, 'pos.refund', 'Allows processing client cash and credit card refunds.', '2026-07-05 13:02:33'),
(41, 'pos.cash', 'Allows monitoring shifts register opening and closing cash counts.', '2026-07-05 13:02:33'),
(42, 'pos.report', 'Allows inspecting point of sale YTD performance reports.', '2026-07-05 13:02:33'),
(43, 'pos.void', 'Allows voiding active counter sales invoices.', '2026-07-05 13:14:22'),
(44, 'pos.override', 'Allows manual unit price overrides in checkout carts.', '2026-07-05 13:14:22'),
(45, 'pos.reports_xz', 'Allows exporting register X-reading and Z-closing drawer audits.', '2026-07-05 13:14:22');

-- --------------------------------------------------------

--
-- Table structure for table `admin_roles`
--

CREATE TABLE `admin_roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_roles`
--

INSERT INTO `admin_roles` (`id`, `name`, `created_at`) VALUES
(1, 'Super Admin', '2026-07-05 06:53:07'),
(2, 'Admin', '2026-07-05 06:53:07'),
(3, 'Manager', '2026-07-05 06:53:07'),
(4, 'Staff', '2026-07-05 06:53:07');

-- --------------------------------------------------------

--
-- Table structure for table `admin_role_permissions`
--

CREATE TABLE `admin_role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_role_permissions`
--

INSERT INTO `admin_role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(1, 10),
(1, 11),
(1, 12),
(1, 13),
(1, 14),
(1, 15),
(1, 16),
(1, 17),
(1, 18),
(1, 19),
(1, 20),
(1, 21),
(1, 25),
(1, 26),
(1, 27),
(1, 28),
(1, 29),
(1, 30),
(1, 31),
(1, 32),
(1, 33),
(1, 34),
(1, 35),
(1, 36),
(1, 37),
(1, 38),
(1, 39),
(1, 40),
(1, 41),
(1, 42),
(1, 43),
(1, 44),
(1, 45),
(2, 1),
(2, 2),
(2, 3),
(2, 4),
(2, 5),
(2, 6),
(2, 7),
(2, 8),
(2, 9),
(2, 10),
(2, 11),
(2, 12),
(3, 1),
(3, 2),
(3, 3),
(3, 4),
(3, 6),
(3, 7),
(3, 8),
(3, 10),
(4, 1),
(4, 2),
(4, 6);

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `priority` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `starts_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocked_ips`
--

CREATE TABLE `blocked_ips` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `blocked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `logo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brands`
--

INSERT INTO `brands` (`id`, `name`, `slug`, `is_active`, `logo`, `created_at`, `updated_at`) VALUES
(1, 'Nestle', 'nestle', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(2, 'Coca-Cola', 'coca-cola', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(3, 'Unilever', 'unilever', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(4, 'Danone', 'danone', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(5, 'PepsiCo', 'pepsico', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(6, 'Kraft', 'kraft', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(7, 'Kellogg', 'kellogg', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(8, 'Mars', 'mars', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(9, 'General Mills', 'general-mills', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(10, 'Mondelez', 'mondelez', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(11, 'Colgate', 'colgate', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(12, 'Procter & Gamble', 'pg', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(13, 'Johnson & Johnson', 'jnj', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(14, 'Reckitt', 'reckitt', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(15, 'Ferrero', 'ferrero', 1, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(191) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `user_id`, `session_id`, `created_at`, `updated_at`) VALUES
(1, NULL, '402354ca7773af82111e89beb10423e5f055ee60', '2026-07-04 16:00:29', '2026-07-04 16:00:29'),
(2, NULL, 'ad7af32691684d991dbab7d0f301877807739196', '2026-07-04 18:29:02', '2026-07-04 18:29:02'),
(3, NULL, 'cc7504ed26e14e7c98825fe138605b77541bd7a0', '2026-07-04 18:29:02', '2026-07-04 18:29:02'),
(4, NULL, '1c887373f3939c251d264a0503134442c8147cfd', '2026-07-04 18:29:02', '2026-07-04 18:29:02'),
(5, NULL, '72b9e1aeea2dce91b6da6eca1cda4a1f2a6f1bb9', '2026-07-04 18:29:02', '2026-07-04 18:29:02'),
(6, NULL, 'f36582ff626b7ab563ca4b5616517e9623a393f7', '2026-07-04 18:29:03', '2026-07-04 18:29:03'),
(7, NULL, 'ff8182c4faec0d967b193d534efbcbc3e2a38b30', '2026-07-04 18:29:03', '2026-07-04 18:29:03'),
(8, NULL, '5327a8640e394065c0f8bf6bceebd68b32cdb781', '2026-07-04 18:29:37', '2026-07-04 18:29:37'),
(9, NULL, 'e5c2cc997ef5f2f0ae7c25954ee68f1cd9fc140e', '2026-07-04 18:29:37', '2026-07-04 18:29:37'),
(10, NULL, 'b8e73d803388b0615e46d228e5c5875f38220331', '2026-07-04 18:29:37', '2026-07-04 18:29:37'),
(11, NULL, '66f86ff9de169a063bde8f11f50b752865954a2d', '2026-07-04 18:29:37', '2026-07-04 18:29:37'),
(12, NULL, 'c9d3bc1b716bf3bf93b1e42a4d734846f48b5bb4', '2026-07-04 18:29:37', '2026-07-04 18:29:37'),
(13, NULL, '6f4a21019ebdd8ef321b024b3adb5753d3008362', '2026-07-04 18:29:37', '2026-07-04 18:29:37'),
(14, NULL, 'ab62d11ecddc9069b1e4a7e453eee9bb487a22e9', '2026-07-04 18:30:55', '2026-07-04 18:30:55'),
(15, NULL, 'b6f654196e4dc31d34aa6ce3af2928b409bee5df', '2026-07-04 18:30:56', '2026-07-04 18:30:56'),
(16, NULL, '256a5e01eec8600573d7b34818c6e13588add326', '2026-07-04 18:30:56', '2026-07-04 18:30:56'),
(17, NULL, '2079ed860d74c71206e685e7a2ab634ed5a3399e', '2026-07-04 18:30:56', '2026-07-04 18:30:56'),
(18, NULL, 'f9b4234277c369a2f8f6604a9671cf262c9b4d7a', '2026-07-04 18:30:56', '2026-07-04 18:30:56'),
(19, NULL, '88dd9a755f53f0d62ab2de04541930a234d30656', '2026-07-04 18:30:56', '2026-07-04 18:30:56'),
(23, NULL, '2993e709dc7cab436d94224a432fb0c353e7b301', '2026-07-04 19:11:52', '2026-07-04 19:11:52'),
(24, NULL, '9466378b546e703feda74b106a2e25617fc04f27', '2026-07-04 19:29:23', '2026-07-04 19:29:23'),
(25, 5, NULL, '2026-07-04 19:30:44', '2026-07-05 05:10:20'),
(26, NULL, '00bffa913e39d943774ccf7105ca8eec08d64011', '2026-07-05 05:05:33', '2026-07-05 05:05:33'),
(27, NULL, 'e5200779f43599796e3351bc3d96b022720d10a6', '2026-07-05 05:06:11', '2026-07-05 05:06:11'),
(28, NULL, '74c16d62b8f3f649d8874d460130ff6a6e03534f', '2026-07-05 05:06:22', '2026-07-05 05:06:22'),
(29, NULL, '95548cfebce0089e3f7f9cce874f989554f69a65', '2026-07-05 05:06:22', '2026-07-05 05:06:22'),
(30, NULL, '1e88b339575733c558d791933a098ea29f048ad2', '2026-07-05 05:06:22', '2026-07-05 05:06:22'),
(31, NULL, '236fe21f0c0894cae02beb67723726c2a41686e4', '2026-07-05 05:06:23', '2026-07-05 05:06:23'),
(32, NULL, '9703273d5d0894abe4e643f562f8b1f62d3581cc', '2026-07-05 05:06:23', '2026-07-05 05:06:23'),
(33, NULL, '0d852407cd9a043ae66c0fb41fc65a54540e75f6', '2026-07-05 05:06:23', '2026-07-05 05:06:23'),
(34, NULL, 'b08f82bcf550200e6ab189ade932298a4f3b4e54', '2026-07-05 05:06:23', '2026-07-05 05:06:23'),
(35, NULL, 'f47f0868c2d354901d78617470e239f4865f7dbc', '2026-07-05 05:06:23', '2026-07-05 05:06:23'),
(36, 1, NULL, '2026-07-05 05:36:42', '2026-07-05 05:36:42'),
(38, NULL, '90d8c91902807fc236314dbc4509d19a657a4ede', '2026-07-05 06:10:00', '2026-07-05 06:10:00'),
(39, NULL, '09b1ae6510886eefc00ec00071ad8a5c8453f3dc', '2026-07-05 06:10:00', '2026-07-05 06:10:00'),
(40, NULL, '57861c83328049f53b4c61688d0b4292ab188952', '2026-07-05 06:10:25', '2026-07-05 06:10:25'),
(41, NULL, '77a32ed04f793af345896de6e2302a57ebbd5178', '2026-07-05 06:19:10', '2026-07-05 06:19:10'),
(42, NULL, '1f1a1eb36edf2f458bd94327e12c117ab696bb4c', '2026-07-05 06:25:50', '2026-07-05 06:25:50'),
(43, NULL, '2b44714ec1985705d7a3186646a9d713ce54c71b', '2026-07-05 06:25:50', '2026-07-05 06:25:50'),
(44, NULL, 'c65a24c20d53ae47cfc1767242c599dfcda72741', '2026-07-05 06:25:51', '2026-07-05 06:25:51'),
(45, NULL, '97640207309aeaac0d3acd944fef83db66f0dbff', '2026-07-05 06:28:06', '2026-07-05 06:28:06'),
(46, NULL, 'c9ea50b13c7f0afa694ded921660c04752be8dba', '2026-07-05 07:00:40', '2026-07-05 07:00:40'),
(47, NULL, '00808855b783cd095dd1223c836c7ef006b9b203', '2026-07-05 07:41:12', '2026-07-05 07:41:12'),
(48, NULL, '6a438dff4f291711941e2a2cf55c9c527d125625', '2026-07-05 13:09:18', '2026-07-05 13:09:18'),
(49, NULL, '7d49e97a97784bd1998320222cbd9a80790dae13', '2026-07-05 13:24:21', '2026-07-05 13:24:21'),
(50, NULL, '58dd4977d84591c4cd7fcb06e52cc548a9bfce09', '2026-07-05 13:28:06', '2026-07-05 13:28:06');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` int(11) NOT NULL,
  `cart_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart_items`
--

INSERT INTO `cart_items` (`id`, `cart_id`, `product_id`, `quantity`, `price`, `created_at`, `updated_at`) VALUES
(1, 1, 13, 1, 160.00, '2026-07-04 16:01:05', '2026-07-04 16:01:05'),
(11, 1, 14, 1, 320.00, '2026-07-04 19:25:33', '2026-07-04 19:25:33'),
(14, 25, 17, 1, 380.00, '2026-07-05 05:59:49', '2026-07-05 06:00:29'),
(15, 25, 8, 1, 420.00, '2026-07-05 06:27:32', '2026-07-05 06:27:32');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(150) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `image`, `icon`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Fruits', 'fruits', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(2, NULL, 'Vegetables', 'vegetables', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(3, NULL, 'Dairy', 'dairy', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(4, NULL, 'Bakery', 'bakery', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(5, NULL, 'Beverages', 'beverages', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(6, NULL, 'Snacks', 'snacks', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(7, NULL, 'Meat', 'meat', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(8, NULL, 'Frozen', 'frozen', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(9, NULL, 'Breakfast', 'breakfast', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(10, NULL, 'Cooking', 'cooking', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(11, NULL, 'Organic', 'organic', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(12, NULL, 'Cleaning', 'cleaning', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(13, NULL, 'Personal Care', 'personal-care', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(14, NULL, 'Baby Care', 'baby-care', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(15, NULL, 'Pet Care', 'pet-care', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(16, NULL, 'Stationery', 'stationery', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(17, NULL, 'Home Decor', 'home-decor', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(18, NULL, 'Electronics', 'electronics', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(19, NULL, 'Toys', 'toys', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(20, NULL, 'Others', 'others', NULL, NULL, NULL, 1, '2026-07-04 13:52:58', '2026-07-04 13:52:58');

-- --------------------------------------------------------

--
-- Table structure for table `cms_pages`
--

CREATE TABLE `cms_pages` (
  `id` int(11) NOT NULL,
  `page_key` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cms_pages`
--

INSERT INTO `cms_pages` (`id`, `page_key`, `title`, `content`, `meta_title`, `meta_description`, `meta_keywords`, `updated_at`) VALUES
(1, 'about', 'About Us', '<p>Welcome to GroCo Grocery Store! We deliver fresh organic groceries right to your doorstep.</p>', 'About Us - GroCo Store', 'Learn more about GroCo organic grocery store and our mission.', NULL, '2026-07-05 07:33:56'),
(2, 'contact', 'Contact Us', '<p>Contact our support team for order adjustments, billing questions, or feedback.</p>', 'Contact Us - Help Desk', 'Get in touch with the GroCo support team.', NULL, '2026-07-05 07:33:56'),
(3, 'faq', 'Frequently Asked Questions', '<p>Browse our categories below to find answers to shipping, payment, or order inquiries.</p>', 'FAQ Help Center', 'Frequently asked questions about ordering groceries online.', NULL, '2026-07-05 07:33:56'),
(4, 'privacy', 'Privacy Policy', '<p>Your privacy is important to us. Read our policy on how we collect and secure buyer data.</p>', 'Privacy Policy & Data Rights', 'Data protection guidelines at GroCo.', NULL, '2026-07-05 07:33:56'),
(5, 'terms', 'Terms & Conditions', '<p>Review the terms of service governing our grocery portal usage.</p>', 'Terms of Service', 'Terms and conditions for online shopping.', NULL, '2026-07-05 07:33:56'),
(6, 'refund', 'Refund Policy', '<p>We offer a hassle-free refund process for damaged grocery items reported within 24 hours.</p>', 'Returns & Refunds Policy', 'Information on product replacements and refunds.', NULL, '2026-07-05 07:33:56'),
(7, 'shipping', 'Shipping Policy', '<p>GroCo delivers across Dhaka. Standard delivery takes 24 hours, and express delivery is within 2 hours.</p>', 'Shipping & Delivery Policy', 'Delivery details, coverage zones, and charges.', NULL, '2026-07-05 07:33:56'),
(8, 'cookie', 'Cookie Policy', '<p>We use cookies to analyze traffic and save your grocery shopping cart preferences.</p>', 'Cookie Guidelines', 'How we use browser cookies.', NULL, '2026-07-05 07:33:56'),
(9, 'careers', 'Careers at GroCo', '<p>Join our growing team of delivery fleet managers and engineers.</p>', 'Careers & Job Openings', 'Work with Bangladesh\'s leading online grocery platform.', NULL, '2026-07-05 07:33:56'),
(10, 'press', 'Press & Media Kit', '<p>Official announcements, logos, and media press kits for GroCo.</p>', 'Press Room & Media', 'GroCo news and official press kits.', NULL, '2026-07-05 07:33:56');

-- --------------------------------------------------------

--
-- Table structure for table `compare_items`
--

CREATE TABLE `compare_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(191) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `compare_items`
--

INSERT INTO `compare_items` (`id`, `user_id`, `session_id`, `product_id`, `created_at`) VALUES
(3, 5, NULL, 17, '2026-07-05 05:59:37');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `phone`, `subject`, `message`, `is_read`, `is_archived`, `created_at`) VALUES
(1, 'Newsletter Subscriber', 'bikash@gmail.com', NULL, 'Newsletter Opt-in', 'User subscribed to newsletter updates.', 0, 0, '2026-07-05 06:11:54');

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `type` varchar(50) DEFAULT 'percentage',
  `discount_percent` decimal(5,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `max_discount_amount` decimal(10,2) DEFAULT NULL,
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `usage_limit` int(11) DEFAULT NULL,
  `times_used` int(11) DEFAULT 0,
  `product_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `valid_from` datetime DEFAULT current_timestamp(),
  `valid_until` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `coupons`
--

INSERT INTO `coupons` (`id`, `code`, `type`, `discount_percent`, `discount_amount`, `max_discount_amount`, `min_order_amount`, `usage_limit`, `times_used`, `product_id`, `category_id`, `brand_id`, `valid_from`, `valid_until`, `is_active`, `created_at`) VALUES
(1, 'WELCOME20', 'percentage', 20.00, NULL, NULL, 0.00, NULL, 0, NULL, NULL, NULL, '2026-07-04 19:52:58', '2026-12-31 23:59:59', 1, '2026-07-04 13:52:58');

-- --------------------------------------------------------

--
-- Table structure for table `customer_activities`
--

CREATE TABLE `customer_activities` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_activity_logs`
--

CREATE TABLE `customer_activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_deleted_records`
--

CREATE TABLE `customer_deleted_records` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `deleted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_devices`
--

CREATE TABLE `customer_devices` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_name` varchar(100) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `is_trusted` tinyint(1) DEFAULT 1,
  `last_active_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_login_logs`
--

CREATE TABLE `customer_login_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `status` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_notifications`
--

CREATE TABLE `customer_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'account',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_security_logs`
--

CREATE TABLE `customer_security_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_closings`
--

CREATE TABLE `daily_closings` (
  `id` int(11) NOT NULL,
  `closing_date` date NOT NULL,
  `opening_balance` decimal(12,2) NOT NULL,
  `total_income` decimal(12,2) NOT NULL,
  `total_expense` decimal(12,2) NOT NULL,
  `closing_balance` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `damaged_products`
--

CREATE TABLE `damaged_products` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dashboard_notifications`
--

CREATE TABLE `dashboard_notifications` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dashboard_notifications`
--

INSERT INTO `dashboard_notifications` (`id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 'user_registered', 'New Customer Registration', 'Customer \'bikash\' created a new shopping account.', 'customers/index.php?id=5', 0, '2026-07-05 06:59:01'),
(2, 'user_registered', 'New Customer Registration', 'Customer \'Admin User\' created a new shopping account.', 'customers/index.php?id=1', 0, '2026-07-05 06:59:01'),
(3, 'order', 'New Order Received', 'Order #ORD-20260705-7BFB4218 has been placed (Total: ৳239.00).', 'orders/index.php?search=ORD-20260705-7BFB4218', 0, '2026-07-05 06:59:01'),
(4, 'order', 'New Order Received', 'Order #ORD-20260705-8A6D6BB9 has been placed (Total: ৳449.00).', 'orders/index.php?search=ORD-20260705-8A6D6BB9', 0, '2026-07-05 06:59:01'),
(5, 'system', 'System Optimization', 'Database statistics index and pre-computed caches have been verified.', NULL, 0, '2026-07-05 06:59:01');

-- --------------------------------------------------------

--
-- Table structure for table `delivery_assignments`
--

CREATE TABLE `delivery_assignments` (
  `id` int(11) NOT NULL,
  `delivery_boy_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` varchar(50) DEFAULT 'assigned',
  `otp` varchar(6) DEFAULT NULL,
  `commission_amount` decimal(10,2) DEFAULT 0.00,
  `route_details` varchar(255) DEFAULT NULL,
  `failed_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_boys`
--

CREATE TABLE `delivery_boys` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `status` varchar(50) DEFAULT 'active',
  `commission_rate` decimal(10,2) DEFAULT 50.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_boys`
--

INSERT INTO `delivery_boys` (`id`, `name`, `phone`, `email`, `status`, `commission_rate`, `created_at`) VALUES
(1, 'Rashedul Islam', '01711122233', 'rashedul@groco.com', 'active', 50.00, '2026-07-05 12:56:22'),
(2, 'Mustafizur Rahman', '01811122233', 'mustafizur@groco.com', 'active', 50.00, '2026-07-05 12:56:22'),
(3, 'Abdur Rahim', '01911122233', 'rahim@groco.com', 'active', 60.00, '2026-07-05 12:56:22');

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` int(11) NOT NULL,
  `to_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` longtext NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `retry_count` int(11) DEFAULT 0,
  `error_message` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `name`, `created_at`) VALUES
(1, 'Salaries & Wages', '2026-07-05 12:53:56'),
(2, 'Office Rent', '2026-07-05 12:53:56'),
(3, 'Electricity & Utilities', '2026-07-05 12:53:56'),
(4, 'Inventory Procurement', '2026-07-05 12:53:56'),
(5, 'Logistics & Shipping', '2026-07-05 12:53:56'),
(6, 'Customer Refunds', '2026-07-05 12:53:56'),
(7, 'Marketing & Promo', '2026-07-05 12:53:56');

-- --------------------------------------------------------

--
-- Table structure for table `expiry_products`
--

CREATE TABLE `expiry_products` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `quantity` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `category`, `question`, `answer`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'Shipping', 'What are your delivery hours?', 'We deliver daily from 9:00 AM to 9:00 PM.', 0, 1, '2026-07-05 07:33:56'),
(2, 'Shipping', 'How much is the delivery fee?', 'Standard shipping charge is ৳50.', 1, 1, '2026-07-05 07:33:56'),
(3, 'Payments', 'Do you support Cash on Delivery?', 'Yes, we accept Cash on Delivery and online bKash payments.', 2, 1, '2026-07-05 07:33:56');

-- --------------------------------------------------------

--
-- Table structure for table `flash_sales`
--

CREATE TABLE `flash_sales` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `discount_percent` decimal(5,2) NOT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL,
  `remaining_stock` int(11) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) DEFAULT 'general',
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `link`, `is_read`, `created_at`) VALUES
(2, 5, 'Order Placed!', 'Your order #ORD-20260705-8A6D6BB9 has been successfully placed. We will process it shortly.', 'order', NULL, 1, '2026-07-05 05:14:55'),
(3, 5, 'Payment Method Set', 'Payment method COD selected for order #ORD-20260705-8A6D6BB9.', 'order', NULL, 1, '2026-07-05 05:14:55'),
(4, 5, 'Order Placed!', 'Your order #ORD-20260705-7BFB4218 has been successfully placed. We will process it shortly.', 'order', NULL, 0, '2026-07-05 05:52:38'),
(5, 5, 'Payment Method Set', 'Payment method COD selected for order #ORD-20260705-7BFB4218.', 'order', NULL, 0, '2026-07-05 05:52:38');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `user_id` int(11) NOT NULL,
  `address_id` int(11) DEFAULT NULL,
  `coupon_id` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `delivery_charge` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cod','card','mobile_banking') DEFAULT 'cod',
  `payment_status` enum('unpaid','paid','refunded') DEFAULT 'unpaid',
  `status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `address_id`, `coupon_id`, `subtotal`, `discount_amount`, `delivery_charge`, `total_amount`, `payment_method`, `payment_status`, `status`, `note`, `created_at`, `updated_at`) VALUES
(2, 'ORD-20260705-8A6D6BB9', 5, NULL, NULL, 380.00, 0.00, 50.00, 449.00, 'cod', 'unpaid', 'pending', '', '2026-07-05 05:14:55', '2026-07-05 05:14:55'),
(3, 'ORD-20260705-7BFB4218', 5, 9, NULL, 180.00, 0.00, 50.00, 239.00, 'cod', 'unpaid', 'pending', '', '2026-07-05 05:52:38', '2026-07-05 05:52:38');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `product_sku` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` int(11) NOT NULL,
  `line_total` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `product_name`, `product_sku`, `price`, `quantity`, `line_total`) VALUES
(1, 2, 17, 'Nestle Cerelac Rice (400g)', 'BAB-CER-026', 380.00, 1, 380.00),
(2, 3, 16, 'Surf Excel Detergent (1kg)', 'CLE-DET-024', 180.00, 1, 180.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_status_history`
--

INSERT INTO `order_status_history` (`id`, `order_id`, `status`, `note`, `created_at`) VALUES
(2, 2, 'pending', 'Order placed successfully. Awaiting processing.', '2026-07-05 05:14:55'),
(3, 3, 'pending', 'Order placed successfully. Awaiting processing.', '2026-07-05 05:52:38');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_gateway_logs`
--

CREATE TABLE `payment_gateway_logs` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `gateway` varchar(50) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(50) NOT NULL,
  `response_payload` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_drawer_transactions`
--

CREATE TABLE `pos_drawer_transactions` (
  `id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_hold_orders`
--

CREATE TABLE `pos_hold_orders` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `cart_data` longtext NOT NULL,
  `hold_notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_returns`
--

CREATE TABLE `pos_returns` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `refund_amount` decimal(12,2) NOT NULL,
  `refund_method` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_return_items`
--

CREATE TABLE `pos_return_items` (
  `id` int(11) NOT NULL,
  `pos_return_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pos_shifts`
--

CREATE TABLE `pos_shifts` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `start_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_time` timestamp NULL DEFAULT NULL,
  `opening_cash` decimal(10,2) DEFAULT 0.00,
  `closing_cash` decimal(10,2) DEFAULT 0.00,
  `actual_cash` decimal(10,2) DEFAULT 0.00,
  `status` varchar(50) DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `sku` varchar(50) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `unit` varchar(50) DEFAULT 'pcs',
  `stock` int(11) DEFAULT 0,
  `min_stock` int(11) DEFAULT 5,
  `weight` decimal(8,2) DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_trending` tinyint(1) DEFAULT 0,
  `is_flash_sale` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `status` varchar(50) DEFAULT 'Published',
  `avg_rating` decimal(3,2) DEFAULT 0.00,
  `review_count` int(11) DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `brand_id`, `name`, `slug`, `description`, `short_description`, `sku`, `barcode`, `price`, `cost_price`, `discount_price`, `unit`, `stock`, `min_stock`, `weight`, `thumbnail`, `is_featured`, `is_trending`, `is_flash_sale`, `is_active`, `status`, `avg_rating`, `review_count`, `meta_title`, `meta_description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, NULL, 'Organic Gala Apples', 'organic-gala-apples', 'Crisp, sweet organic gala apples imported from premium orchards. Rich in fiber and vitamin C.', 'Sweet, crisp and fresh organic gala apples.', 'FRU-APP-001', '8801234560012', 250.00, NULL, 220.00, 'kg', 150, 5, NULL, 'products/apple.jpg', 1, 0, 0, 1, 'Published', 4.00, 1, 'Organic Gala Apples - Fresh Fruits', 'Buy organic gala apples online at best price. Fresh and crisp.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(2, 2, NULL, 'Fresh Broccoli', 'fresh-broccoli', 'Green and nutrient-rich fresh broccoli. Perfect for steaming, salads, and healthy stir-fries.', 'Fresh green broccoli crowns.', 'VEG-BRO-002', '8801234560029', 120.00, NULL, 99.00, 'pcs', 80, 5, NULL, 'products/broccoli.jpg', 1, 0, 0, 1, 'Published', 4.00, 1, 'Fresh Broccoli Online', 'Get fresh and healthy broccoli delivered.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(3, 3, 4, 'Pasteurized Whole Milk (1L)', 'pasteurized-whole-milk-1l', 'Rich and creamy pasteurized whole milk, sourced from local organic dairy farms.', '1 Liter whole milk pack.', 'DAI-MIL-003', '8801234560036', 90.00, NULL, NULL, 'pcs', 120, 5, NULL, 'products/milk.jpg', 1, 0, 0, 1, 'Published', 5.00, 1, 'Whole Milk 1L - Fresh Dairy', 'Shop farm fresh milk online.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(4, 4, NULL, 'Artisan Sourdough Bread', 'artisan-sourdough-bread', 'Naturally fermented artisan sourdough bread with a crusty exterior and chewy crumb.', 'Freshly baked sourdough bread.', 'BAK-SOU-004', '8801234560043', 180.00, NULL, 165.00, 'pcs', 30, 5, NULL, 'products/bread.jpg', 1, 0, 0, 1, 'Published', 4.00, 1, 'Artisan Sourdough Bread', 'Freshly baked sourdough bread.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(5, 7, NULL, 'Boneless Chicken Breast', 'boneless-chicken-breast', 'Lean, high-protein boneless chicken breast. Processed under strict hygiene controls.', 'Fresh antibiotic-free chicken breast.', 'MEA-CHI-005', '8801234560050', 360.00, NULL, 330.00, 'kg', 45, 5, NULL, 'products/chicken.jpg', 1, 0, 0, 1, 'Published', 4.00, 1, 'Boneless Chicken Breast', 'Buy premium boneless chicken breast online.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(6, 1, NULL, 'Navel Oranges', 'navel-oranges', 'Sweet, juicy and seedless Navel Oranges. Packed with Vitamin C.', 'Sweet, juicy navel oranges.', 'FRU-ORA-006', '8801234560067', 280.00, NULL, 260.00, 'kg', 200, 5, NULL, 'products/orange.jpg', 0, 0, 0, 1, 'Published', 5.00, 1, 'Navel Oranges - Fresh Fruits', 'Juicy sweet navel oranges.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(7, 2, NULL, 'Fresh Spinach Bunch', 'fresh-spinach-bunch', 'Organic leafy green spinach. Rich in iron, vitamins, and minerals.', 'Leafy green fresh spinach.', 'VEG-SPI-007', '8801234560074', 40.00, NULL, 30.00, 'pcs', 110, 5, NULL, 'products/spinach.jpg', 0, 0, 0, 1, 'Published', 5.00, 1, 'Fresh Spinach Online', 'Buy organic spinach online.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(8, 10, NULL, 'Miniket Rice Premium (5kg)', 'miniket-rice-premium-5kg', 'Double-polished premium Miniket rice. High-quality grains with beautiful aroma.', 'Premium Miniket rice 5kg pack.', 'COO-RIC-008', '8801234560081', 420.00, NULL, 395.00, 'pcs', 95, 5, NULL, 'products/miniket.jpg', 1, 0, 0, 1, 'Published', 4.00, 1, 'Miniket Rice 5kg Pack', 'Best miniket rice price in Bangladesh.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(9, 10, NULL, 'Red Lentils (Masoor Dal) 1kg', 'red-lentils-masoor-dal-1kg', 'High-quality, clean, and protein-packed red lentils. A staple in traditional cooking.', 'Premium Masoor Dal 1kg.', 'COO-DAL-009', '8801234560098', 140.00, NULL, 130.00, 'pcs', 150, 5, NULL, 'products/masoor_dal.jpg', 0, 0, 0, 1, 'Published', 4.00, 1, 'Red Lentils 1kg Online', 'Buy premium masoor dal.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(10, 10, NULL, 'Soybean Oil (1L)', 'soybean-oil-1l', 'Refined soybean oil containing vitamin A and D. Excellent for daily cooking.', 'Refined soybean oil 1 Liter.', 'COO-OIL-010', '8801234560104', 165.00, NULL, 160.00, 'pcs', 100, 5, NULL, 'products/soybean_oil.jpg', 1, 0, 0, 1, 'Published', 4.00, 1, 'Soybean Oil 1L', 'Pure refined cooking oil.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(11, 6, 5, 'Potato Chips Classic (100g)', 'potato-chips-classic-100g', 'Thinly sliced potatoes fried to a perfect golden crispiness and salted lightly.', 'Crisp potato chips 100g.', 'SNA-CHI-015', '8801234560159', 50.00, NULL, 45.00, 'pcs', 300, 5, NULL, 'products/chips.jpg', 0, 0, 0, 1, 'Published', 4.00, 1, 'Classic Potato Chips', 'Crispy and tasty snacks.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(12, 5, 3, 'Black Tea Premium Blend (200g)', 'black-tea-premium-blend-200g', 'Freshly plucked leaves from the gardens of Sylhet, blended to give a strong color and aroma.', 'Strong black tea blend 200g.', 'BEV-TEA-016', '8801234560166', 110.00, NULL, 95.00, 'pcs', 140, 5, NULL, 'products/tea.jpg', 1, 0, 0, 1, 'Published', 4.00, 1, 'Premium Black Tea', 'Authentic Bangladeshi tea.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(13, 9, 1, 'Maggi Masala Noodles (Pack of 8)', 'maggi-masala-noodles-pack-of-8', 'Your favorite 2-minute Maggi noodles with an authentic blend of spices. Convenient and delicious.', 'Instant masala noodles pack of 8.', 'BRE-NOD-018', '8801234560180', 160.00, NULL, 150.00, 'pcs', 180, 5, NULL, 'products/noodles.jpg', 1, 0, 0, 1, 'Published', 5.00, 1, 'Maggi Masala Noodles', 'Quick breakfast noodles.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(14, 8, NULL, 'Frozen Chicken Nuggets (500g)', 'frozen-chicken-nuggets-500g', 'Breaded chicken nuggets ready to deep fry or bake. Crispy on the outside, juicy inside.', 'Frozen chicken nuggets 500g.', 'FRO-NUG-020', '8801234560203', 320.00, NULL, 280.00, 'pcs', 70, 5, NULL, 'products/nuggets.jpg', 1, 0, 0, 1, 'Published', 5.00, 1, 'Frozen Chicken Nuggets 500g', 'Quick snacks for kids.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(15, 13, 3, 'Lifebuoy Soap Total (100g)', 'lifebuoy-soap-total-100g', 'Germ protection bar soap. Keeps your family safe from bacteria and viruses.', 'Antibacterial bar soap.', 'PER-SOA-022', '8801234560227', 45.00, NULL, 40.00, 'pcs', 250, 5, NULL, 'products/lifebuoy.jpg', 0, 0, 0, 1, 'Published', 4.00, 1, 'Lifebuoy Soap 100g', 'Germ protection soap.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(16, 12, 3, 'Surf Excel Detergent (1kg)', 'surf-excel-detergent-1kg', 'Surf Excel Quick Wash removes tough stains easily with the power of bleach. Gentle on clothes.', 'Stain removing detergent powder 1kg.', 'CLE-DET-024', '8801234560241', 180.00, NULL, 165.00, 'pcs', 99, 5, NULL, 'products/surf_excel.jpg', 0, 0, 0, 1, 'Published', 5.00, 1, 'Surf Excel 1kg Price', 'Laundry detergent powder.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL),
(17, 14, 1, 'Nestle Cerelac Rice (400g)', 'nestle-cerelac-rice-400g', 'Nestle Cerelac infant cereal with milk and rice, enriched with iron and essential nutrients for babies from 6 months.', 'Infant cereal rice flavor.', 'BAB-CER-026', '8801234560265', 380.00, NULL, 350.00, 'pcs', 49, 5, NULL, 'products/cerelac.png', 1, 0, 0, 1, 'Published', 5.00, 1, 'Nestle Cerelac Rice 400g', 'Infant cereal baby food.', '2026-07-04 15:54:40', '2026-07-05 06:37:53', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `review_title` varchar(255) DEFAULT NULL,
  `review_comment` text DEFAULT NULL,
  `review_images` text DEFAULT NULL,
  `verified_purchase` tinyint(1) DEFAULT 0,
  `status` varchar(50) DEFAULT 'approved',
  `helpful_count` int(11) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`id`, `product_id`, `user_id`, `order_id`, `rating`, `review_title`, `review_comment`, `review_images`, `verified_purchase`, `status`, `helpful_count`, `is_approved`, `created_at`, `updated_at`) VALUES
(1, 1, 1, NULL, 4, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(2, 6, 1, NULL, 5, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(3, 2, 1, NULL, 4, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(4, 7, 1, NULL, 5, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(5, 3, 1, NULL, 5, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(6, 4, 1, NULL, 4, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(7, 12, 1, NULL, 4, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(8, 11, 1, NULL, 4, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(9, 5, 1, NULL, 4, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(10, 14, 1, NULL, 5, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(11, 13, 1, NULL, 5, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(12, 8, 1, NULL, 4, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(13, 9, 1, NULL, 4, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(14, 10, 1, NULL, 4, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(15, 16, 1, NULL, 5, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(16, 15, 1, NULL, 4, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL),
(17, 17, 1, NULL, 5, NULL, 'Excellent quality product! Very fresh and fast delivery. Highly recommended.', NULL, 0, 'approved', 0, 1, '2026-07-04 15:54:40', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_number` varchar(100) NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `total_amount` decimal(12,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_items`
--

CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `created_at`, `updated_at`) VALUES
(1, 'admin', '2026-07-04 13:52:58', '2026-07-04 13:52:58'),
(2, 'customer', '2026-07-04 13:52:58', '2026-07-04 13:52:58');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `key_name`, `value`, `updated_at`) VALUES
(1, 'site_name', 'Grocery Store', '2026-07-04 13:52:58'),
(2, 'delivery_charge', '50.00', '2026-07-04 13:52:58'),
(3, 'currency', 'BDT', '2026-07-04 13:52:58'),
(4, 'homepage_banners', '[\n    {\n        \"title\": \"Fresh Organic Vegetables\",\n        \"subtitle\": \"Get up to 30% Off on Daily Essentials\",\n        \"button_text\": \"Shop Now\",\n        \"button_link\": \"products.php?category=vegetables\",\n        \"image\": \"banners\\/banner1.png\"\n    },\n    {\n        \"title\": \"Premium Dairy & Eggs\",\n        \"subtitle\": \"Farm fresh milk and butter at your door\",\n        \"button_text\": \"Explore Deals\",\n        \"button_link\": \"products.php?category=dairy\",\n        \"image\": \"banners\\/banner2.png\"\n    },\n    {\n        \"title\": \"Fresh Fruits & Juices\",\n        \"subtitle\": \"100% natural, sweet and juicy fruits\",\n        \"button_text\": \"View Collection\",\n        \"button_link\": \"products.php?category=fruits\",\n        \"image\": \"banners\\/banner3.png\"\n    }\n]', '2026-07-04 15:54:40'),
(5, 'site_email', 'support@groco.com.bd', '2026-07-05 07:33:56'),
(6, 'site_phone', '+880 1712 345678', '2026-07-05 07:33:56'),
(7, 'site_address', 'Flat 4A, House 12, Road 4, Banani, Dhaka, Bangladesh', '2026-07-05 07:33:56'),
(8, 'site_business_hours', '9:00 AM - 10:00 PM, Saturday - Thursday', '2026-07-05 07:33:56'),
(9, 'site_facebook', 'https://facebook.com/groco.store', '2026-07-05 07:33:56'),
(10, 'site_twitter', 'https://twitter.com/groco_store', '2026-07-05 07:33:56'),
(11, 'site_instagram', 'https://instagram.com/groco.store', '2026-07-05 07:33:56'),
(12, 'site_google_map', 'https://www.google.com/maps/embed?pb=...', '2026-07-05 07:33:56'),
(13, 'site_copyright', '&copy; 2026 GroCo Grocery Store. All rights reserved.', '2026-07-05 07:33:56'),
(14, 'site_meta_title', 'GroCo — Fresh Organic Groceries Delivered in 2 Hours', '2026-07-05 07:33:56'),
(15, 'site_meta_description', 'Order fresh organic vegetables, fruits, dairy, and household essentials online.', '2026-07-05 07:33:56'),
(16, 'site_meta_keywords', 'grocery, online store, fresh organic vegetables, dhaka delivery', '2026-07-05 07:33:56'),
(17, 'announcement_bar_text', '🎉 Mega Discount! Use coupon code SAVE20 to get 20% off on your first order.', '2026-07-05 07:33:56'),
(18, 'announcement_bar_active', '1', '2026-07-05 07:33:56'),
(19, 'popup_notice_title', 'Holiday Notice', '2026-07-05 07:33:56'),
(20, 'popup_notice_message', 'Our delivery fleet will be resting on Eid day. Normal operations resume the day after.', '2026-07-05 07:33:56'),
(21, 'popup_notice_active', '0', '2026-07-05 07:33:56'),
(22, 'holiday_notice_active', '0', '2026-07-05 07:33:56'),
(23, 'maintenance_notice_active', '0', '2026-07-05 07:33:56'),
(24, 'homepage_layout_sections', '[{\"id\":\"hero_slider\",\"name\":\"Hero Slider\",\"visible\":true},{\"id\":\"flash_sale\",\"name\":\"Flash Sales\",\"visible\":true},{\"id\":\"featured_categories\",\"name\":\"Featured Categories\",\"visible\":true},{\"id\":\"featured_products\",\"name\":\"Featured Products\",\"visible\":true},{\"id\":\"trending_products\",\"name\":\"Trending Products\",\"visible\":true}]', '2026-07-05 07:33:56');

-- --------------------------------------------------------

--
-- Table structure for table `sms_queue`
--

CREATE TABLE `sms_queue` (
  `id` int(11) NOT NULL,
  `to_phone` varchar(50) NOT NULL,
  `message` varchar(255) NOT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `retry_count` int(11) DEFAULT 0,
  `error_message` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_backups`
--

CREATE TABLE `system_backups` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `rating` int(11) DEFAULT 5,
  `comment` text NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `testimonials`
--

INSERT INTO `testimonials` (`id`, `name`, `designation`, `rating`, `comment`, `image_path`, `is_active`, `created_at`) VALUES
(1, 'Rahim Uddin', 'Verified Buyer', 5, 'Exceptional quality organic vegetables and very fast delivery.', NULL, 1, '2026-07-05 07:33:56'),
(2, 'Jahanara Khan', 'Regular Customer', 5, 'The best online grocery store in Dhaka. Highly recommended!', NULL, 1, '2026-07-05 07:33:56');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reference` varchar(255) NOT NULL,
  `payment_method` varchar(100) DEFAULT 'cash',
  `reconciled` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 2,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `wallet_balance` decimal(10,2) DEFAULT 0.00,
  `reward_points` int(11) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `phone_verified` tinyint(1) DEFAULT 0,
  `email_verified` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_banned` tinyint(1) DEFAULT 0,
  `banned_until` datetime DEFAULT NULL,
  `ban_reason` varchar(255) DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `failed_logins` int(11) DEFAULT 0,
  `last_password_change` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `full_name`, `username`, `email`, `phone`, `gender`, `dob`, `password`, `avatar`, `wallet_balance`, `reward_points`, `is_verified`, `phone_verified`, `email_verified`, `is_active`, `is_banned`, `banned_until`, `ban_reason`, `remember_token`, `failed_logins`, `last_password_change`, `last_login_at`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'Admin User', NULL, 'admin@grocery.com', NULL, NULL, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 0.00, 0, 1, 0, 0, 1, 0, NULL, NULL, NULL, 0, NULL, NULL, '2026-07-04 13:52:58', '2026-07-04 13:52:58', NULL),
(5, 2, 'bikash', NULL, 'bikash@gmail.com', '+8801782427035', NULL, NULL, '$2y$10$WbchCAzoT32VnYJzkrQQ8.B54D/BvJ7bjPxuzSgVs6acmGAMCnrDm', 'storage/uploads/users/avatar_5_1783230198.png', 0.00, 0, 0, 0, 0, 1, 0, NULL, NULL, NULL, 0, NULL, '2026-07-05 12:28:06', '2026-07-05 05:10:20', '2026-07-05 06:28:06', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlists`
--

INSERT INTO `wishlists` (`id`, `user_id`, `product_id`, `created_at`) VALUES
(4, 5, 17, '2026-07-05 05:59:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `admin_login_logs`
--
ALTER TABLE `admin_login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `admin_permissions`
--
ALTER TABLE `admin_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_key` (`permission_key`);

--
-- Indexes for table `admin_roles`
--
ALTER TABLE `admin_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `admin_role_permissions`
--
ALTER TABLE `admin_role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ip_address` (`ip_address`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_session` (`session_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_cart_product` (`cart_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `cms_pages`
--
ALTER TABLE `cms_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_key` (`page_key`);

--
-- Indexes for table `compare_items`
--
ALTER TABLE `compare_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `idx_session` (`session_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `customer_activities`
--
ALTER TABLE `customer_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `customer_activity_logs`
--
ALTER TABLE `customer_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cust_act_logs_user` (`user_id`);

--
-- Indexes for table `customer_deleted_records`
--
ALTER TABLE `customer_deleted_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `deleted_by` (`deleted_by`);

--
-- Indexes for table `customer_devices`
--
ALTER TABLE `customer_devices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `customer_login_logs`
--
ALTER TABLE `customer_login_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cust_login_logs_user` (`user_id`);

--
-- Indexes for table `customer_notifications`
--
ALTER TABLE `customer_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `customer_security_logs`
--
ALTER TABLE `customer_security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `daily_closings`
--
ALTER TABLE `daily_closings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `closing_date` (`closing_date`);

--
-- Indexes for table `damaged_products`
--
ALTER TABLE `damaged_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `dashboard_notifications`
--
ALTER TABLE `dashboard_notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `delivery_assignments`
--
ALTER TABLE `delivery_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `delivery_boy_id` (`delivery_boy_id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `delivery_boys`
--
ALTER TABLE `delivery_boys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `expiry_products`
--
ALTER TABLE `expiry_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `flash_sales`
--
ALTER TABLE `flash_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `address_id` (`address_id`),
  ADD KEY `coupon_id` (`coupon_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`token`);

--
-- Indexes for table `payment_gateway_logs`
--
ALTER TABLE `payment_gateway_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pos_drawer_transactions`
--
ALTER TABLE `pos_drawer_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shift_id` (`shift_id`);

--
-- Indexes for table `pos_hold_orders`
--
ALTER TABLE `pos_hold_orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pos_returns`
--
ALTER TABLE `pos_returns`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pos_return_items`
--
ALTER TABLE `pos_return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pos_return_id` (`pos_return_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `pos_shifts`
--
ALTER TABLE `pos_shifts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `brand_id` (`brand_id`);
ALTER TABLE `products` ADD FULLTEXT KEY `ft_name_desc` (`name`,`description`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_product_review` (`product_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `purchase_order_id` (`purchase_order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_name` (`key_name`);

--
-- Indexes for table `sms_queue`
--
ALTER TABLE `sms_queue`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_backups`
--
ALTER TABLE `system_backups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `idx_users_deleted_at` (`deleted_at`),
  ADD KEY `idx_users_is_active` (`is_active`),
  ADD KEY `idx_users_is_banned` (`is_banned`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_product_wishlist` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_login_logs`
--
ALTER TABLE `admin_login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_permissions`
--
ALTER TABLE `admin_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `admin_roles`
--
ALTER TABLE `admin_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `banners`
--
ALTER TABLE `banners`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blocked_ips`
--
ALTER TABLE `blocked_ips`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `cms_pages`
--
ALTER TABLE `cms_pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `compare_items`
--
ALTER TABLE `compare_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_activities`
--
ALTER TABLE `customer_activities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_activity_logs`
--
ALTER TABLE `customer_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_deleted_records`
--
ALTER TABLE `customer_deleted_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_devices`
--
ALTER TABLE `customer_devices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_login_logs`
--
ALTER TABLE `customer_login_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_notifications`
--
ALTER TABLE `customer_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_security_logs`
--
ALTER TABLE `customer_security_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_closings`
--
ALTER TABLE `daily_closings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `damaged_products`
--
ALTER TABLE `damaged_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dashboard_notifications`
--
ALTER TABLE `dashboard_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `delivery_assignments`
--
ALTER TABLE `delivery_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_boys`
--
ALTER TABLE `delivery_boys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `expiry_products`
--
ALTER TABLE `expiry_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `flash_sales`
--
ALTER TABLE `flash_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_gateway_logs`
--
ALTER TABLE `payment_gateway_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_drawer_transactions`
--
ALTER TABLE `pos_drawer_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_hold_orders`
--
ALTER TABLE `pos_hold_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_returns`
--
ALTER TABLE `pos_returns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_return_items`
--
ALTER TABLE `pos_return_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_shifts`
--
ALTER TABLE `pos_shifts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `sms_queue`
--
ALTER TABLE `sms_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_backups`
--
ALTER TABLE `system_backups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `admin_roles` (`id`);

--
-- Constraints for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD CONSTRAINT `admin_activity_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_login_logs`
--
ALTER TABLE `admin_login_logs`
  ADD CONSTRAINT `admin_login_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_role_permissions`
--
ALTER TABLE `admin_role_permissions`
  ADD CONSTRAINT `admin_role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `admin_roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `admin_permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `compare_items`
--
ALTER TABLE `compare_items`
  ADD CONSTRAINT `compare_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `compare_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `customer_activities`
--
ALTER TABLE `customer_activities`
  ADD CONSTRAINT `customer_activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_activity_logs`
--
ALTER TABLE `customer_activity_logs`
  ADD CONSTRAINT `customer_activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_deleted_records`
--
ALTER TABLE `customer_deleted_records`
  ADD CONSTRAINT `customer_deleted_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_deleted_records_ibfk_2` FOREIGN KEY (`deleted_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_devices`
--
ALTER TABLE `customer_devices`
  ADD CONSTRAINT `customer_devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_login_logs`
--
ALTER TABLE `customer_login_logs`
  ADD CONSTRAINT `customer_login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_notifications`
--
ALTER TABLE `customer_notifications`
  ADD CONSTRAINT `customer_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_security_logs`
--
ALTER TABLE `customer_security_logs`
  ADD CONSTRAINT `customer_security_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `damaged_products`
--
ALTER TABLE `damaged_products`
  ADD CONSTRAINT `damaged_products_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_assignments`
--
ALTER TABLE `delivery_assignments`
  ADD CONSTRAINT `delivery_assignments_ibfk_1` FOREIGN KEY (`delivery_boy_id`) REFERENCES `delivery_boys` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `delivery_assignments_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expiry_products`
--
ALTER TABLE `expiry_products`
  ADD CONSTRAINT `expiry_products_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `flash_sales`
--
ALTER TABLE `flash_sales`
  ADD CONSTRAINT `flash_sales_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD CONSTRAINT `inventory_logs_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_logs_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD CONSTRAINT `order_status_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pos_drawer_transactions`
--
ALTER TABLE `pos_drawer_transactions`
  ADD CONSTRAINT `pos_drawer_transactions_ibfk_1` FOREIGN KEY (`shift_id`) REFERENCES `pos_shifts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pos_return_items`
--
ALTER TABLE `pos_return_items`
  ADD CONSTRAINT `pos_return_items_ibfk_1` FOREIGN KEY (`pos_return_id`) REFERENCES `pos_returns` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pos_return_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pos_shifts`
--
ALTER TABLE `pos_shifts`
  ADD CONSTRAINT `pos_shifts_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_order_items`
--
ALTER TABLE `purchase_order_items`
  ADD CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD CONSTRAINT `wishlists_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `wishlists_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
