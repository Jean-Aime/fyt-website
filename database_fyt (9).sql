-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 01, 2025 at 08:43 PM
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
-- Database: `database_fyt`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateCustomerLTV` (IN `customer_id` INT)   BEGIN
    DECLARE total_bookings INT DEFAULT 0;
    DECLARE total_spent DECIMAL(12,2) DEFAULT 0;
    DECLARE avg_booking_value DECIMAL(10,2) DEFAULT 0;
    DECLARE first_booking DATE DEFAULT NULL;
    DECLARE last_booking DATE DEFAULT NULL;
    DECLARE predicted_ltv DECIMAL(12,2) DEFAULT 0;
    DECLARE customer_tier VARCHAR(20) DEFAULT 'bronze';
    
    -- Calculate metrics
    SELECT 
        COUNT(*),
        COALESCE(SUM(total_amount), 0),
        COALESCE(AVG(total_amount), 0),
        MIN(DATE(created_at)),
        MAX(DATE(created_at))
    INTO total_bookings, total_spent, avg_booking_value, first_booking, last_booking
    FROM bookings 
    WHERE user_id = customer_id AND status IN ('confirmed', 'completed');
    
    -- Calculate predicted LTV (simple model)
    SET predicted_ltv = total_spent * 1.5;
    
    -- Determine customer tier
    IF total_spent >= 10000 THEN
        SET customer_tier = 'platinum';
    ELSEIF total_spent >= 5000 THEN
        SET customer_tier = 'gold';
    ELSEIF total_spent >= 2000 THEN
        SET customer_tier = 'silver';
    ELSE
        SET customer_tier = 'bronze';
    END IF;
    
    -- Update or insert LTV record
    INSERT INTO customer_ltv (
        user_id, total_bookings, total_spent, average_booking_value,
        first_booking_date, last_booking_date, predicted_ltv, customer_tier
    ) VALUES (
        customer_id, total_bookings, total_spent, avg_booking_value,
        first_booking, last_booking, predicted_ltv, customer_tier
    ) ON DUPLICATE KEY UPDATE
        total_bookings = VALUES(total_bookings),
        total_spent = VALUES(total_spent),
        average_booking_value = VALUES(average_booking_value),
        first_booking_date = VALUES(first_booking_date),
        last_booking_date = VALUES(last_booking_date),
        predicted_ltv = VALUES(predicted_ltv),
        customer_tier = VALUES(customer_tier),
        last_calculated = NOW();
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `UpdateTourAvailability` (IN `tour_id` INT, IN `tour_date` DATE)   BEGIN
    DECLARE booked_spots INT DEFAULT 0;
    DECLARE max_capacity INT DEFAULT 0;
    DECLARE available_spots INT DEFAULT 0;
    DECLARE availability_status VARCHAR(20) DEFAULT 'available';
    
    -- Get current bookings for this tour and date
    SELECT COALESCE(SUM(adults + children), 0)
    INTO booked_spots
    FROM bookings 
    WHERE tour_id = tour_id 
    AND tour_date = tour_date 
    AND status IN ('confirmed', 'completed');
    
    -- Get tour capacity
    SELECT max_group_size INTO max_capacity
    FROM tours WHERE id = tour_id;
    
    -- Calculate available spots
    SET available_spots = max_capacity - booked_spots;
    
    -- Determine status
    IF available_spots <= 0 THEN
        SET availability_status = 'sold_out';
    ELSEIF available_spots <= 3 THEN
        SET availability_status = 'limited';
    ELSE
        SET availability_status = 'available';
    END IF;
    
    -- Update availability
    INSERT INTO tour_availability (
        tour_id, date, max_capacity, available_spots, status
    ) VALUES (
        tour_id, tour_date, max_capacity, available_spots, availability_status
    ) ON DUPLICATE KEY UPDATE
        available_spots = VALUES(available_spots),
        status = VALUES(status),
        updated_at = NOW();
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_daily_summary`
--

CREATE TABLE `analytics_daily_summary` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `total_bookings` int(11) DEFAULT 0,
  `total_revenue` decimal(12,2) DEFAULT 0.00,
  `total_visitors` int(11) DEFAULT 0,
  `total_page_views` int(11) DEFAULT 0,
  `conversion_rate` decimal(5,4) DEFAULT 0.0000,
  `average_booking_value` decimal(10,2) DEFAULT 0.00,
  `top_tour_id` int(11) DEFAULT NULL,
  `top_country_id` int(11) DEFAULT NULL,
  `bounce_rate` decimal(5,4) DEFAULT 0.0000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_events`
--

CREATE TABLE `analytics_events` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `event_name` varchar(100) NOT NULL,
  `event_category` varchar(100) DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`properties`)),
  `page_url` varchar(500) DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_page_views`
--

CREATE TABLE `analytics_page_views` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `page_url` varchar(500) NOT NULL,
  `page_title` varchar(255) DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  `time_on_page` int(11) DEFAULT NULL,
  `bounce` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_tokens`
--

CREATE TABLE `api_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_name` varchar(100) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','revoked','expired') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_categories`
--

CREATE TABLE `blog_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT '#667eea',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blog_categories`
--

INSERT INTO `blog_categories` (`id`, `name`, `slug`, `description`, `color`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Travel Tips', 'travel-tips', 'Helpful tips and advice for travelers', '#28a745', 'active', '2025-06-27 03:32:03', '2025-06-27 03:32:03'),
(2, 'Destinations', 'destinations', 'Featured destinations and travel guides', '#17a2b8', 'active', '2025-06-27 03:32:03', '2025-06-27 03:32:03'),
(3, 'Tour Updates', 'tour-updates', 'Latest news and updates about our tours', '#ffc107', 'active', '2025-06-27 03:32:03', '2025-06-27 03:32:03'),
(4, 'Company News', 'company-news', 'News and announcements from Forever Young Tours', '#dc3545', 'active', '2025-06-27 03:32:03', '2025-06-27 03:32:03');

-- --------------------------------------------------------

--
-- Table structure for table `blog_comments`
--

CREATE TABLE `blog_comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `author_name` varchar(100) NOT NULL,
  `author_email` varchar(255) NOT NULL,
  `author_website` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `status` enum('pending','approved','spam','trash') DEFAULT 'pending',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `author_id` int(11) DEFAULT NULL,
  `published_at` datetime DEFAULT current_timestamp(),
  `status` enum('draft','published') DEFAULT 'draft',
  `featured_image` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `excerpt` text DEFAULT NULL,
  `content_type` varchar(20) DEFAULT 'blocks',
  `gallery_images` text DEFAULT NULL,
  `reading_time` int(11) DEFAULT NULL,
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `seo_keywords` varchar(255) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `post_type` varchar(50) DEFAULT 'blog',
  `featured` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `title`, `slug`, `content`, `author_id`, `published_at`, `status`, `featured_image`, `created_at`, `updated_at`, `category_id`, `is_featured`, `view_count`, `excerpt`, `content_type`, `gallery_images`, `reading_time`, `seo_title`, `seo_description`, `seo_keywords`, `region`, `post_type`, `featured`) VALUES
(1, '🇷🇼 Rwanda Gorilla Trekking Tour', '-rwanda-gorilla-trekking-tour', '[{\"type\":\"paragraph\",\"content\":\"Embark on a once-in-a-lifetime journey through Rwanda’s misty mountains to encounter endangered mountain gorillas in their natural habitat.\"},{\"type\":\"image\",\"content\":\"\",\"id\":1751069314606,\"src\":\"data:image/jpeg;base64,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', 2, '2025-06-28 02:26:17', 'published', 'uploads/blog/685f36a9c8373.jpeg', '2025-06-28 02:26:17', '2025-06-28 02:26:17', 4, 1, 0, 'Experience the thrill of tracking endangered mountain gorillas in the lush rainforests of Rwanda’s Volcanoes National Park. This guided trek takes you through scenic', 'blocks', '[\"uploads\\/blog\\/685f36a9c8959.jpeg\",\"uploads\\/blog\\/685f36a9c8ea6.jpeg\"]', 51, 'rwanda', 'Iby’Iwacu Cultural Village – local dance, music, and traditions\r\n\r\nScenic views of the Virunga Volcano chain\r\n\r\nDian Fossey Research Center (optional visit)\r\n\r\nKigali', 'troursin', '', 'blog', 0);
INSERT INTO `blog_posts` (`id`, `title`, `slug`, `content`, `author_id`, `published_at`, `status`, `featured_image`, `created_at`, `updated_at`, `category_id`, `is_featured`, `view_count`, `excerpt`, `content_type`, `gallery_images`, `reading_time`, `seo_title`, `seo_description`, `seo_keywords`, `region`, `post_type`, `featured`) VALUES
(2, '🇫🇷 France Travel Overview', '-france-travel-overview', '[{\"type\":\"paragraph\",\"content\":\"France, located in Western Europe, is renowned for its rich culture, iconic landmarks like the Eiffel Tower, diverse landscapes from the French Riviera to the Alps, world-class cuisine, and historic cities such as Paris, Lyon, and Bordeaux. It’s a global hub for art, fashion, and gastronomy.\"},{\"type\":\"image\",\"content\":\"\",\"id\":1751129504739,\"src\":\"data:image/jpeg;base64,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AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABQRYACwAFIAsAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACwAAAAAAABbLEAALAAAAFBAAAAAAAAACwFIAAAAAAAAAAAAAAAAAAAAAAUiiALBYLFIAsBSAAALAsAAAAAABSAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAsABYAAAUEALAFWEAAAAAAAWCwAAALFIAAAAAAAFBAAAAACwAAAAAAALAAsFgALBZSLACywAAAALAAAsAABSAAAALBYAAAAABSAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAABQQAAAAAAAAUhSKIsBSKIoiiKIoiiKItMqIojQy0MtDKiNQi0yoiiKIoiiLAAAAUgAALFIUiwssWxUiwAAAWAACyiAWBUAAFgFJQiiUAIAAAAAAAAAUgLAVCywAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAALAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAsAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFgqCoKgqDUgqDTI0yNMjTIqDTI1IKg1INMjUgtyNMjTI1ILcjTI0yLcjTI0yNMjTI0yNXA0yNMjTI0yNMjcyNzI0yNMjUgqCoLAAAAAqCoFlIAAAAAAAAAAAAAAAAAABYAAAAAAAAAAAAAAAAAAAAAAAAAAFgAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFI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', 2, '2025-06-28 18:57:38', 'published', NULL, '2025-06-28 18:57:39', '2025-06-28 18:57:39', 1, 0, 0, 'France, located in Western Europe, is renowned for its rich culture, iconic landmarks like the Eiffel Tower, diverse landscapes from the French Riviera to the Alps, world-class cuisine, and historic cities such as Paris, Lyon, and Bordeaux. It’s a global hub for art, fashion, and gastronomy.', 'blocks', '[]', 548, 'France', 'rance, located in Western Europe,', 'France tours', 'Europe', 'blog', 0);

-- --------------------------------------------------------

--
-- Table structure for table `blog_post_tags`
--

CREATE TABLE `blog_post_tags` (
  `post_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_tags`
--

CREATE TABLE `blog_tags` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `booking_reference` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tour_id` int(11) NOT NULL,
  `agent_id` int(11) DEFAULT NULL,
  `tour_date` date NOT NULL,
  `adults` int(11) NOT NULL DEFAULT 1,
  `children` int(11) DEFAULT 0,
  `infants` int(11) DEFAULT 0,
  `total_amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `status` enum('pending','confirmed','cancelled','completed','refunded') DEFAULT 'pending',
  `payment_status` enum('pending','partial','paid','refunded') DEFAULT 'pending',
  `special_requests` text DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) DEFAULT NULL,
  `dietary_requirements` text DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `travel_insurance` tinyint(1) DEFAULT 0,
  `insurance_provider` varchar(100) DEFAULT NULL,
  `insurance_policy_number` varchar(100) DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT 0.00,
  `refund_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `group_size` int(11) DEFAULT 1,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `bookings`
--
DELIMITER $$
CREATE TRIGGER `update_availability_after_booking` AFTER INSERT ON `bookings` FOR EACH ROW BEGIN
    CALL UpdateTourAvailability(NEW.tour_id, NEW.tour_date);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_availability_after_booking_update` AFTER UPDATE ON `bookings` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status OR OLD.adults != NEW.adults OR OLD.children != NEW.children THEN
        CALL UpdateTourAvailability(NEW.tour_id, NEW.tour_date);
        IF OLD.tour_id != NEW.tour_id OR OLD.tour_date != NEW.tour_date THEN
            CALL UpdateTourAvailability(OLD.tour_id, OLD.tour_date);
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_customer_ltv_after_booking` AFTER INSERT ON `bookings` FOR EACH ROW BEGIN
    CALL UpdateCustomerLTV(NEW.user_id);
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_customer_ltv_after_booking_update` AFTER UPDATE ON `bookings` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status OR OLD.total_amount != NEW.total_amount THEN
        CALL UpdateCustomerLTV(NEW.user_id);
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `booking_addons`
--

CREATE TABLE `booking_addons` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `addon_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_modifications`
--

CREATE TABLE `booking_modifications` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `modification_type` enum('date_change','tour_change','traveler_change','cancellation') NOT NULL,
  `original_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`original_data`)),
  `new_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`new_data`)),
  `reason` text DEFAULT NULL,
  `fee_amount` decimal(10,2) DEFAULT 0.00,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_by` int(11) NOT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_travelers`
--

CREATE TABLE `booking_travelers` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `type` enum('adult','child','infant') NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `passport_number` varchar(50) DEFAULT NULL,
  `passport_expiry` date DEFAULT NULL,
  `dietary_requirements` text DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `content_pages`
--

CREATE TABLE `content_pages` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `excerpt` text DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `template` varchar(100) DEFAULT 'default',
  `status` enum('draft','published','private') DEFAULT 'draft',
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `seo_keywords` text DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `menu_order` int(11) DEFAULT 0,
  `show_in_menu` tinyint(1) DEFAULT 1,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `content_versions`
--

CREATE TABLE `content_versions` (
  `id` bigint(20) NOT NULL,
  `entity_type` enum('tour','blog_post','page') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `version_number` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_published` tinyint(1) DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `continents`
--

CREATE TABLE `continents` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `continents`
--

INSERT INTO `continents` (`id`, `name`, `created_at`) VALUES
(1, 'Africa', '2025-06-27 03:42:22'),
(2, 'Europe', '2025-06-27 03:42:22'),
(3, 'Asia', '2025-06-27 03:42:22'),
(4, 'North America', '2025-06-27 03:42:22'),
(5, 'South America', '2025-06-27 03:42:22'),
(6, 'Australia', '2025-06-27 03:42:22'),
(7, 'Antarctica', '2025-06-27 03:42:22');

-- --------------------------------------------------------

--
-- Table structure for table `conversion_funnel`
--

CREATE TABLE `conversion_funnel` (
  `id` bigint(20) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `step_name` varchar(100) NOT NULL,
  `step_order` int(11) NOT NULL,
  `tour_id` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(3) NOT NULL,
  `continent` varchar(50) DEFAULT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `language` varchar(100) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT NULL,
  `visa_required` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `travel_advisory` text DEFAULT NULL,
  `best_time_to_visit` text DEFAULT NULL,
  `travel_facts` text DEFAULT NULL,
  `visa_tips` text DEFAULT NULL,
  `flag_image` varchar(255) DEFAULT NULL,
  `gallery_images` text DEFAULT NULL,
  `map_latitude` decimal(10,6) DEFAULT NULL,
  `map_longitude` decimal(10,6) DEFAULT NULL,
  `map_zoom` tinyint(4) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `continent_id` int(11) DEFAULT NULL,
  `region` varchar(50) DEFAULT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `region_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`id`, `name`, `code`, `continent`, `currency`, `language`, `timezone`, `visa_required`, `description`, `travel_advisory`, `best_time_to_visit`, `travel_facts`, `visa_tips`, `flag_image`, `gallery_images`, `map_latitude`, `map_longitude`, `map_zoom`, `status`, `created_at`, `updated_at`, `created_by`, `continent_id`, `region`, `slug`, `region_id`) VALUES
(18, 'Rwanda', '+25', 'Africa', 'RWF', 'Kinyarwanda', 'Africa/Cairo', 1, 'Rwanda, known as the Land of a Thousand Hills, is a lush, mountainous country in East Africa celebrated for its stunning scenery, vibrant culture, and rare wildlife. It\'s one of the few places in the world where you can track endangered mountain gorillas in the wild, while also enjoying clean cities, friendly locals, and a strong commitment to conservation.', 'Safety: Rwanda is one of the safest countries in Africa with a strong police presence\r\n\r\nHealth: Yellow fever vaccination required if arriving from a country with risk of transmission\r\n\r\nEmergency: Dial 112 for police or medical emergencies\r\n\r\nTrekking Permits: Gorilla and chimpanzee trekking require permits booked in advance\r\n\r\nCultural Respect: Modest dress and respectful behavior are appreciated, especially in rural areas', 'Dry Seasons (Best for trekking):\r\n• June – September\r\n• December – February\r\n\r\nThese months offer better trail conditions and clearer views for gorilla trekking and national parks.\r\n\r\nRainy Seasons: March–May and October–November – lush landscapes, but trails can be slippery.', 'Capital: Kigali\r\n\r\nOfficial Language: Kinyarwanda (English and French widely spoken)\r\n\r\nCurrency: Rwandan Franc (RWF)\r\n\r\nTime Zone: Central Africa Time (GMT+2)\r\n\r\nMain Attractions: Volcanoes National Park, Lake Kivu, Akagera National Park, Kigali Genocide Memorial\r\n\r\nClean & Safe: Rwanda is known for its cleanliness, strict anti-littering laws, and low crime rate\r\n\r\nPlastic Ban: Non-biodegradable plastic bags are banned – bring reusable bags', 'Visa on Arrival: Available for all nationalities at ports of entry\r\n\r\nEast Africa Tourist Visa: One visa covers Rwanda, Kenya, and Uganda (ideal for regional travel)\r\n\r\neVisa Option: Apply online before travel via irembo.gov.rw\r\n\r\nRequirements: Valid passport with at least 6 months before expiry, proof of accommodation, return ticket', 'uploads/flags/+25.jpeg', '[\"uploads\\/countries\\/RWF_1751136563_0.jpeg\",\"uploads\\/countries\\/RWF_1751136563_1.jpeg\",\"uploads\\/countries\\/RWF_1751136563_2.jpeg\",\"uploads\\/countries\\/RWF_1751136563_3.jpeg\",\"uploads\\/countries\\/+25_1751293087_0.jpeg\",\"uploads\\/countries\\/+25_1751293087_1.jpeg\",\"uploads\\/countries\\/+25_1751293087_2.jpeg\",\"uploads\\/countries\\/+25_1751293087_3.jpeg\"]', -2.034789, 30.025635, 1, 'active', '2025-06-28 18:49:23', '2025-06-30 14:18:07', 2, NULL, NULL, NULL, NULL),
(19, 'Nigeria', 'NG', 'Africa', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:47:59', '2025-06-29 11:47:59', NULL, 1, NULL, NULL, NULL),
(21, 'Burundi', 'BI', 'Africa', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:47:59', '2025-06-29 11:47:59', NULL, 1, NULL, NULL, NULL),
(22, 'South Africa', 'ZA', 'Africa', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:47:59', '2025-06-29 11:47:59', NULL, 1, NULL, NULL, NULL),
(23, 'Egypt', 'EG', 'Africa', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:47:59', '2025-06-29 11:47:59', NULL, 1, NULL, NULL, NULL),
(24, 'China', 'CN', 'Asia', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:47:59', '2025-06-30 11:53:37', NULL, 3, NULL, NULL, NULL),
(25, 'India', 'IN', 'Asia', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:47:59', '2025-06-30 11:53:37', NULL, 3, NULL, NULL, NULL),
(26, 'Japan', 'JP', 'Asia', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:47:59', '2025-06-30 11:53:37', NULL, 3, NULL, NULL, NULL),
(27, 'Thailand', 'TH', 'Asia', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:47:59', '2025-06-30 11:53:37', NULL, 3, NULL, NULL, NULL),
(28, 'Indonesia', 'ID', 'Asia', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:47:59', '2025-06-30 11:53:37', NULL, 3, NULL, NULL, NULL),
(29, 'France', 'FR', 'Europe', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:47:59', '2025-06-30 11:53:36', NULL, 2, NULL, NULL, NULL),
(30, 'Germany', 'DE', 'Europe', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:47:59', '2025-06-30 11:53:36', NULL, 2, NULL, NULL, NULL),
(31, 'England', '+44', 'Europe', 'GBP', 'English', 'Africa/Accra', 1, 'England is the largest and most populous country in the United Kingdom, known for its rich history, iconic landmarks, charming countryside, and vibrant cities. From the historic streets of London to the literary heritage of Stratford-upon-Avon, the rolling hills of the Cotswolds, and the ancient mystery of Stonehenge, England offers a blend of tradition and modern culture, making it a must-visit destination for travelers of all types.', 'Safety: England is very safe for tourists with good public services and low violent crime rates\r\nHealth: No vaccinations required, but travel insurance is strongly recommended\r\nWeather: Be prepared for sudden rain – always carry a light raincoat or umbrella\r\nEmergency Numbers: Dial 999 or 112 for police, ambulance, or fire services', 'May to September: Best weather, outdoor festivals, and long daylight hours\r\nApril and October: Milder, fewer crowds – great for sightseeing and countryside tours\r\nDecember: Magical atmosphere with Christmas lights and markets in cities like London, Bath, and York\r\nAvoid: January–February if you dislike cold, grey, and wet weather', 'Capital: London\r\nOfficial Language: English\r\nCurrency: British Pound Sterling (£ / GBP)\r\nTime Zone: GMT (UTC+0) / BST (British Summer Time, UTC+1)\r\nTransportation: Extensive public transport including trains (e.g., National Rail), buses, and the London Underground\r\nFamous Destinations: London, Oxford, Cambridge, Bath, Stonehenge, Lake District, York, Windsor\r\nDriving: On the left side of the road', 'Visa-Free Entry: Citizens of many countries (e.g., USA, EU, Canada, Australia) can enter for up to 6 months without a visa\r\nStandard Visitor Visa: Required for citizens of some countries; allows stays up to 6 months\r\neVisa: Coming soon under the UK ETA system (expected for some travelers in late 2025)\r\nTips: Ensure your passport is valid for the full duration of your stay; show proof of accommodation and return ticket if asked', 'uploads/flags/+44.jpeg', '[\"uploads\\/countries\\/+44_1751293657_0.jpeg\",\"uploads\\/countries\\/+44_1751293657_1.jpeg\",\"uploads\\/countries\\/+44_1751293657_2.jpeg\",\"uploads\\/countries\\/+44_1751293657_3.jpeg\"]', 52.355500, 1.174300, 6, 'active', '2025-06-29 11:47:59', '2025-06-30 14:27:37', NULL, 2, NULL, NULL, NULL),
(32, 'Spain', 'ES', 'Europe', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:47:59', '2025-06-30 11:53:36', NULL, 2, NULL, NULL, NULL),
(33, 'Italy', 'IT', 'Europe', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:47:59', '2025-06-30 11:53:36', NULL, 2, NULL, NULL, NULL),
(34, 'United States', 'US', 'North America', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 4, NULL, NULL, NULL),
(35, 'Canada', 'CA', 'North America', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 4, NULL, NULL, NULL),
(36, 'Mexico', 'MX', 'North America', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 4, NULL, NULL, NULL),
(37, 'Jamaica', 'JM', 'North America', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 4, NULL, NULL, NULL),
(38, 'Cuba', 'CU', 'North America', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 4, NULL, NULL, NULL),
(39, 'Brazil', 'BR', 'South America', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 5, NULL, NULL, NULL),
(40, 'Argentina', 'AR', 'South America', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 5, NULL, NULL, NULL),
(41, 'Chile', 'CL', 'South America', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 5, NULL, NULL, NULL),
(42, 'Colombia', 'CO', 'South America', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 5, NULL, NULL, NULL),
(43, 'Peru', 'PE', 'South America', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 5, NULL, NULL, NULL),
(44, 'Australia', 'AU', 'Oceania', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 6, NULL, NULL, NULL),
(45, 'Fiji', 'FJ', 'Oceania', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 6, NULL, NULL, NULL),
(46, 'New Zealand', 'NZ', 'Oceania', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 6, NULL, NULL, NULL),
(47, 'Papua New Guinea', 'PG', 'Oceania', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 6, NULL, NULL, NULL),
(48, 'Samoa', 'WS', 'Oceania', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:00', '2025-06-29 11:48:00', NULL, 6, NULL, NULL, NULL),
(49, 'Antarctica', 'AQ', 'Antarctica', NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-06-29 11:48:01', '2025-06-29 11:48:01', NULL, 7, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `countries2`
--

CREATE TABLE `countries2` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(3) NOT NULL,
  `continent` varchar(50) DEFAULT NULL,
  `continent_id` int(11) DEFAULT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `language` varchar(100) DEFAULT NULL,
  `timezone` varchar(50) DEFAULT NULL,
  `visa_required` tinyint(1) DEFAULT 0,
  `description` text DEFAULT NULL,
  `travel_advisory` text DEFAULT NULL,
  `best_time_to_visit` text DEFAULT NULL,
  `travel_facts` text DEFAULT NULL,
  `visa_tips` text DEFAULT NULL,
  `flag_image` varchar(255) DEFAULT NULL,
  `gallery_images` text DEFAULT NULL,
  `map_latitude` decimal(10,6) DEFAULT NULL,
  `map_longitude` decimal(10,6) DEFAULT NULL,
  `map_zoom` tinyint(4) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `region` varchar(50) DEFAULT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `region_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `countries2`
--

INSERT INTO `countries2` (`id`, `name`, `code`, `continent`, `continent_id`, `currency`, `language`, `timezone`, `visa_required`, `description`, `travel_advisory`, `best_time_to_visit`, `travel_facts`, `visa_tips`, `flag_image`, `gallery_images`, `map_latitude`, `map_longitude`, `map_zoom`, `status`, `created_at`, `updated_at`, `created_by`, `region`, `slug`, `region_id`) VALUES
(435, 'Algeria', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(436, 'Angola', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(437, 'Benin', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(438, 'Botswana', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(439, 'Burkina Faso', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(440, 'Burundi', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(441, 'Cabo Verde', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(442, 'Cameroon', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(443, 'Central African Republic', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(444, 'Chad', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(445, 'Comoros', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(446, 'Congo (Democratic Republic of the)', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(447, 'Congo (Republic of the)', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(448, 'COTE D IVOIRE', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(449, 'Djibouti', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(450, 'Egypt', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(451, 'Equatorial Guinea', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(452, 'Eritrea', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(453, 'Eswatini', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(454, 'Ethiopia', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(455, 'Gabon', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(456, 'Gambia', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(457, 'Ghana', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(458, 'Guinea', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(459, 'Guinea-Bissau', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(460, 'Kenya', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(461, 'Lesotho', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(462, 'Liberia', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(463, 'Libya', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(464, 'Madagascar', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(465, 'Malawi', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(466, 'Mali', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(467, 'Mauritania', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(468, 'Mauritius', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(469, 'Morocco', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(470, 'Mozambique', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(471, 'Namibia', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(472, 'Niger', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(473, 'Nigeria', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(474, 'Rwanda', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(475, ' SAO TOME AND PRINCIPE', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(476, 'Senegal', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(477, 'Seychelles', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(478, 'Sierra Leone', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(479, 'Somalia', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(480, 'South Africa', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(481, 'South Sudan', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(482, 'Sudan', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(483, 'Tanzania', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(484, 'Togo', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(485, 'Tunisia', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(486, 'Uganda', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(487, 'Zambia', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(488, 'Zimbabwe', '', 'Africa', 1, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(489, 'Albania', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(490, 'Andorra', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(491, 'Armenia', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(492, 'Austria', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(493, 'Azerbaijan', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(494, 'Belarus', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(495, 'Belgium', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(496, 'Bosnia and Herzegovina', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(497, 'Bulgaria', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(498, 'Croatia', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(499, 'Cyprus', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(500, 'Czech Republic (Czechia)', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(501, 'Denmark', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(502, 'Estonia', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(503, 'Finland', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(504, 'France', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(505, 'Georgia', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(506, 'Germany', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(507, 'Greece', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(508, 'Hungary', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(509, 'Iceland', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(510, 'Ireland', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(511, 'Italy', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(512, 'Kazakhstan (partly in Europe)', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(513, 'Kosovo', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(514, 'Latvia', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(515, 'Liechtenstein', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(516, 'Lithuania', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(517, 'Luxembourg', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(518, 'Malta', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(519, 'Moldova', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(520, 'Monaco', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(521, 'Montenegro', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(522, 'Netherlands', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(523, 'North Macedonia', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(524, 'Norway', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(525, 'Poland', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(526, 'Portugal', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(527, 'Romania', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(528, 'Russia (partly in Europe)', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(529, 'San Marino', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(530, 'Serbia', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(531, 'Slovakia', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(532, 'Slovenia', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(533, 'Spain', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(534, 'Sweden', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(535, 'Switzerland', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(536, 'Turkey (partly in Europe)', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(537, 'Ukraine', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(538, 'United Kingdom (England, Scotland, Wales, Northern Ireland)', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(539, 'Vatican City', '', 'Europe', 2, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(540, 'Afghanistan', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(541, 'Armenia', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(542, 'Azerbaijan', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(543, 'Bahrain', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(544, 'Bangladesh', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(545, 'Bhutan', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(546, 'Brunei', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(547, 'Cambodia', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(548, 'China', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(549, 'Cyprus', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(550, 'Georgia', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(551, 'India', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(552, 'Indonesia', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(553, 'Iran', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(554, 'Iraq', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(555, 'Israel', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(556, 'Japan', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(557, 'Jordan', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(558, 'Kazakhstan', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(559, 'Kuwait', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(560, 'Kyrgyzstan', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(561, 'Laos', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(562, 'Lebanon', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(563, 'Malaysia', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(564, 'Maldives', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(565, 'Mongolia', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(566, 'Myanmar (Burma)', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(567, 'Nepal', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(568, 'North Korea', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(569, 'Oman', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(570, 'Pakistan', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(571, 'Palestine', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(572, 'Philippines', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(573, 'Qatar', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(574, 'Russia (partly in Asia)', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(575, 'Saudi Arabia', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(576, 'Singapore', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(577, 'South Korea', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(578, 'Sri Lanka', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(579, 'Syria', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(580, 'Taiwan', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(581, 'Tajikistan', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(582, 'Thailand', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(583, 'Timor-Leste', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(584, 'Turkey (partly in Asia)', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(585, 'Turkmenistan', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(586, 'United Arab Emirates', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(587, 'Uzbekistan', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(588, 'Vietnam', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(589, 'Yemen', '', 'Asia', 3, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(590, 'Antigua and Barbuda', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(591, 'Bahamas', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(592, 'Barbados', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(593, 'Belize', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(594, 'Canada', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(595, 'Costa Rica', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(596, 'Cuba', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(597, 'Dominica', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(598, 'Dominican Republic', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(599, 'El Salvador', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(600, 'Grenada', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(601, 'Guatemala', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(602, 'Haiti', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(603, 'Honduras', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(604, 'Jamaica', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(605, 'Mexico', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(606, 'Nicaragua', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(607, 'Panama', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(608, 'Saint Kitts and Nevis', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(609, 'Saint Lucia', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(610, 'Saint Vincent and the Grenadines', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(611, 'Trinidad and Tobago', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(612, 'United States of America', '', 'North America', 4, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(613, 'Argentina', '', 'South America', 5, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(614, 'Bolivia', '', 'South America', 5, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(615, 'Brazil', '', 'South America', 5, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(616, 'Chile', '', 'South America', 5, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(617, 'Colombia', '', 'South America', 5, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(618, 'Ecuador', '', 'South America', 5, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(619, 'Guyana', '', 'South America', 5, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(620, 'Paraguay', '', 'South America', 5, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(621, 'Peru', '', 'South America', 5, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(622, 'Suriname', '', 'South America', 5, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(623, 'Uruguay', '', 'South America', 5, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(624, 'Venezuela', '', 'South America', 5, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(625, 'Australia', '', 'Australia', 6, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(626, 'Fiji', '', 'Australia', 6, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(627, 'Kiribati', '', 'Australia', 6, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(628, 'Marshall Islands', '', 'Australia', 6, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(629, 'Micronesia', '', 'Australia', 6, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(630, 'Nauru', '', 'Australia', 6, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(631, 'New Zealand', '', 'Australia', 6, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(632, 'Palau', '', 'Australia', 6, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(633, 'Papua New Guinea', '', 'Australia', 6, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(634, 'Samoa', '', 'Australia', 6, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(635, 'Solomon Islands', '', 'Australia', 6, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(636, 'Tonga', '', 'Australia', 6, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(637, 'Tuvalu', '', 'Australia', 6, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0),
(638, 'Vanuatu', '', 'Australia', 6, '', '', '', 0, '', '', '', '', '', '', '', 0.000000, 0.000000, 0, '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 2, '', '', 0);

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('percentage','fixed_amount','free_shipping') NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `minimum_amount` decimal(10,2) DEFAULT 0.00,
  `maximum_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `usage_count` int(11) DEFAULT 0,
  `user_limit` int(11) DEFAULT 1,
  `valid_from` timestamp NULL DEFAULT NULL,
  `valid_until` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coupon_usage`
--

CREATE TABLE `coupon_usage` (
  `id` int(11) NOT NULL,
  `coupon_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_id` int(11) DEFAULT NULL,
  `discount_amount` decimal(10,2) NOT NULL,
  `used_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_ltv`
--

CREATE TABLE `customer_ltv` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_bookings` int(11) DEFAULT 0,
  `total_spent` decimal(12,2) DEFAULT 0.00,
  `average_booking_value` decimal(10,2) DEFAULT 0.00,
  `first_booking_date` date DEFAULT NULL,
  `last_booking_date` date DEFAULT NULL,
  `predicted_ltv` decimal(12,2) DEFAULT 0.00,
  `customer_tier` enum('bronze','silver','gold','platinum') DEFAULT 'bronze',
  `last_calculated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_segments`
--

CREATE TABLE `customer_segments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `criteria` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`criteria`)),
  `auto_update` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customer_segment_members`
--

CREATE TABLE `customer_segment_members` (
  `id` bigint(20) NOT NULL,
  `segment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `removed_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_campaigns`
--

CREATE TABLE `email_campaigns` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `template_id` int(11) DEFAULT NULL,
  `recipient_type` enum('all_users','newsletter_subscribers','customers','custom') NOT NULL,
  `recipient_list` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recipient_list`)),
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `status` enum('draft','scheduled','sending','sent','failed') DEFAULT 'draft',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_queue`
--

CREATE TABLE `email_queue` (
  `id` bigint(20) NOT NULL,
  `to_email` varchar(100) NOT NULL,
  `to_name` varchar(100) DEFAULT NULL,
  `from_email` varchar(100) DEFAULT NULL,
  `from_name` varchar(100) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `body_html` longtext DEFAULT NULL,
  `body_text` longtext DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `template_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`template_data`)),
  `priority` enum('low','normal','high') DEFAULT 'normal',
  `status` enum('pending','sending','sent','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 3,
  `error_message` text DEFAULT NULL,
  `scheduled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `body_html` longtext NOT NULL,
  `body_text` longtext DEFAULT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `category` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`id`, `name`, `subject`, `body_html`, `body_text`, `variables`, `category`, `status`, `created_at`, `updated_at`) VALUES
(1, 'booking_confirmation', 'Booking Confirmation - {{booking_reference}}', '<h2>Booking Confirmed!</h2><p>Dear {{customer_name}},</p><p>Your booking has been confirmed.</p><p><strong>Booking Reference:</strong> {{booking_reference}}</p><p><strong>Tour:</strong> {{tour_title}}</p><p><strong>Date:</strong> {{tour_date}}</p><p>Thank you for choosing Forever Young Tours!</p>', NULL, NULL, 'booking', 'active', '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(2, 'payment_received', 'Payment Received - {{booking_reference}}', '<h2>Payment Received</h2><p>Dear {{customer_name}},</p><p>We have received your payment for booking {{booking_reference}}.</p><p><strong>Amount:</strong> {{amount}} {{currency}}</p><p>Thank you for your payment!</p>', NULL, NULL, 'payment', 'active', '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(3, 'booking_cancelled', 'Booking Cancelled - {{booking_reference}}', '<h2>Booking Cancelled</h2><p>Dear {{customer_name}},</p><p>Your booking has been cancelled.</p><p><strong>Booking Reference:</strong> {{booking_reference}}</p><p>If you have any questions, please contact our support team.</p>', NULL, NULL, 'booking', 'active', '2025-06-27 01:38:54', '2025-06-27 01:38:54');

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` int(11) NOT NULL,
  `question` text NOT NULL,
  `answer` text NOT NULL,
  `category` varchar(100) DEFAULT 'general',
  `status` enum('active','inactive') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_bookings`
--

CREATE TABLE `group_bookings` (
  `id` int(11) NOT NULL,
  `group_name` varchar(255) NOT NULL,
  `group_leader_id` int(11) NOT NULL,
  `tour_id` int(11) NOT NULL,
  `tour_date` date NOT NULL,
  `total_travelers` int(11) NOT NULL,
  `group_discount_percentage` decimal(5,2) DEFAULT 0.00,
  `special_requirements` text DEFAULT NULL,
  `status` enum('draft','confirmed','cancelled') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_booking_members`
--

CREATE TABLE `group_booking_members` (
  `id` int(11) NOT NULL,
  `group_booking_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `member_role` enum('leader','member') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `itineraries`
--

CREATE TABLE `itineraries` (
  `id` int(11) NOT NULL,
  `tour_id` int(11) NOT NULL,
  `day_number` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `activities` text DEFAULT NULL,
  `meals_included` varchar(100) DEFAULT NULL,
  `accommodation` varchar(200) DEFAULT NULL,
  `transportation` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mca_agents`
--

CREATE TABLE `mca_agents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `agent_code` varchar(20) NOT NULL,
  `commission_rate` decimal(5,2) DEFAULT 10.00,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `territory` varchar(100) DEFAULT NULL,
  `specialization` text DEFAULT NULL,
  `training_completed` tinyint(1) DEFAULT 0,
  `certification_date` date DEFAULT NULL,
  `performance_rating` decimal(3,2) DEFAULT 0.00,
  `total_sales` decimal(12,2) DEFAULT 0.00,
  `total_commission` decimal(12,2) DEFAULT 0.00,
  `bank_account_name` varchar(100) DEFAULT NULL,
  `bank_account_number` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_swift_code` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mca_commissions`
--

CREATE TABLE `mca_commissions` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `booking_amount` decimal(10,2) NOT NULL,
  `commission_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','paid','cancelled') DEFAULT 'pending',
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_reference` varchar(50) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mca_training_modules`
--

CREATE TABLE `mca_training_modules` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 0,
  `difficulty` enum('beginner','intermediate','advanced') DEFAULT 'beginner',
  `order_index` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `required` tinyint(1) DEFAULT 0,
  `passing_score` int(11) DEFAULT 70,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `mca_training_modules`
--

INSERT INTO `mca_training_modules` (`id`, `title`, `description`, `content`, `video_url`, `duration_minutes`, `difficulty`, `order_index`, `status`, `required`, `passing_score`, `created_at`, `updated_at`) VALUES
(1, 'MCA Program Overview', 'Introduction to the MCA program and how it works', NULL, NULL, 30, 'beginner', 1, 'active', 1, 70, '2025-06-27 03:32:03', '2025-06-27 03:32:03'),
(2, 'Tour Products Knowledge', 'Learn about our tour packages and destinations', NULL, NULL, 45, 'beginner', 2, 'active', 1, 70, '2025-06-27 03:32:03', '2025-06-27 03:32:03'),
(3, 'Sales Techniques', 'Effective sales strategies for travel products', NULL, NULL, 60, 'intermediate', 3, 'active', 1, 70, '2025-06-27 03:32:03', '2025-06-27 03:32:03'),
(4, 'Customer Service Excellence', 'Providing exceptional customer service', NULL, NULL, 40, 'intermediate', 4, 'active', 1, 70, '2025-06-27 03:32:03', '2025-06-27 03:32:03'),
(5, 'Commission Structure', 'Understanding how commissions are calculated and paid', NULL, NULL, 25, 'beginner', 5, 'active', 1, 70, '2025-06-27 03:32:03', '2025-06-27 03:32:03'),
(6, 'Marketing and Promotion', 'How to effectively market and promote tours', NULL, NULL, 50, 'intermediate', 6, 'active', 0, 70, '2025-06-27 03:32:03', '2025-06-27 03:32:03'),
(7, 'Advanced Sales Strategies', 'Advanced techniques for experienced agents', NULL, NULL, 75, 'advanced', 7, 'active', 0, 70, '2025-06-27 03:32:03', '2025-06-27 03:32:03'),
(8, 'MCA Program Overview', 'Introduction to the MCA program and how it works', NULL, NULL, 30, 'beginner', 1, 'active', 1, 70, '2025-06-27 03:43:21', '2025-06-27 03:43:21'),
(9, 'Tour Products Knowledge', 'Learn about our tour packages and destinations', NULL, NULL, 45, 'beginner', 2, 'active', 1, 70, '2025-06-27 03:43:21', '2025-06-27 03:43:21'),
(10, 'Sales Techniques', 'Effective sales strategies for travel products', NULL, NULL, 60, 'intermediate', 3, 'active', 1, 70, '2025-06-27 03:43:21', '2025-06-27 03:43:21'),
(11, 'Customer Service Excellence', 'Providing exceptional customer service', NULL, NULL, 40, 'intermediate', 4, 'active', 1, 70, '2025-06-27 03:43:21', '2025-06-27 03:43:21'),
(12, 'Commission Structure', 'Understanding how commissions are calculated and paid', NULL, NULL, 25, 'beginner', 5, 'active', 1, 70, '2025-06-27 03:43:21', '2025-06-27 03:43:21'),
(13, 'Marketing and Promotion', 'How to effectively market and promote tours', NULL, NULL, 50, 'intermediate', 6, 'active', 0, 70, '2025-06-27 03:43:21', '2025-06-27 03:43:21'),
(14, 'Advanced Sales Strategies', 'Advanced techniques for experienced agents', NULL, NULL, 75, 'advanced', 7, 'active', 0, 70, '2025-06-27 03:43:21', '2025-06-27 03:43:21');

-- --------------------------------------------------------

--
-- Table structure for table `mca_training_progress`
--

CREATE TABLE `mca_training_progress` (
  `id` int(11) NOT NULL,
  `agent_id` int(11) NOT NULL,
  `module_id` int(11) NOT NULL,
  `status` enum('not_started','in_progress','completed','failed') DEFAULT 'not_started',
  `progress_percentage` int(11) DEFAULT 0,
  `score` int(11) DEFAULT NULL,
  `attempts` int(11) DEFAULT 0,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `certificate_url` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `media_files`
--

CREATE TABLE `media_files` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint(20) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_type` enum('image','video','audio','document','other') NOT NULL DEFAULT 'other',
  `dimensions` varchar(20) DEFAULT NULL,
  `alt_text` text DEFAULT NULL,
  `caption` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `status` enum('active','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `media_folders`
--

CREATE TABLE `media_folders` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `path` varchar(500) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `media_library`
--

CREATE TABLE `media_library` (
  `id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `file_type` enum('image','video','audio','document','other') NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `folder` varchar(255) DEFAULT 'uploads',
  `is_public` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mobile_money_transactions`
--

CREATE TABLE `mobile_money_transactions` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `provider` enum('mtn','airtel','tigo') NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `transaction_id` varchar(100) NOT NULL,
  `external_reference` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL,
  `status` enum('pending','completed','failed','expired') DEFAULT 'pending',
  `provider_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`provider_response`)),
  `callback_received` tinyint(1) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `newsletter_subscribers`
--

CREATE TABLE `newsletter_subscribers` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `status` enum('active','unsubscribed','bounced') DEFAULT 'active',
  `source` varchar(100) DEFAULT NULL,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unsubscribed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_queue`
--

CREATE TABLE `notification_queue` (
  `id` bigint(20) NOT NULL,
  `template_id` int(11) NOT NULL,
  `recipient_type` enum('user','admin','agent') NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `recipient_phone` varchar(20) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `content` longtext NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `scheduled_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','sent','failed','cancelled') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 3,
  `error_message` text DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification_templates`
--

CREATE TABLE `notification_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('email','sms','push','in_app') NOT NULL,
  `trigger_event` varchar(100) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `content` longtext NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `send_delay_minutes` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_templates`
--

INSERT INTO `notification_templates` (`id`, `name`, `type`, `trigger_event`, `subject`, `content`, `variables`, `send_delay_minutes`, `status`, `created_at`, `updated_at`) VALUES
(1, 'booking_confirmation', 'email', 'booking_created', 'Booking Confirmation - {{booking_reference}}', 'Dear {{customer_name}}, your booking has been confirmed...', '[\"customer_name\", \"booking_reference\", \"tour_name\", \"tour_date\"]', 0, 'active', '2025-06-27 03:33:13', '2025-06-27 03:33:13'),
(2, 'payment_received', 'email', 'payment_completed', 'Payment Received - {{booking_reference}}', 'Thank you for your payment...', '[\"customer_name\", \"booking_reference\", \"amount\", \"payment_method\"]', 0, 'active', '2025-06-27 03:33:13', '2025-06-27 03:33:13'),
(3, 'tour_reminder', 'email', 'tour_reminder_7_days', 'Your tour is coming up!', 'Your tour {{tour_name}} is scheduled for {{tour_date}}...', '[\"customer_name\", \"tour_name\", \"tour_date\"]', 0, 'active', '2025-06-27 03:33:13', '2025-06-27 03:33:13'),
(4, 'booking_sms', 'sms', 'booking_created', '', 'Your Forever Young Tours booking {{booking_reference}} is confirmed for {{tour_date}}. Thank you!', '[\"booking_reference\", \"tour_date\"]', 0, 'active', '2025-06-27 03:33:13', '2025-06-27 03:33:13');

-- --------------------------------------------------------

--
-- Table structure for table `office_locations`
--

CREATE TABLE `office_locations` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `map_url` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `status` enum('active','inactive') DEFAULT 'active',
  `is_primary` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `office_locations`
--

INSERT INTO `office_locations` (`id`, `name`, `address`, `phone`, `email`, `map_url`, `created_at`, `status`, `is_primary`) VALUES
(1, 'Main Office', '123 Main St, Kigali', '+250123456789', 'info@example.com', 'https://maps.google.com/...', '2025-06-28 22:42:35', 'active', 0);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `order_number` varchar(50) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
  `subtotal` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `shipping_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `billing_first_name` varchar(100) DEFAULT NULL,
  `billing_last_name` varchar(100) DEFAULT NULL,
  `billing_email` varchar(255) DEFAULT NULL,
  `billing_phone` varchar(20) DEFAULT NULL,
  `billing_address_line1` varchar(255) DEFAULT NULL,
  `billing_address_line2` varchar(255) DEFAULT NULL,
  `billing_city` varchar(100) DEFAULT NULL,
  `billing_state` varchar(100) DEFAULT NULL,
  `billing_postal_code` varchar(20) DEFAULT NULL,
  `billing_country` varchar(100) DEFAULT NULL,
  `shipping_first_name` varchar(100) DEFAULT NULL,
  `shipping_last_name` varchar(100) DEFAULT NULL,
  `shipping_address_line1` varchar(255) DEFAULT NULL,
  `shipping_address_line2` varchar(255) DEFAULT NULL,
  `shipping_city` varchar(100) DEFAULT NULL,
  `shipping_state` varchar(100) DEFAULT NULL,
  `shipping_postal_code` varchar(20) DEFAULT NULL,
  `shipping_country` varchar(100) DEFAULT NULL,
  `shipping_method` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `payment_reference` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_name` varchar(200) NOT NULL,
  `product_sku` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `product_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`product_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `seo_title` varchar(255) DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `seo_keywords` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `payment_reference` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `payment_method_id` int(11) NOT NULL,
  `gateway_transaction_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed','cancelled','refunded') DEFAULT 'pending',
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `failure_reason` text DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_intent_id` varchar(255) DEFAULT NULL,
  `refunded_amount` decimal(10,2) DEFAULT 0.00,
  `refund_reason` text DEFAULT NULL,
  `processor_fee` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) GENERATED ALWAYS AS (`amount` - `refunded_amount` - `processor_fee`) STORED,
  `refund_amount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_disputes`
--

CREATE TABLE `payment_disputes` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `dispute_id` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `status` enum('warning_needs_response','warning_under_review','warning_closed','needs_response','under_review','charge_refunded','won','lost') NOT NULL,
  `evidence_due_by` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_gateways`
--

CREATE TABLE `payment_gateways` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `provider` varchar(50) NOT NULL,
  `configuration` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`configuration`)),
  `supported_currencies` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`supported_currencies`)),
  `supported_countries` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`supported_countries`)),
  `min_amount` decimal(10,2) DEFAULT 0.01,
  `max_amount` decimal(10,2) DEFAULT 999999.99,
  `processing_fee_type` enum('fixed','percentage') DEFAULT 'percentage',
  `processing_fee_value` decimal(5,4) DEFAULT 0.0290,
  `status` enum('active','inactive','maintenance') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `logo_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_gateways`
--

INSERT INTO `payment_gateways` (`id`, `name`, `display_name`, `description`, `provider`, `configuration`, `supported_currencies`, `supported_countries`, `min_amount`, `max_amount`, `processing_fee_type`, `processing_fee_value`, `status`, `sort_order`, `logo_url`, `created_at`, `updated_at`) VALUES
(1, 'stripe', 'Stripe', NULL, 'stripe', '{\"publishable_key\": \"\", \"secret_key\": \"\", \"webhook_secret\": \"\"}', '[\"USD\", \"EUR\", \"GBP\", \"RWF\"]', NULL, 0.01, 999999.99, 'percentage', 0.0290, 'active', 0, NULL, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(2, 'paypal', 'PayPal', NULL, 'paypal', '{\"client_id\": \"\", \"client_secret\": \"\", \"mode\": \"sandbox\"}', '[\"USD\", \"EUR\", \"GBP\"]', NULL, 0.01, 999999.99, 'percentage', 0.0290, 'active', 0, NULL, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(3, 'mtn_mobile_money', 'MTN Mobile Money', NULL, 'mtn', '{\"api_key\": \"\", \"subscription_key\": \"\", \"environment\": \"sandbox\"}', '[\"RWF\", \"UGX\"]', NULL, 0.01, 999999.99, 'percentage', 0.0290, 'active', 0, NULL, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(4, 'airtel_money', 'Airtel Money', NULL, 'airtel', '{\"client_id\": \"\", \"client_secret\": \"\", \"environment\": \"sandbox\"}', '[\"RWF\", \"UGX\", \"KES\", \"TZS\"]', NULL, 0.01, 999999.99, 'percentage', 0.0290, 'active', 0, NULL, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(5, 'bank_transfer', 'Bank Transfer', NULL, 'manual', '{\"account_details\": {}}', '[\"USD\", \"RWF\", \"EUR\"]', NULL, 0.01, 999999.99, 'percentage', 0.0290, 'active', 0, NULL, '2025-06-27 01:38:54', '2025-06-27 01:38:54');

-- --------------------------------------------------------

--
-- Table structure for table `payment_gateway_logs`
--

CREATE TABLE `payment_gateway_logs` (
  `id` bigint(20) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `gateway` varchar(50) NOT NULL,
  `transaction_type` enum('charge','refund','capture','void') NOT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `response_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_data`)),
  `status_code` varchar(10) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `processing_time_ms` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `gateway_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `requires_redirect` tinyint(1) DEFAULT 0,
  `requires_verification` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `gateway_id`, `name`, `display_name`, `description`, `icon`, `requires_redirect`, `requires_verification`, `status`, `sort_order`, `created_at`) VALUES
(1, 1, 'credit_card', 'Credit/Debit Card', 'Pay securely with your credit or debit card', 'fas fa-credit-card', 0, 0, 'active', 0, '2025-06-27 01:38:54'),
(2, 2, 'paypal', 'PayPal', 'Pay with your PayPal account', 'fab fa-paypal', 1, 0, 'active', 0, '2025-06-27 01:38:54'),
(3, 3, 'mtn_momo', 'MTN Mobile Money', 'Pay with MTN Mobile Money', 'fas fa-mobile-alt', 0, 0, 'active', 0, '2025-06-27 01:38:54'),
(4, 4, 'airtel_money', 'Airtel Money', 'Pay with Airtel Money', 'fas fa-mobile-alt', 0, 0, 'active', 0, '2025-06-27 01:38:54'),
(5, 5, 'bank_transfer', 'Bank Transfer', 'Direct bank transfer', 'fas fa-university', 0, 0, 'active', 0, '2025-06-27 01:38:54');

-- --------------------------------------------------------

--
-- Table structure for table `payment_refunds`
--

CREATE TABLE `payment_refunds` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `refund_reason` text NOT NULL,
  `refund_reference` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `processed_by` int(11) NOT NULL,
  `processed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` bigint(20) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `transaction_type` enum('charge','refund','capture','void','authorize') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL,
  `gateway_transaction_id` varchar(100) DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `status` enum('pending','success','failed') NOT NULL,
  `error_code` varchar(50) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `processing_time_ms` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_webhooks`
--

CREATE TABLE `payment_webhooks` (
  `id` bigint(20) NOT NULL,
  `gateway_name` varchar(50) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `event_id` varchar(255) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `signature` varchar(500) DEFAULT NULL,
  `processed` tinyint(1) DEFAULT 0,
  `processed_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `display_name` varchar(150) NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `display_name`, `category`, `description`, `created_at`) VALUES
(1, 'dashboard.view', 'View Dashboard', 'dashboard', NULL, '2025-06-27 01:38:53'),
(2, 'users.view', 'View Users', 'users', NULL, '2025-06-27 01:38:53'),
(3, 'users.create', 'Create Users', 'users', NULL, '2025-06-27 01:38:53'),
(4, 'users.edit', 'Edit Users', 'users', NULL, '2025-06-27 01:38:53'),
(5, 'users.delete', 'Delete Users', 'users', NULL, '2025-06-27 01:38:53'),
(6, 'roles.view', 'View Roles', 'users', NULL, '2025-06-27 01:38:53'),
(7, 'roles.create', 'Create Roles', 'users', NULL, '2025-06-27 01:38:53'),
(8, 'roles.edit', 'Edit Roles', 'users', NULL, '2025-06-27 01:38:53'),
(9, 'roles.delete', 'Delete Roles', 'users', NULL, '2025-06-27 01:38:53'),
(10, 'tours.view', 'View Tours', 'tours', NULL, '2025-06-27 01:38:53'),
(11, 'tours.create', 'Create Tours', 'tours', NULL, '2025-06-27 01:38:53'),
(12, 'tours.edit', 'Edit Tours', 'tours', NULL, '2025-06-27 01:38:53'),
(13, 'tours.delete', 'Delete Tours', 'tours', NULL, '2025-06-27 01:38:53'),
(14, 'tours.manage', 'Manage Tour Categories', 'tours', NULL, '2025-06-27 01:38:53'),
(15, 'bookings.view', 'View Bookings', 'bookings', NULL, '2025-06-27 01:38:53'),
(16, 'bookings.create', 'Create Bookings', 'bookings', NULL, '2025-06-27 01:38:53'),
(17, 'bookings.edit', 'Edit Bookings', 'bookings', NULL, '2025-06-27 01:38:53'),
(18, 'bookings.delete', 'Delete Bookings', 'bookings', NULL, '2025-06-27 01:38:53'),
(19, 'bookings.approve', 'Approve Bookings', 'bookings', NULL, '2025-06-27 01:38:53'),
(20, 'payments.view', 'View Payments', 'payments', NULL, '2025-06-27 01:38:53'),
(21, 'payments.process', 'Process Payments', 'payments', NULL, '2025-06-27 01:38:53'),
(22, 'payments.refund', 'Process Refunds', 'payments', NULL, '2025-06-27 01:38:53'),
(23, 'mca.view', 'View MCA Agents', 'mca', NULL, '2025-06-27 01:38:53'),
(24, 'mca.create', 'Create MCA Agents', 'mca', NULL, '2025-06-27 01:38:53'),
(25, 'mca.edit', 'Edit MCA Agents', 'mca', NULL, '2025-06-27 01:38:53'),
(26, 'mca.delete', 'Delete MCA Agents', 'mca', NULL, '2025-06-27 01:38:53'),
(27, 'mca.manage', 'Manage MCA System', 'mca', NULL, '2025-06-27 01:38:53'),
(28, 'settings.view', 'View Settings', 'settings', NULL, '2025-06-27 01:38:53'),
(29, 'settings.edit', 'Edit Settings', 'settings', NULL, '2025-06-27 01:38:53'),
(138, 'users.suspend', 'Suspend Users', 'users', NULL, '2025-06-27 03:42:14'),
(139, 'tours.publish', 'Publish Tours', 'tours', NULL, '2025-06-27 03:42:14'),
(140, 'bookings.confirm', 'Confirm Bookings', 'bookings', NULL, '2025-06-27 03:42:14'),
(141, 'bookings.cancel', 'Cancel Bookings', 'bookings', NULL, '2025-06-27 03:42:14'),
(142, 'destinations.view', 'View Destinations', 'destinations', NULL, '2025-06-27 03:42:14'),
(143, 'destinations.create', 'Create Destinations', 'destinations', NULL, '2025-06-27 03:42:14'),
(144, 'destinations.edit', 'Edit Destinations', 'destinations', NULL, '2025-06-27 03:42:14'),
(145, 'destinations.delete', 'Delete Destinations', 'destinations', NULL, '2025-06-27 03:42:14'),
(146, 'content.view', 'View Content', 'content', NULL, '2025-06-27 03:42:14'),
(147, 'content.create', 'Create Content', 'content', NULL, '2025-06-27 03:42:14'),
(148, 'content.edit', 'Edit Content', 'content', NULL, '2025-06-27 03:42:14'),
(149, 'content.delete', 'Delete Content', 'content', NULL, '2025-06-27 03:42:14'),
(150, 'content.publish', 'Publish Content', 'content', NULL, '2025-06-27 03:42:14'),
(151, 'analytics.view', 'View Analytics', 'analytics', NULL, '2025-06-27 03:42:14'),
(152, 'reports.view', 'View Reports', 'reports', NULL, '2025-06-27 03:42:14'),
(153, 'reports.export', 'Export Reports', 'reports', NULL, '2025-06-27 03:42:14'),
(212, 'media.view', 'Access Media Library', 'Media', NULL, '2025-06-27 09:16:24'),
(213, 'store.view', 'Manage Store', 'E-commerce', NULL, '2025-06-27 09:16:24'),
(214, 'email.view', 'Manage Email Campaigns', 'Email', NULL, '2025-06-27 09:16:24'),
(228, 'blog.view', 'View Blog Posts', 'Blog', 'Allows viewing blog post management', '2025-06-27 11:59:46'),
(230, 'content.pages.view', 'View Pages', 'Content', 'Allows viewing of pages in the CMS', '2025-06-27 20:46:41'),
(231, 'blog.create', '', '', 'Permission to create blog posts', '2025-06-27 21:40:39'),
(233, 'blog.edit_all', 'Edit All Blog Posts', 'Blog', 'Allows the user to edit blog posts created by others', '2025-06-28 00:38:08');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `title` varchar(200) DEFAULT NULL,
  `review` text DEFAULT NULL,
  `verified_purchase` tinyint(1) DEFAULT 0,
  `helpful_votes` int(11) DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` bigint(20) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `identifier_type` enum('ip','user','api_key') NOT NULL,
  `endpoint` varchar(255) NOT NULL,
  `requests_count` int(11) DEFAULT 1,
  `window_start` timestamp NOT NULL DEFAULT current_timestamp(),
  `blocked_until` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refunds`
--

CREATE TABLE `refunds` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `refund_reference` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL,
  `reason` text DEFAULT NULL,
  `gateway_refund_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `regions`
--

CREATE TABLE `regions` (
  `id` int(11) NOT NULL,
  `country_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `climate` varchar(100) DEFAULT NULL,
  `attractions` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `regions`
--

INSERT INTO `regions` (`id`, `country_id`, `name`, `slug`, `description`, `climate`, `attractions`, `status`, `created_at`, `updated_at`) VALUES
(8, 504, 'Provence-Alpes-Côte d’Azur (PACA)', '', 'Located in southeastern France, Provence-Alpes-Côte d’Azur is a diverse and picturesque region known for its lavender fields, Mediterranean coastline, charming hilltop villages, and the dramatic French Alps. It offers a perfect blend of culture, nature, and luxury, making it one of France’s top tourist destinations.', 'Provence-Alpes-Côte d’Azur enjoys a Mediterranean climate along the coast and alpine conditions in t', 'rench Riviera (Côte d’Azur)\r\nNice – Beautiful beaches, art museums, and old town charm\r\n\r\nCannes – Famous for the Film Festival and luxury lifestyle\r\n\r\nMonaco – Glamour, casinos, and coastal beauty (though technically a microstate)\r\n\r\n🌾 Provence\r\nLavender Fields of Valensole – Peak bloom in July\r\n\r\nGorges du Verdon – Europe’s “Grand Canyon” for kayaking and hiking\r\n\r\nAvignon – Historic Papal Palace and medieval bridge\r\n\r\nAix-en-Provence – Elegant town with markets, art, and fountains\r\n\r\n🏔️ Alps & Nature\r\nÉcrins National Park – Alpine trails, lakes, and wildlife\r\n\r\nSki Resorts – Serre Chevalier, Vars, and more for winter sports\r\n\r\n🏛️ Historic Sites\r\nRoman amphitheaters in Arles and Nîmes\r\n\r\nAbbaye de Sénanque – Stunning abbey amid lavender fields\r\n\r\nLet me know if you\'d like a printable version or itinerary ideas based on this region!\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\nAsk ChatGPT', 'active', '2025-06-29 17:26:40', '2025-06-29 17:29:16'),
(10, 31, 'The Cotswolds', '', 'The Cotswolds is a picturesque rural region in south-central England, famous for its rolling hills, charming stone villages, historic market towns, and scenic countryside. Designated as an Area of Outstanding Natural Beauty (AONB), it’s one of England’s most idyllic destinations and a popular retreat for both locals and tourists.', 'Region: South West and West Midlands of England\r\n\r\nCovers parts of: Gloucestershire, Oxfordshire, Wi', 'Bourton-on-the-Water – Often called the “Venice of the Cotswolds”\r\n\r\nBibury – Famous for Arlington Row cottages\r\nStow-on-the-Wold and Chipping Campden – Historic market towns\r\nSudeley Castle, Blenheim Palace, and other heritage sites\r\nCotswold Way – A long-distance walking trail with stunning views', 'active', '2025-06-30 11:55:12', '2025-06-30 11:55:12');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `report_type` enum('revenue_summary','booking_analysis','customer_insights','tour_performance','geographic_analysis','payment_analysis') NOT NULL,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parameters`)),
  `file_path` varchar(500) DEFAULT NULL,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `name`, `report_type`, `parameters`, `file_path`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Revenue Summary Report', 'revenue_summary', '{\"date_from\":\"2025-05-28\",\"date_to\":\"2025-06-27\",\"format\":\"html\"}', NULL, 'completed', 2, '2025-06-27 17:57:12', '2025-06-27 17:57:12'),
(2, 'Revenue Summary Report', 'revenue_summary', '{\"date_from\":\"2025-05-28\",\"date_to\":\"2025-06-27\",\"format\":\"html\"}', NULL, 'completed', 2, '2025-06-27 17:57:19', '2025-06-27 17:57:19'),
(3, 'Revenue Summary Report', 'revenue_summary', '{\"date_from\":\"2025-05-28\",\"date_to\":\"2025-06-27\",\"format\":\"html\"}', NULL, 'completed', 2, '2025-06-27 17:57:19', '2025-06-27 17:57:19');

-- --------------------------------------------------------

--
-- Table structure for table `report_executions`
--

CREATE TABLE `report_executions` (
  `id` bigint(20) NOT NULL,
  `report_id` int(11) NOT NULL,
  `executed_by` int(11) DEFAULT NULL,
  `execution_time_ms` int(11) DEFAULT NULL,
  `row_count` int(11) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `status` enum('running','completed','failed') DEFAULT 'running',
  `error_message` text DEFAULT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `revenue_summary`
-- (See below for the actual view)
--
CREATE TABLE `revenue_summary` (
`booking_date` date
,`total_bookings` bigint(21)
,`total_revenue` decimal(32,2)
,`average_booking_value` decimal(14,6)
,`confirmed_bookings` bigint(21)
,`cancelled_bookings` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `tour_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `permissions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `display_name`, `description`, `status`, `created_at`, `updated_at`, `permissions`) VALUES
(1, 'super_admin', 'Super Administrator', 'Full system access with all permissions', 'active', '2025-06-27 01:38:53', '2025-06-27 01:38:53', NULL),
(2, 'admin', 'Administrator', 'Administrative access to most system features', 'active', '2025-06-27 01:38:53', '2025-06-27 01:38:53', NULL),
(3, 'content_manager', 'Content Manager', 'Manage tours, blog posts, and media content', 'active', '2025-06-27 01:38:53', '2025-06-27 01:38:53', NULL),
(4, 'booking_agent', 'Booking Agent', 'Handle customer bookings and reservations', 'active', '2025-06-27 01:38:53', '2025-06-27 01:38:53', NULL),
(5, 'mca_agent', 'MCA Agent', 'Multi-Country Agent with commission tracking', 'active', '2025-06-27 01:38:53', '2025-06-27 01:38:53', NULL),
(6, 'client', 'Client', 'Regular customer with booking capabilities', 'active', '2025-06-27 01:38:53', '2025-06-27 01:38:53', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
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
(1, 22),
(1, 23),
(1, 24),
(1, 25),
(1, 26),
(1, 27),
(1, 28),
(1, 29),
(1, 138),
(1, 139),
(1, 140),
(1, 141),
(1, 142),
(1, 143),
(1, 144),
(1, 145),
(1, 146),
(1, 147),
(1, 148),
(1, 149),
(1, 150),
(1, 151),
(1, 152),
(1, 153),
(1, 212),
(1, 213),
(1, 214),
(1, 233),
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
(2, 13),
(2, 14),
(2, 15),
(2, 16),
(2, 17),
(2, 18),
(2, 19),
(2, 20),
(2, 21),
(2, 22),
(2, 23),
(2, 24),
(2, 25),
(2, 26),
(2, 27),
(2, 142),
(2, 143),
(2, 144),
(2, 145),
(2, 150),
(2, 151),
(2, 152),
(2, 153),
(2, 228),
(2, 230),
(2, 231),
(2, 233),
(3, 1),
(3, 10),
(3, 11),
(3, 12),
(3, 13),
(3, 14),
(3, 15),
(3, 139),
(3, 142),
(3, 143),
(3, 144),
(3, 146),
(3, 147),
(3, 148),
(3, 150),
(3, 151),
(4, 1),
(4, 10),
(4, 11),
(4, 12),
(4, 14),
(4, 15),
(4, 16),
(4, 17),
(4, 19),
(4, 20),
(4, 21),
(4, 22),
(4, 140),
(4, 141),
(4, 142),
(5, 1),
(5, 10),
(5, 15),
(5, 16),
(5, 23),
(5, 142),
(5, 151),
(6, 1),
(6, 10),
(6, 15),
(6, 16),
(6, 142);

-- --------------------------------------------------------

--
-- Table structure for table `saved_reports`
--

CREATE TABLE `saved_reports` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `report_type` varchar(100) NOT NULL,
  `parameters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`parameters`)),
  `sql_query` longtext DEFAULT NULL,
  `schedule_frequency` enum('none','daily','weekly','monthly') DEFAULT 'none',
  `schedule_time` time DEFAULT NULL,
  `last_run_at` timestamp NULL DEFAULT NULL,
  `next_run_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `shared_with` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shared_with`)),
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seasonal_pricing`
--

CREATE TABLE `seasonal_pricing` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `markup_percentage` decimal(5,2) NOT NULL,
  `applies_to` enum('all_tours','specific_tours','categories') DEFAULT 'all_tours',
  `tour_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tour_ids`)),
  `category_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`category_ids`)),
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seasonal_pricing`
--

INSERT INTO `seasonal_pricing` (`id`, `name`, `description`, `start_date`, `end_date`, `markup_percentage`, `applies_to`, `tour_ids`, `category_ids`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Peak Season', 'High season pricing for December-January', '2024-12-15', '2025-01-15', 25.00, 'all_tours', NULL, NULL, 'active', '2025-06-27 03:33:13', '2025-06-27 03:33:13'),
(2, 'Easter Holiday', 'Easter holiday premium', '2024-03-29', '2024-04-08', 15.00, 'all_tours', NULL, NULL, 'active', '2025-06-27 03:33:13', '2025-06-27 03:33:13'),
(3, 'Summer Premium', 'Summer season markup', '2024-06-01', '2024-08-31', 20.00, 'all_tours', NULL, NULL, 'active', '2025-06-27 03:33:13', '2025-06-27 03:33:13');

-- --------------------------------------------------------

--
-- Table structure for table `security_events`
--

CREATE TABLE `security_events` (
  `id` bigint(20) NOT NULL,
  `event_type` enum('login_success','login_failed','password_change','permission_change','data_access','suspicious_activity') NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `risk_score` int(11) DEFAULT 0,
  `blocked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seo_metadata`
--

CREATE TABLE `seo_metadata` (
  `id` int(11) NOT NULL,
  `entity_type` enum('tour','blog_post','page','category') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` text DEFAULT NULL,
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` text DEFAULT NULL,
  `og_image` varchar(500) DEFAULT NULL,
  `twitter_title` varchar(255) DEFAULT NULL,
  `twitter_description` text DEFAULT NULL,
  `twitter_image` varchar(500) DEFAULT NULL,
  `canonical_url` varchar(500) DEFAULT NULL,
  `robots` varchar(100) DEFAULT 'index,follow',
  `schema_markup` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schema_markup`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shopping_cart`
--

CREATE TABLE `shopping_cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_messages`
--

CREATE TABLE `sms_messages` (
  `id` bigint(20) NOT NULL,
  `notification_id` bigint(20) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `provider` varchar(50) NOT NULL,
  `provider_message_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','sent','delivered','failed') DEFAULT 'pending',
  `cost` decimal(6,4) DEFAULT 0.0000,
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_categories`
--

CREATE TABLE `store_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_orders`
--

CREATE TABLE `store_orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled','refunded') DEFAULT 'pending',
  `total_amount` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `shipping_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'USD',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `shipping_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shipping_address`)),
  `billing_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`billing_address`)),
  `notes` text DEFAULT NULL,
  `shipped_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_order_items`
--

CREATE TABLE `store_order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `product_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`product_snapshot`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_products`
--

CREATE TABLE `store_products` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `short_description` text DEFAULT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `sale_price` decimal(10,2) DEFAULT NULL,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `manage_stock` tinyint(1) DEFAULT 1,
  `stock_status` enum('in_stock','out_of_stock','on_backorder') DEFAULT 'in_stock',
  `weight` decimal(8,2) DEFAULT NULL,
  `dimensions` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `gallery` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gallery`)),
  `status` enum('draft','active','inactive') DEFAULT 'draft',
  `featured` tinyint(1) DEFAULT 0,
  `rating_average` decimal(3,2) DEFAULT 0.00,
  `rating_count` int(11) DEFAULT 0,
  `sales_count` int(11) DEFAULT 0,
  `seo_title` varchar(200) DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_categories`
--

CREATE TABLE `support_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(7) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_categories`
--

INSERT INTO `support_categories` (`id`, `name`, `description`, `color`, `sort_order`, `status`, `created_at`) VALUES
(1, 'Booking Issues', 'Problems with bookings and reservations', '#E74C3C', 0, 'active', '2025-06-27 03:34:34'),
(2, 'Payment Problems', 'Payment and billing related issues', '#F39C12', 0, 'active', '2025-06-27 03:34:34'),
(3, 'Tour Information', 'Questions about tour details and itineraries', '#3498DB', 0, 'active', '2025-06-27 03:34:34'),
(4, 'Technical Support', 'Website and technical issues', '#9B59B6', 0, 'active', '2025-06-27 03:34:34'),
(5, 'General Inquiry', 'General questions and information requests', '#27AE60', 0, 'active', '2025-06-27 03:34:34'),
(6, 'Booking Issues', 'Problems with bookings and reservations', '#E74C3C', 0, 'active', '2025-06-27 03:35:15'),
(7, 'Payment Problems', 'Payment and billing related issues', '#F39C12', 0, 'active', '2025-06-27 03:35:15'),
(8, 'Tour Information', 'Questions about tour details and itineraries', '#3498DB', 0, 'active', '2025-06-27 03:35:15'),
(9, 'Technical Support', 'Website and technical issues', '#9B59B6', 0, 'active', '2025-06-27 03:35:15'),
(10, 'General Inquiry', 'General questions and information requests', '#27AE60', 0, 'active', '2025-06-27 03:35:15');

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `ticket_number` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `category` varchar(100) DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_ticket_messages`
--

CREATE TABLE `support_ticket_messages` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_internal` tinyint(1) DEFAULT 0,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_ticket_replies`
--

CREATE TABLE `support_ticket_replies` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `is_internal` tinyint(1) DEFAULT 0,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` longtext DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json','text') DEFAULT 'string',
  `category` varchar(50) DEFAULT 'general',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `category`, `description`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'Forever Young Tours', 'string', 'general', 'Website name', 1, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(2, 'site_description', 'Discover amazing tours and adventures', 'text', 'general', 'Website description', 1, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(3, 'contact_email', 'info@foreveryoungtours.com', 'string', 'general', 'Main contact email', 1, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(4, 'contact_phone', '+250 123 456 789', 'string', 'general', 'Main contact phone', 1, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(5, 'default_currency', 'USD', 'string', 'general', 'Default currency code', 1, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(6, 'booking_confirmation_required', 'true', 'boolean', 'booking', 'Require admin confirmation for bookings', 0, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(7, 'max_booking_days_advance', '365', 'number', 'booking', 'Maximum days in advance for bookings', 0, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(8, 'commission_rate_default', '10.00', 'number', 'mca', 'Default commission rate for MCA agents', 0, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(9, 'payment_timeout_minutes', '30', 'number', 'payment', 'Payment timeout in minutes', 0, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(10, 'stripe_publishable_key', '', 'string', 'payment', 'Stripe publishable key', 0, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(11, 'stripe_secret_key', '', 'string', 'payment', 'Stripe secret key', 0, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(12, 'paypal_client_id', '', 'string', 'payment', 'PayPal client ID', 0, '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(13, 'paypal_client_secret', '', 'string', 'payment', 'PayPal client secret', 0, '2025-06-27 01:38:54', '2025-06-27 01:38:54');

-- --------------------------------------------------------

--
-- Table structure for table `tours`
--

CREATE TABLE `tours` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `short_description` text DEFAULT NULL,
  `full_description` longtext DEFAULT NULL,
  `country_id` int(11) NOT NULL,
  `region_id` int(11) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `duration_nights` int(11) DEFAULT 0,
  `min_group_size` int(11) DEFAULT 1,
  `max_group_size` int(11) DEFAULT 20,
  `difficulty_level` enum('easy','moderate','challenging','extreme') DEFAULT 'moderate',
  `price_adult` decimal(10,2) NOT NULL,
  `price_child` decimal(10,2) DEFAULT NULL,
  `price_infant` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `includes` text DEFAULT NULL,
  `excludes` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `cancellation_policy` text DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `gallery` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gallery`)),
  `video_url` varchar(255) DEFAULT NULL,
  `brochure_pdf` varchar(255) DEFAULT NULL,
  `status` enum('draft','active','inactive') DEFAULT 'draft',
  `featured` tinyint(1) DEFAULT 0,
  `rating_average` decimal(3,2) DEFAULT 0.00,
  `rating_count` int(11) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `booking_count` int(11) DEFAULT 0,
  `seo_title` varchar(200) DEFAULT NULL,
  `seo_description` text DEFAULT NULL,
  `seo_keywords` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) NOT NULL,
  `average_rating` decimal(3,2) DEFAULT 0.00,
  `review_count` int(11) DEFAULT 0,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `max_capacity` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tours`
--

INSERT INTO `tours` (`id`, `title`, `slug`, `short_description`, `full_description`, `country_id`, `region_id`, `category_id`, `duration_days`, `duration_nights`, `min_group_size`, `max_group_size`, `difficulty_level`, `price_adult`, `price_child`, `price_infant`, `currency`, `includes`, `excludes`, `requirements`, `cancellation_policy`, `featured_image`, `gallery`, `video_url`, `brochure_pdf`, `status`, `featured`, `rating_average`, `rating_count`, `view_count`, `booking_count`, `seo_title`, `seo_description`, `seo_keywords`, `created_at`, `updated_at`, `created_by`, `average_rating`, `review_count`, `is_featured`, `max_capacity`) VALUES
(3, 'Visit Rwanda', 'visit-rwanda', 'Rwanda, known as the Land of a Thousand Hills, is a lush, mountainous country in East Africa celebrated for its stunning scenery, vibrant culture, and rare wildlife. It\'s one of the few places in the world where you can track endangered mountain gorillas in the wild, while also enjoying clean cities, friendly locals, and a strong commitment to conservation.', '<ul>\r\n	<li>\r\n	<p><strong>Capital</strong>: Kigali</p>\r\n	</li>\r\n	<li>\r\n	<p><strong>Official Language</strong>: Kinyarwanda (English and French widely spoken)</p>\r\n	</li>\r\n	<li>\r\n	<p><strong>Currency</strong>: Rwandan Franc (RWF)</p>\r\n	</li>\r\n	<li>\r\n	<p><strong>Time Zone</strong>: Central Africa Time (GMT+2)</p>\r\n	</li>\r\n	<li>\r\n	<p><strong>Main Attractions</strong>: Volcanoes National Park, Lake Kivu, Akagera National Park, Kigali Genocide Memorial</p>\r\n	</li>\r\n	<li>\r\n	<p><strong>Clean &amp; Safe</strong>: Rwanda is known for its cleanliness, strict anti-littering laws, and low crime rate</p>\r\n	</li>\r\n	<li>\r\n	<p><strong>Plastic Ban</strong>: Non-biodegradable plastic bags are banned &ndash; bring reusable bags<img alt=\"Visit Kigali\" src=\"https://i.pinimg.com/736x/17/de/69/17de69065175c6dc23040268fa1ecb80.jpg\" style=\"float:left; height:525px; width:700px\" /></p>\r\n	</li>\r\n</ul>', 18, NULL, 1, 1, 3, 3, 5, 'moderate', 150.00, 150.00, 160.00, 'RWF', '• Accommodation in comfortable lodges or eco-camps\r\n• Daily meals (breakfast, lunch, and dinner during trekking days)\r\n• Private transportation to and from Volcanoes National Park\r\n• Gorilla trekking permit (mandatory and pre-booked)\r\n• Professional English-speaking guide and park ranger services\r\n• Bottled water during trekking days\r\n• Cultural village visit (optional, where included in itinerary)', '• International flights to and from Rwanda\r\n• Travel and health insurance (strongly recommended)\r\n• Visa fees (if applicable)\r\n• Personal expenses (snacks, souvenirs, tips)\r\n• Porter fees (optional support for carrying gear)\r\n• Optional activities not listed in the main itinerary', '• Valid passport (must be valid for at least 6 months beyond travel date)\r\n• Moderate physical fitness (trekking can involve steep, muddy terrain for 2–6 hours)\r\n• Minimum age: 15 years (required for gorilla trekking by Rwandan law)\r\n• Waterproof hiking boots, gloves, long pants/sleeves, and insect repellent are essential\r\n• COVID-19 and yellow fever vaccination may be required based on origin and current health guidelines', '• Gorilla permits are non-refundable and non-transferable once issued by Rwandan authorities\r\n• Tour cancellations more than 30 days before departure: 50% refund (excluding permit cost)\r\n• Cancellations within 30 days: No refund\r\n• If travel is canceled due to medical or government travel restrictions, flexible rebooking may be offered (documentation required)', 'uploads/tours/68603b0d66e3a.jpeg', '[\"uploads\\/tours\\/gallery\\/68603b0d6a855.jpeg\",\"uploads\\/tours\\/gallery\\/68603b0d6aefc.jpeg\"]', NULL, NULL, 'active', 0, 0.00, 0, 15, 0, NULL, NULL, NULL, '2025-06-28 18:57:17', '2025-07-01 07:51:15', 2, 0.00, 0, 0, 0),
(4, '🇬🇧 \"Classic England Highlights\" – 8-Day Guided Tour', 'classic-england-highlights-8-day-guided-tour', 'Experience the best of England on this 8-day journey through London, Oxford, the Cotswolds, Bath, and Stonehenge — a perfect mix of culture, countryside, and history.', '<p><strong>England</strong> is the largest and most populous country in the United Kingdom, known for its rich history, iconic landmarks, charming countryside, and vibrant cities. From the historic streets of <strong>London</strong> to the literary heritage of <strong>Stratford-upon-Avon</strong>, the rolling hills of the <strong>Cotswolds</strong>, and the ancient mystery of <strong>Stonehenge</strong>, England offers a blend of tradition and modern culture, making it a must-visit destination for travelers of all types.<br />\r\n<br />\r\n<img alt=\"England Travel Overview\" src=\"https://i.pinimg.com/736x/13/4b/d1/134bd1a4ff74c420d4e713c69198c82b.jpg\" style=\"height:490px; width:736px\" /><br />\r\n<br />\r\n<strong>England</strong> is the largest and most populous country in the United Kingdom, known for its rich history, iconic landmarks, charming countryside, and vibrant cities. From the historic streets of <strong>London</strong> to the literary heritage of <strong>Stratford-upon-Avon</strong>, the rolling hills of the <strong>Cotswolds</strong>, and the ancient mystery of <strong>Stonehenge</strong>, England offers a blend of tradition and modern culture, making it a must-visit destination for travelers of all types.</p>', 31, 10, 1, 1, 7, 1, 5, 'challenging', 200.00, 200.00, 2000.00, 'USD', '7 nights in 3–4 star hotels or inns\r\n• Daily breakfast and select dinners\r\n• Private transportation between cities and sites\r\n• Professional English-speaking tour guide\r\n• Entry fees to major attractions (e.g., Roman Baths, Stonehenge)\r\n• City tours and walking experiences', '• International flights\r\n• Personal expenses, tips, and additional meals\r\n• Travel insurance\r\n• Optional activities (e.g., West End show, river cruise)', '• Valid passport\r\n• Moderate walking ability (some cobblestone streets and stairs)\r\n• No visa needed for most Western countries for short stays', '• Free cancellation up to 30 days before departure\r\n• 50% refund for cancellations 15–29 days before\r\n• No refund within 14 days of departure', 'uploads/tours/686293108a6a5.jpeg', '[\"uploads\\/tours\\/gallery\\/6862931098595.jpeg\",\"uploads\\/tours\\/gallery\\/686293109a3b1.jpeg\"]', NULL, NULL, 'active', 0, 0.00, 0, 3, 0, NULL, NULL, NULL, '2025-06-30 13:37:20', '2025-07-01 07:53:40', 2, 0.00, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `tour_addons`
--

CREATE TABLE `tour_addons` (
  `id` int(11) NOT NULL,
  `tour_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `max_quantity` int(11) DEFAULT 1,
  `required` tinyint(1) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tour_availability`
--

CREATE TABLE `tour_availability` (
  `id` int(11) NOT NULL,
  `tour_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `available_spots` int(11) NOT NULL,
  `booked_spots` int(11) DEFAULT 0,
  `price_adult` decimal(10,2) DEFAULT NULL,
  `price_child` decimal(10,2) DEFAULT NULL,
  `price_infant` decimal(10,2) DEFAULT NULL,
  `status` enum('available','limited','sold_out','cancelled') DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tour_categories`
--

CREATE TABLE `tour_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(7) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tour_categories`
--

INSERT INTO `tour_categories` (`id`, `name`, `slug`, `description`, `icon`, `color`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Adventure Tours', 'adventure-tours', 'Thrilling outdoor adventures and extreme sports', 'fas fa-mountain', '#E67E22', 0, 'active', '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(2, 'Cultural Tours', 'cultural-tours', 'Explore local culture, traditions, and heritage', 'fas fa-landmark', '#9B59B6', 0, 'active', '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(3, 'Wildlife Safari', 'wildlife-safari', 'Amazing wildlife viewing and safari experiences', 'fas fa-paw', '#27AE60', 0, 'active', '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(4, 'City Tours', 'city-tours', 'Urban exploration and city sightseeing', 'fas fa-city', '#3498DB', 0, 'active', '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(5, 'Beach & Islands', 'beach-islands', 'Tropical beaches and island getaways', 'fas fa-umbrella-beach', '#1ABC9C', 0, 'active', '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(6, 'Mountain Trekking', 'mountain-trekking', 'Hiking and trekking in mountain regions', 'fas fa-hiking', '#8E44AD', 0, 'active', '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(7, 'Photography Tours', 'photography-tours', 'Specialized tours for photography enthusiasts', 'fas fa-camera', '#F39C12', 0, 'active', '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(8, 'Family Tours', 'family-tours', 'Family-friendly tours and activities', 'fas fa-users', '#E74C3C', 0, 'active', '2025-06-27 01:38:54', '2025-06-27 01:38:54'),
(9, 'Beach & Island', 'beach-island', 'Relaxing beach holidays and island getaways', 'fas fa-umbrella-beach', '#1abc9c', 0, 'active', '2025-06-27 03:33:44', '2025-06-27 03:33:44'),
(10, 'Food & Wine', 'food-wine', 'Culinary experiences and wine tasting tours', 'fas fa-wine-glass-alt', '#ff9800', 0, 'active', '2025-06-27 03:33:44', '2025-06-27 03:33:44');

-- --------------------------------------------------------

--
-- Table structure for table `tour_inventory`
--

CREATE TABLE `tour_inventory` (
  `id` int(11) NOT NULL,
  `tour_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `available_spots` int(11) NOT NULL,
  `booked_spots` int(11) DEFAULT 0,
  `price_adult` decimal(10,2) DEFAULT NULL,
  `price_child` decimal(10,2) DEFAULT NULL,
  `price_infant` decimal(10,2) DEFAULT NULL,
  `status` enum('available','limited','sold_out','cancelled') DEFAULT 'available',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tour_itinerary`
--

CREATE TABLE `tour_itinerary` (
  `id` int(11) NOT NULL,
  `tour_id` int(11) NOT NULL,
  `day_number` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `activities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`activities`)),
  `meals_included` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meals_included`)),
  `accommodation` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `tour_performance`
-- (See below for the actual view)
--
CREATE TABLE `tour_performance` (
`id` int(11)
,`title` varchar(200)
,`price_adult` decimal(10,2)
,`total_bookings` bigint(21)
,`total_revenue` decimal(32,2)
,`average_booking_value` decimal(14,6)
,`confirmed_bookings` bigint(21)
,`conversion_rate` decimal(28,5)
);

-- --------------------------------------------------------

--
-- Table structure for table `tour_reviews`
--

CREATE TABLE `tour_reviews` (
  `id` int(11) NOT NULL,
  `tour_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `title` varchar(255) DEFAULT NULL,
  `review` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tour_waitlist`
--

CREATE TABLE `tour_waitlist` (
  `id` int(11) NOT NULL,
  `tour_id` int(11) NOT NULL,
  `tour_date` date NOT NULL,
  `user_id` int(11) NOT NULL,
  `travelers` int(11) DEFAULT 1,
  `priority_score` int(11) DEFAULT 0,
  `notification_sent` tinyint(1) DEFAULT 0,
  `expires_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','notified','converted','expired','cancelled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `nationality` varchar(50) DEFAULT NULL,
  `passport_number` varchar(50) DEFAULT NULL,
  `passport_expiry` date DEFAULT NULL,
  `emergency_contact_name` varchar(100) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `password_reset_token` varchar(255) DEFAULT NULL,
  `password_reset_expires` timestamp NULL DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `billing_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`billing_address`)),
  `shipping_address` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`shipping_address`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `password_hash`, `first_name`, `last_name`, `phone`, `date_of_birth`, `gender`, `nationality`, `passport_number`, `passport_expiry`, `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_relationship`, `role_id`, `status`, `email_verified`, `email_verification_token`, `password_reset_token`, `password_reset_expires`, `last_login`, `login_attempts`, `locked_until`, `profile_image`, `preferences`, `billing_address`, `shipping_address`, `created_at`, `updated_at`, `created_by`, `updated_by`, `remember_token`) VALUES
(2, 'admin', 'admin@iforeveryoungtours.com', '$2y$10$0ElP2pJQ7RBmAz1BFNGsV.dnC/4xO6T7Vt7hLV7BdZjPb9VXU9JvK', '$2y$10$S.3yPR.5UVGhrUu5h8LVEuHTmyZa7IEygQdgJFyHtvYq/HdDGYYDe', 'Admin', 'User', '+250788712679', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, 'active', 0, NULL, NULL, NULL, '2025-07-01 07:50:05', 0, NULL, NULL, NULL, NULL, NULL, '2025-06-27 02:22:15', '2025-07-01 07:50:05', NULL, NULL, 'fdfb0467ba2617b63ba507682fe0721d76b664781627d28e3b73c8c0d74a76b4'),
(11, 'newuser', 'newuser@gmail.com', '', '$2y$10$SP92upR9USTiQKP/cQ9C3u7hoYv7/Y.4Xo/QUtz/J.U44YuK.1cxO', 'new', 'user', '0787654321', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 6, 'active', 0, 'd9029df10561813c13d4903862b5a81855c09b178dad4038f6034246d116bfe8', NULL, NULL, '2025-07-01 12:55:17', 0, NULL, NULL, NULL, NULL, NULL, '2025-06-30 01:43:36', '2025-07-01 12:55:17', NULL, NULL, '7c1ad256ef070573e86f64b9a4dac160b7c4aa0c627f14f4d93ab239a2efbd18');

-- --------------------------------------------------------

--
-- Table structure for table `user_activity`
--

CREATE TABLE `user_activity` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `activity_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_activity`
--

INSERT INTO `user_activity` (`id`, `user_id`, `activity_type`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 2, 'profile_update', 'Updated user profile for Admin User', '::1', NULL, '2025-06-27 19:11:25');

-- --------------------------------------------------------

--
-- Table structure for table `user_activity_logs`
--

CREATE TABLE `user_activity_logs` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_activity_logs`
--

INSERT INTO `user_activity_logs` (`id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `metadata`, `created_at`) VALUES
(2, NULL, 'failed_login', 'Failed login attempt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 02:04:29'),
(3, NULL, 'failed_login', 'Failed login attempt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 02:04:31'),
(4, NULL, 'failed_login', 'Failed login attempt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 02:04:32'),
(5, 2, 'failed_login', 'Failed login attempt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 02:25:24'),
(6, 2, 'failed_login', 'Failed login attempt', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 02:33:05'),
(7, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 02:37:36'),
(8, NULL, 'register', 'User registered', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 03:46:29'),
(9, NULL, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 03:46:53'),
(10, NULL, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 04:00:23'),
(11, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 04:00:38'),
(12, 2, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 09:21:52'),
(13, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 09:22:04'),
(14, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Edg/137.0.0.0', NULL, '2025-06-27 09:43:41'),
(15, 2, 'region_created', 'Created region: AFRICA', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 10:45:25'),
(16, 2, 'tour_created', 'Created new tour: Rwanda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 10:47:27'),
(17, 2, 'tour_deleted', 'Deleted tour: Rwanda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 10:49:59'),
(18, 2, 'region_deleted', 'Deleted region ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 10:53:34'),
(19, 2, 'country_created', 'Created country: France', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 11:33:52'),
(20, 2, 'tour_created', 'Created new tour: Cultural & Classic Tours', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 11:42:15'),
(21, 2, 'tour_updated', 'Updated tour: Cultural & Classic Tours', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 11:42:29'),
(22, 2, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 12:00:38'),
(23, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 12:00:49'),
(24, 2, 'tour_deleted', 'Deleted tour: Cultural & Classic Tours', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 12:18:51'),
(25, 2, 'country_deleted', 'Deleted country ID: 16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 12:19:54'),
(26, 2, 'country_deleted', 'Deleted country ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 12:20:00'),
(27, 2, 'country_deleted', 'Deleted country ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 12:20:03'),
(28, 2, 'country_deleted', 'Deleted country ID: 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 12:20:06'),
(29, 2, 'country_deleted', 'Deleted country ID: 3', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 12:20:09'),
(30, 2, 'country_deleted', 'Deleted country ID: 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 12:20:12'),
(31, 2, 'country_created', 'Created country: France', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 12:23:57'),
(32, 2, 'region_created', 'Created region: EUROPE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 12:26:47'),
(33, 2, 'region_deleted', 'Deleted region ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 14:49:41'),
(34, 2, 'region_deleted', 'Deleted region ID: 2', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 14:49:52'),
(35, 2, 'country_deleted', 'Deleted country ID: 17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 14:51:20'),
(36, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-27 20:54:40'),
(37, 2, 'blog_post_created', 'Created blog post: 🇷🇼 Rwanda Gorilla Trekking Tour', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-28 00:26:18'),
(38, 2, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-28 09:53:55'),
(39, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-28 09:54:15'),
(40, 2, 'blog_post_created', 'Created blog post: 🇫🇷 France Travel Overview', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-28 16:57:39'),
(41, 2, 'country_created', 'Created country: Rwanda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-28 18:49:23'),
(42, 2, 'country_updated', 'Updated country: Rwanda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-28 18:51:50'),
(43, 2, 'tour_created', 'Created new tour: Visit Rwanda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-28 18:57:17'),
(44, 2, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-29 01:23:56'),
(45, NULL, 'register', 'User registered', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-29 01:31:44'),
(46, NULL, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-29 01:31:55'),
(47, NULL, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-29 01:43:20'),
(48, NULL, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-29 01:43:31'),
(49, NULL, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-29 10:27:04'),
(50, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-29 10:27:28'),
(51, 2, 'region_created', 'Created region: FRANCE FRANCE', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-29 17:26:40'),
(52, 2, 'region_updated', 'Updated region: Provence-Alpes-Côte d’Azur (PACA)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-29 17:29:16'),
(53, 2, 'region_created', 'Created region: BUSINESS', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-29 23:27:26'),
(54, NULL, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-29 23:32:29'),
(55, NULL, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-30 01:13:32'),
(56, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-30 01:14:47'),
(57, 11, 'register', 'User registered', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-30 01:43:36'),
(58, 11, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-30 01:43:51'),
(59, 11, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-30 01:52:42'),
(60, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 01:54:10'),
(61, 2, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-30 01:54:35'),
(62, 11, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-30 01:55:02'),
(63, 11, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-30 01:56:44'),
(64, 11, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-30 01:57:53'),
(65, 11, 'login', 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', NULL, '2025-06-30 02:00:48'),
(66, 11, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1', NULL, '2025-06-30 02:12:08'),
(67, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 05:07:16'),
(68, 2, 'region_deleted', 'Deleted region ID: 9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 11:48:07'),
(69, 2, 'region_deleted', 'Deleted region ID: 9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 11:50:22'),
(70, 2, 'region_created', 'Created region: The Cotswolds', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 11:55:12'),
(71, 2, 'tour_created', 'Created new tour: 🇬🇧 \"Classic England Highlights\" – 8-Day Guided Tour', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 13:37:20'),
(72, 2, 'tour_updated', 'Updated tour: 🇬🇧 \"Classic England Highlights\" – 8-Day Guided Tour', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 13:53:12'),
(73, 2, 'region_created', 'Created region: Visit Rwanda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 13:58:13'),
(74, 2, 'region_updated', 'Updated region: Visit Rwanda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 14:16:00'),
(75, 2, 'country_updated', 'Updated country: Rwanda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 14:18:07'),
(76, 2, 'country_deleted', 'Deleted country ID: 20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 14:18:14'),
(77, 2, 'country_deleted', 'Deleted country ID: 20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 14:18:18'),
(78, 2, 'country_updated', 'Updated country: England', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 14:27:37'),
(79, 2, 'tour_updated', 'Updated tour: 🇬🇧 \"Classic England Highlights\" – 8-Day Guided Tour', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 14:37:04'),
(80, 2, 'tour_updated', 'Updated tour: Visit Rwanda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-06-30 15:29:34'),
(81, 2, 'logout', 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-07-01 07:49:47'),
(82, 2, 'login', 'User logged in successfully', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-07-01 07:50:05'),
(83, 2, 'tour_updated', 'Updated tour: Visit Rwanda', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-07-01 07:51:15');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission` varchar(100) NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `user_id`, `role_id`) VALUES
(1, 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_sessions`
--

INSERT INTO `user_sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`, `expires_at`) VALUES
('fc2fd29c6be588827394a1bb07f912769771ad5b84065b880534bee2a5cd887caaeb13431f43a800ea01e9e96a49af2a8169eeee773d290f0fb73c94e4a4cf5b', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0', NULL, '2025-07-01 13:07:02', '2025-07-01 21:07:02');

-- --------------------------------------------------------

--
-- Table structure for table `user_wishlist`
--

CREATE TABLE `user_wishlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tour_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure for view `revenue_summary`
--
DROP TABLE IF EXISTS `revenue_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `revenue_summary`  AS SELECT cast(`b`.`created_at` as date) AS `booking_date`, count(0) AS `total_bookings`, sum(`b`.`total_amount`) AS `total_revenue`, avg(`b`.`total_amount`) AS `average_booking_value`, count(case when `b`.`status` = 'confirmed' then 1 end) AS `confirmed_bookings`, count(case when `b`.`status` = 'cancelled' then 1 end) AS `cancelled_bookings` FROM `bookings` AS `b` GROUP BY cast(`b`.`created_at` as date) ;

-- --------------------------------------------------------

--
-- Structure for view `tour_performance`
--
DROP TABLE IF EXISTS `tour_performance`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `tour_performance`  AS SELECT `t`.`id` AS `id`, `t`.`title` AS `title`, `t`.`price_adult` AS `price_adult`, count(`b`.`id`) AS `total_bookings`, sum(`b`.`total_amount`) AS `total_revenue`, avg(`b`.`total_amount`) AS `average_booking_value`, count(case when `b`.`status` = 'confirmed' then 1 end) AS `confirmed_bookings`, count(case when `b`.`status` = 'confirmed' then 1 end) * 100.0 / nullif(count(`b`.`id`),0) AS `conversion_rate` FROM (`tours` `t` left join `bookings` `b` on(`t`.`id` = `b`.`tour_id`)) GROUP BY `t`.`id`, `t`.`title`, `t`.`price_adult` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `analytics_daily_summary`
--
ALTER TABLE `analytics_daily_summary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date` (`date`),
  ADD KEY `idx_date` (`date`);

--
-- Indexes for table `analytics_events`
--
ALTER TABLE `analytics_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_name` (`event_name`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_event_category` (`event_category`);

--
-- Indexes for table `analytics_page_views`
--
ALTER TABLE `analytics_page_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_page_url` (`page_url`(255)),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `idx_token_hash` (`token_hash`),
  ADD KEY `idx_user_status` (`user_id`,`status`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `blog_categories`
--
ALTER TABLE `blog_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `blog_comments`
--
ALTER TABLE `blog_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_post_status` (`post_id`,`status`),
  ADD KEY `idx_parent` (`parent_id`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `blog_post_tags`
--
ALTER TABLE `blog_post_tags`
  ADD PRIMARY KEY (`post_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Indexes for table `blog_tags`
--
ALTER TABLE `blog_tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_reference` (`booking_reference`),
  ADD KEY `idx_booking_reference` (`booking_reference`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_tour_id` (`tour_id`),
  ADD KEY `idx_tour_date` (`tour_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_agent` (`agent_id`),
  ADD KEY `idx_bookings_status_date` (`status`,`created_at`),
  ADD KEY `idx_bookings_tour_date` (`tour_date`,`status`),
  ADD KEY `idx_bookings_user_status` (`user_id`,`status`);

--
-- Indexes for table `booking_addons`
--
ALTER TABLE `booking_addons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `addon_id` (`addon_id`);

--
-- Indexes for table `booking_modifications`
--
ALTER TABLE `booking_modifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_modification_type` (`modification_type`);

--
-- Indexes for table `booking_travelers`
--
ALTER TABLE `booking_travelers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`);

--
-- Indexes for table `content_pages`
--
ALTER TABLE `content_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_parent` (`parent_id`),
  ADD KEY `idx_menu_order` (`menu_order`);

--
-- Indexes for table `content_versions`
--
ALTER TABLE `content_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_entity_version` (`entity_type`,`entity_id`,`version_number`),
  ADD KEY `idx_published` (`is_published`,`published_at`);

--
-- Indexes for table `continents`
--
ALTER TABLE `continents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `conversion_funnel`
--
ALTER TABLE `conversion_funnel`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tour_id` (`tour_id`),
  ADD KEY `idx_session_step` (`session_id`,`step_order`),
  ADD KEY `idx_step_name` (`step_name`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_continent` (`continent`),
  ADD KEY `continent_id` (`continent_id`);

--
-- Indexes for table `countries2`
--
ALTER TABLE `countries2`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_valid_dates` (`valid_from`,`valid_until`);

--
-- Indexes for table `coupon_usage`
--
ALTER TABLE `coupon_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `idx_coupon` (`coupon_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `customer_ltv`
--
ALTER TABLE `customer_ltv`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_customer_tier` (`customer_tier`),
  ADD KEY `idx_total_spent` (`total_spent`),
  ADD KEY `idx_last_calculated` (`last_calculated`);

--
-- Indexes for table `customer_segments`
--
ALTER TABLE `customer_segments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_auto_update` (`auto_update`);

--
-- Indexes for table `customer_segment_members`
--
ALTER TABLE `customer_segment_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_segment_user` (`segment_id`,`user_id`),
  ADD KEY `idx_segment_active` (`segment_id`,`is_active`),
  ADD KEY `idx_user_active` (`user_id`,`is_active`);

--
-- Indexes for table `email_campaigns`
--
ALTER TABLE `email_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_scheduled_at` (`scheduled_at`),
  ADD KEY `idx_recipient_type` (`recipient_type`);

--
-- Indexes for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_scheduled_at` (`scheduled_at`),
  ADD KEY `idx_priority` (`priority`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `group_bookings`
--
ALTER TABLE `group_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_leader_id` (`group_leader_id`),
  ADD KEY `idx_tour_date` (`tour_id`,`tour_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `group_booking_members`
--
ALTER TABLE `group_booking_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_booking_group` (`group_booking_id`,`booking_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_group_booking` (`group_booking_id`);

--
-- Indexes for table `itineraries`
--
ALTER TABLE `itineraries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tour_day` (`tour_id`,`day_number`);

--
-- Indexes for table `mca_agents`
--
ALTER TABLE `mca_agents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `agent_code` (`agent_code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_agent_code` (`agent_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_performance_rating` (`performance_rating`);

--
-- Indexes for table `mca_commissions`
--
ALTER TABLE `mca_commissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_agent_id` (`agent_id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `mca_training_modules`
--
ALTER TABLE `mca_training_modules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order` (`order_index`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `mca_training_progress`
--
ALTER TABLE `mca_training_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_agent_module` (`agent_id`,`module_id`),
  ADD KEY `module_id` (`module_id`),
  ADD KEY `idx_agent_status` (`agent_id`,`status`);

--
-- Indexes for table `media_files`
--
ALTER TABLE `media_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`),
  ADD KEY `idx_file_type` (`file_type`),
  ADD KEY `idx_folder_id` (`folder_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `media_folders`
--
ALTER TABLE `media_folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_path` (`path`(255));

--
-- Indexes for table `media_library`
--
ALTER TABLE `media_library`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_file_type` (`file_type`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`),
  ADD KEY `idx_folder` (`folder`);

--
-- Indexes for table `mobile_money_transactions`
--
ALTER TABLE `mobile_money_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `idx_provider_phone` (`provider`,`phone_number`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_subscribed_at` (`subscribed_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_read_at` (`read_at`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `template_id` (`template_id`),
  ADD KEY `idx_status_scheduled` (`status`,`scheduled_at`),
  ADD KEY `idx_recipient` (`recipient_type`,`recipient_id`),
  ADD KEY `idx_priority` (`priority`);

--
-- Indexes for table `notification_templates`
--
ALTER TABLE `notification_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_trigger_event` (`trigger_event`),
  ADD KEY `idx_type_status` (`type`,`status`);

--
-- Indexes for table `office_locations`
--
ALTER TABLE `office_locations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_order_number` (`order_number`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_reference` (`payment_reference`),
  ADD KEY `payment_method_id` (`payment_method_id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_payment_reference` (`payment_reference`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `payment_disputes`
--
ALTER TABLE `payment_disputes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dispute_id` (`dispute_id`);

--
-- Indexes for table `payment_gateways`
--
ALTER TABLE `payment_gateways`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `payment_gateway_logs`
--
ALTER TABLE `payment_gateway_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_gateway` (`payment_id`,`gateway`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gateway_status` (`gateway_id`,`status`);

--
-- Indexes for table `payment_refunds`
--
ALTER TABLE `payment_refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `payment_webhooks`
--
ALTER TABLE `payment_webhooks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gateway_event` (`gateway_name`,`event_type`),
  ADD KEY `idx_processed` (`processed`),
  ADD KEY `idx_event_id` (`event_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_product` (`user_id`,`product_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identifier_endpoint` (`identifier`,`endpoint`),
  ADD KEY `idx_window_start` (`window_start`),
  ADD KEY `idx_blocked_until` (`blocked_until`);

--
-- Indexes for table `refunds`
--
ALTER TABLE `refunds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `refund_reference` (`refund_reference`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_refund_reference` (`refund_reference`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `regions`
--
ALTER TABLE `regions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_country_id` (`country_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_report_type` (`report_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `report_executions`
--
ALTER TABLE `report_executions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `executed_by` (`executed_by`),
  ADD KEY `idx_report_status` (`report_id`,`status`),
  ADD KEY `idx_executed_at` (`executed_at`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tour_id` (`tour_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `saved_reports`
--
ALTER TABLE `saved_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_report_type` (`report_type`),
  ADD KEY `idx_schedule` (`schedule_frequency`,`next_run_at`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `seasonal_pricing`
--
ALTER TABLE `seasonal_pricing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date_range` (`start_date`,`end_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `security_events`
--
ALTER TABLE `security_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_risk_score` (`risk_score`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `seo_metadata`
--
ALTER TABLE `seo_metadata`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_entity_type` (`entity_type`);

--
-- Indexes for table `shopping_cart`
--
ALTER TABLE `shopping_cart`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `sms_messages`
--
ALTER TABLE `sms_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notification_id` (`notification_id`),
  ADD KEY `idx_phone_status` (`phone_number`,`status`),
  ADD KEY `idx_provider_message_id` (`provider_message_id`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `store_categories`
--
ALTER TABLE `store_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `store_orders`
--
ALTER TABLE `store_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `store_order_items`
--
ALTER TABLE `store_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `store_products`
--
ALTER TABLE `store_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_featured` (`featured`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_stock_status` (`stock_status`);
ALTER TABLE `store_products` ADD FULLTEXT KEY `idx_search` (`name`,`description`,`short_description`);

--
-- Indexes for table `support_categories`
--
ALTER TABLE `support_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `idx_user_status` (`user_id`,`status`),
  ADD KEY `idx_ticket_number` (`ticket_number`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `support_ticket_messages`
--
ALTER TABLE `support_ticket_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `support_ticket_replies`
--
ALTER TABLE `support_ticket_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_is_public` (`is_public`);

--
-- Indexes for table `tours`
--
ALTER TABLE `tours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `region_id` (`region_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_featured` (`featured`),
  ADD KEY `idx_country_id` (`country_id`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_price_adult` (`price_adult`),
  ADD KEY `idx_tours_featured_status` (`featured`,`status`),
  ADD KEY `idx_tours_country_category` (`country_id`,`category_id`,`status`);
ALTER TABLE `tours` ADD FULLTEXT KEY `idx_search` (`title`,`short_description`,`full_description`);
ALTER TABLE `tours` ADD FULLTEXT KEY `title` (`title`,`short_description`,`full_description`);

--
-- Indexes for table `tour_addons`
--
ALTER TABLE `tour_addons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tour_id` (`tour_id`);

--
-- Indexes for table `tour_availability`
--
ALTER TABLE `tour_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tour_date` (`tour_id`,`date`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `tour_categories`
--
ALTER TABLE `tour_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `tour_inventory`
--
ALTER TABLE `tour_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tour_date` (`tour_id`,`date`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `tour_itinerary`
--
ALTER TABLE `tour_itinerary`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tour_id` (`tour_id`),
  ADD KEY `idx_day_number` (`day_number`);

--
-- Indexes for table `tour_reviews`
--
ALTER TABLE `tour_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_tour_review` (`user_id`,`tour_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `idx_tour_id` (`tour_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_rating` (`rating`);

--
-- Indexes for table `tour_waitlist`
--
ALTER TABLE `tour_waitlist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tour_date_status` (`tour_id`,`tour_date`,`status`),
  ADD KEY `idx_user_status` (`user_id`,`status`),
  ADD KEY `idx_priority` (`priority_score`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_users_role_status` (`role_id`,`status`);

--
-- Indexes for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_last_activity` (`last_activity`);

--
-- Indexes for table `user_wishlist`
--
ALTER TABLE `user_wishlist`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tour_id` (`tour_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `analytics_daily_summary`
--
ALTER TABLE `analytics_daily_summary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `analytics_events`
--
ALTER TABLE `analytics_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `analytics_page_views`
--
ALTER TABLE `analytics_page_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_tokens`
--
ALTER TABLE `api_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_categories`
--
ALTER TABLE `blog_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `blog_comments`
--
ALTER TABLE `blog_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `blog_tags`
--
ALTER TABLE `blog_tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `booking_addons`
--
ALTER TABLE `booking_addons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_modifications`
--
ALTER TABLE `booking_modifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `booking_travelers`
--
ALTER TABLE `booking_travelers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `content_pages`
--
ALTER TABLE `content_pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `content_versions`
--
ALTER TABLE `content_versions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `continents`
--
ALTER TABLE `continents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `conversion_funnel`
--
ALTER TABLE `conversion_funnel`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `countries2`
--
ALTER TABLE `countries2`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=639;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coupon_usage`
--
ALTER TABLE `coupon_usage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_ltv`
--
ALTER TABLE `customer_ltv`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customer_segments`
--
ALTER TABLE `customer_segments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `customer_segment_members`
--
ALTER TABLE `customer_segment_members`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_campaigns`
--
ALTER TABLE `email_campaigns`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_queue`
--
ALTER TABLE `email_queue`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_bookings`
--
ALTER TABLE `group_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_booking_members`
--
ALTER TABLE `group_booking_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `itineraries`
--
ALTER TABLE `itineraries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mca_agents`
--
ALTER TABLE `mca_agents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mca_commissions`
--
ALTER TABLE `mca_commissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mca_training_modules`
--
ALTER TABLE `mca_training_modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `mca_training_progress`
--
ALTER TABLE `mca_training_progress`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `media_files`
--
ALTER TABLE `media_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `media_folders`
--
ALTER TABLE `media_folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `media_library`
--
ALTER TABLE `media_library`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mobile_money_transactions`
--
ALTER TABLE `mobile_money_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_queue`
--
ALTER TABLE `notification_queue`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_templates`
--
ALTER TABLE `notification_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `office_locations`
--
ALTER TABLE `office_locations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_disputes`
--
ALTER TABLE `payment_disputes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_gateways`
--
ALTER TABLE `payment_gateways`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payment_gateway_logs`
--
ALTER TABLE `payment_gateway_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payment_refunds`
--
ALTER TABLE `payment_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_webhooks`
--
ALTER TABLE `payment_webhooks`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=234;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refunds`
--
ALTER TABLE `refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `regions`
--
ALTER TABLE `regions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `report_executions`
--
ALTER TABLE `report_executions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `saved_reports`
--
ALTER TABLE `saved_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seasonal_pricing`
--
ALTER TABLE `seasonal_pricing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `security_events`
--
ALTER TABLE `security_events`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seo_metadata`
--
ALTER TABLE `seo_metadata`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shopping_cart`
--
ALTER TABLE `shopping_cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_messages`
--
ALTER TABLE `sms_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_categories`
--
ALTER TABLE `store_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_orders`
--
ALTER TABLE `store_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_order_items`
--
ALTER TABLE `store_order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `store_products`
--
ALTER TABLE `store_products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_categories`
--
ALTER TABLE `support_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_ticket_messages`
--
ALTER TABLE `support_ticket_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_ticket_replies`
--
ALTER TABLE `support_ticket_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `tours`
--
ALTER TABLE `tours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `tour_addons`
--
ALTER TABLE `tour_addons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tour_availability`
--
ALTER TABLE `tour_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tour_categories`
--
ALTER TABLE `tour_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `tour_inventory`
--
ALTER TABLE `tour_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tour_itinerary`
--
ALTER TABLE `tour_itinerary`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tour_reviews`
--
ALTER TABLE `tour_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tour_waitlist`
--
ALTER TABLE `tour_waitlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_activity`
--
ALTER TABLE `user_activity`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_wishlist`
--
ALTER TABLE `user_wishlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `analytics_events`
--
ALTER TABLE `analytics_events`
  ADD CONSTRAINT `analytics_events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `analytics_page_views`
--
ALTER TABLE `analytics_page_views`
  ADD CONSTRAINT `analytics_page_views_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD CONSTRAINT `api_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blog_comments`
--
ALTER TABLE `blog_comments`
  ADD CONSTRAINT `blog_comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `blog_comments_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `blog_comments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD CONSTRAINT `blog_posts_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `blog_posts_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `blog_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `blog_post_tags`
--
ALTER TABLE `blog_post_tags`
  ADD CONSTRAINT `blog_post_tags_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `blog_posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `blog_post_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `blog_tags` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_4` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `bookings_ibfk_5` FOREIGN KEY (`agent_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `booking_addons`
--
ALTER TABLE `booking_addons`
  ADD CONSTRAINT `booking_addons_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_addons_ibfk_2` FOREIGN KEY (`addon_id`) REFERENCES `tour_addons` (`id`);

--
-- Constraints for table `booking_modifications`
--
ALTER TABLE `booking_modifications`
  ADD CONSTRAINT `booking_modifications_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_modifications_ibfk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `booking_modifications_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `booking_travelers`
--
ALTER TABLE `booking_travelers`
  ADD CONSTRAINT `booking_travelers_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `content_pages`
--
ALTER TABLE `content_pages`
  ADD CONSTRAINT `content_pages_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `content_pages_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `content_pages` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `content_versions`
--
ALTER TABLE `content_versions`
  ADD CONSTRAINT `content_versions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `conversion_funnel`
--
ALTER TABLE `conversion_funnel`
  ADD CONSTRAINT `conversion_funnel_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `user_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversion_funnel_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `conversion_funnel_ibfk_3` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `coupon_usage`
--
ALTER TABLE `coupon_usage`
  ADD CONSTRAINT `coupon_usage_ibfk_1` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `coupon_usage_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `coupon_usage_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_ltv`
--
ALTER TABLE `customer_ltv`
  ADD CONSTRAINT `customer_ltv_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_segments`
--
ALTER TABLE `customer_segments`
  ADD CONSTRAINT `customer_segments_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `customer_segment_members`
--
ALTER TABLE `customer_segment_members`
  ADD CONSTRAINT `customer_segment_members_ibfk_1` FOREIGN KEY (`segment_id`) REFERENCES `customer_segments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_segment_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_campaigns`
--
ALTER TABLE `email_campaigns`
  ADD CONSTRAINT `email_campaigns_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `email_campaigns_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `email_queue`
--
ALTER TABLE `email_queue`
  ADD CONSTRAINT `email_queue_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `email_templates` (`id`);

--
-- Constraints for table `group_bookings`
--
ALTER TABLE `group_bookings`
  ADD CONSTRAINT `group_bookings_ibfk_1` FOREIGN KEY (`group_leader_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `group_bookings_ibfk_2` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`);

--
-- Constraints for table `group_booking_members`
--
ALTER TABLE `group_booking_members`
  ADD CONSTRAINT `group_booking_members_ibfk_1` FOREIGN KEY (`group_booking_id`) REFERENCES `group_bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `group_booking_members_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `itineraries`
--
ALTER TABLE `itineraries`
  ADD CONSTRAINT `itineraries_ibfk_1` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mca_agents`
--
ALTER TABLE `mca_agents`
  ADD CONSTRAINT `mca_agents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mca_agents_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `mca_commissions`
--
ALTER TABLE `mca_commissions`
  ADD CONSTRAINT `mca_commissions_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `mca_agents` (`id`),
  ADD CONSTRAINT `mca_commissions_ibfk_2` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `mca_commissions_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `mca_training_progress`
--
ALTER TABLE `mca_training_progress`
  ADD CONSTRAINT `mca_training_progress_ibfk_1` FOREIGN KEY (`agent_id`) REFERENCES `mca_agents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `mca_training_progress_ibfk_2` FOREIGN KEY (`module_id`) REFERENCES `mca_training_modules` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `media_files`
--
ALTER TABLE `media_files`
  ADD CONSTRAINT `media_files_ibfk_1` FOREIGN KEY (`folder_id`) REFERENCES `media_folders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `media_files_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `media_folders`
--
ALTER TABLE `media_folders`
  ADD CONSTRAINT `media_folders_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `media_folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `media_folders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `media_library`
--
ALTER TABLE `media_library`
  ADD CONSTRAINT `media_library_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mobile_money_transactions`
--
ALTER TABLE `mobile_money_transactions`
  ADD CONSTRAINT `mobile_money_transactions_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_queue`
--
ALTER TABLE `notification_queue`
  ADD CONSTRAINT `notification_queue_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `notification_templates` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `store_products` (`id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`);

--
-- Constraints for table `payment_disputes`
--
ALTER TABLE `payment_disputes`
  ADD CONSTRAINT `payment_disputes_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`);

--
-- Constraints for table `payment_gateway_logs`
--
ALTER TABLE `payment_gateway_logs`
  ADD CONSTRAINT `payment_gateway_logs_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD CONSTRAINT `payment_methods_ibfk_1` FOREIGN KEY (`gateway_id`) REFERENCES `payment_gateways` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_refunds`
--
ALTER TABLE `payment_refunds`
  ADD CONSTRAINT `payment_refunds_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  ADD CONSTRAINT `payment_refunds_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD CONSTRAINT `payment_transactions_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `product_reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `store_products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `refunds`
--
ALTER TABLE `refunds`
  ADD CONSTRAINT `refunds_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`);

--
-- Constraints for table `regions`
--
ALTER TABLE `regions`
  ADD CONSTRAINT `check_country_exists` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `regions_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `report_executions`
--
ALTER TABLE `report_executions`
  ADD CONSTRAINT `report_executions_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `saved_reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `report_executions_ibfk_2` FOREIGN KEY (`executed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `saved_reports`
--
ALTER TABLE `saved_reports`
  ADD CONSTRAINT `saved_reports_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `security_events`
--
ALTER TABLE `security_events`
  ADD CONSTRAINT `security_events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `shopping_cart`
--
ALTER TABLE `shopping_cart`
  ADD CONSTRAINT `shopping_cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shopping_cart_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `store_products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sms_messages`
--
ALTER TABLE `sms_messages`
  ADD CONSTRAINT `sms_messages_ibfk_1` FOREIGN KEY (`notification_id`) REFERENCES `notification_queue` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `store_categories`
--
ALTER TABLE `store_categories`
  ADD CONSTRAINT `store_categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `store_categories` (`id`);

--
-- Constraints for table `store_orders`
--
ALTER TABLE `store_orders`
  ADD CONSTRAINT `store_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `store_order_items`
--
ALTER TABLE `store_order_items`
  ADD CONSTRAINT `store_order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `store_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `store_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `store_products` (`id`);

--
-- Constraints for table `store_products`
--
ALTER TABLE `store_products`
  ADD CONSTRAINT `store_products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `store_categories` (`id`),
  ADD CONSTRAINT `store_products_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_tickets_ibfk_2` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `support_ticket_messages`
--
ALTER TABLE `support_ticket_messages`
  ADD CONSTRAINT `support_ticket_messages_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_ticket_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_ticket_replies`
--
ALTER TABLE `support_ticket_replies`
  ADD CONSTRAINT `support_ticket_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_ticket_replies_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `tours`
--
ALTER TABLE `tours`
  ADD CONSTRAINT `tours_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  ADD CONSTRAINT `tours_ibfk_2` FOREIGN KEY (`region_id`) REFERENCES `regions` (`id`),
  ADD CONSTRAINT `tours_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `tour_categories` (`id`),
  ADD CONSTRAINT `tours_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `tour_addons`
--
ALTER TABLE `tour_addons`
  ADD CONSTRAINT `tour_addons_ibfk_1` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tour_availability`
--
ALTER TABLE `tour_availability`
  ADD CONSTRAINT `tour_availability_ibfk_1` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tour_inventory`
--
ALTER TABLE `tour_inventory`
  ADD CONSTRAINT `tour_inventory_ibfk_1` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tour_itinerary`
--
ALTER TABLE `tour_itinerary`
  ADD CONSTRAINT `tour_itinerary_ibfk_1` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tour_reviews`
--
ALTER TABLE `tour_reviews`
  ADD CONSTRAINT `tour_reviews_ibfk_1` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tour_reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tour_reviews_ibfk_3` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tour_waitlist`
--
ALTER TABLE `tour_waitlist`
  ADD CONSTRAINT `tour_waitlist_ibfk_1` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tour_waitlist_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `user_activity`
--
ALTER TABLE `user_activity`
  ADD CONSTRAINT `user_activity_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_activity_logs`
--
ALTER TABLE `user_activity_logs`
  ADD CONSTRAINT `user_activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_wishlist`
--
ALTER TABLE `user_wishlist`
  ADD CONSTRAINT `user_wishlist_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_wishlist_ibfk_2` FOREIGN KEY (`tour_id`) REFERENCES `tours` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
