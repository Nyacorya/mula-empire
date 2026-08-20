-- ==========================================
-- Database Schema for Chat Application
-- ==========================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Disable foreign key checks during creation
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------

-- Table structure for table `users`
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) DEFAULT NULL,
  `number` varchar(30) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `status` enum('muted','enabled','suspended') NOT NULL DEFAULT 'enabled',
  `role` enum('user','admin','ceo') NOT NULL DEFAULT 'user',
  `country` enum('Kenya','Uganda','Cameroon','Ivory Coast','South Sudan','Tanzania','Rwanda','Burundi','Malawi','Botswana','Ghana','Nigeria','Others') NULL DEFAULT NULL,
  `icon_url` varchar(500) DEFAULT '/img/user.png',
  `password` varchar(255) DEFAULT NULL,
  `remember_token` varchar(255) DEFAULT NULL,
  `token_expires` datetime DEFAULT NULL,
  `online_status` enum('online','offline','away') NOT NULL DEFAULT 'offline',
  `last_activity` timestamp NULL DEFAULT NULL,
  `theme` enum('dark','light') NOT NULL DEFAULT 'dark',
  `access` varchar(255) DEFAULT NULL COMMENT 'Comma-separated list of site IDs this user can access as admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uk_email` (`email`),
  UNIQUE KEY `uk_number` (`number`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- Table structure for table `sites`
CREATE TABLE `sites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_name` varchar(25) NOT NULL,
  `domain` varchar(25) NOT NULL,
  `status` enum('active','inactive','suspended','blocked') DEFAULT 'active',
  `username` varchar(25) NOT NULL,
  `affiliate_link` text DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `whatsapp_number` varchar(15) DEFAULT NULL,
  `whatsapp_group_link` text DEFAULT NULL,
  `telegram_group_link` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `password` varchar(255) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `session_created` timestamp NULL DEFAULT current_timestamp(),
  `session_expiry` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_site_name` (`site_name`),
  UNIQUE KEY `uk_domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

-- Table structure for table `chats`
CREATE TABLE `chats` (
  `chat_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `site_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`chat_id`),
  UNIQUE KEY `uk_user_admin` (`user_id`,`admin_id`),
  UNIQUE KEY `uk_user_site` (`user_id`,`site_id`),
  KEY `fk_chats_admin` (`admin_id`),
  KEY `idx_site_id` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- Table structure for table `messages`
CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `chat_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message_body` text DEFAULT NULL,
  `message_status` enum('read','delivered','unread') NOT NULL DEFAULT 'unread',
  `deleted` enum('yes','no') NOT NULL DEFAULT 'no',
  `star` enum('yes','no') NOT NULL DEFAULT 'no',
  `pin` enum('0','1','2','3','4','5') NOT NULL DEFAULT '0',
  `notified` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `media_url` varchar(500) DEFAULT NULL,
  `tag_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `idx_chat_id` (`chat_id`),
  KEY `idx_sender_id` (`sender_id`),
  KEY `idx_tag_id` (`tag_id`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_chat_created` (`chat_id`,`created_at`),
  KEY `idx_notified` (`notified`),
  KEY `idx_read_at` (`read_at`),
  KEY `idx_message_status` (`message_status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_chat_status` (`chat_id`,`message_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- Table structure for table `push_subscriptions`
CREATE TABLE `push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `auth_token` varchar(255) NOT NULL,
  `public_key` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_endpoint` (`endpoint`(255)),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

-- Trigger for messages.expires_at default 7 days
DELIMITER $$
CREATE TRIGGER `trg_messages_before_insert` BEFORE INSERT ON `messages` FOR EACH ROW
BEGIN
    IF NEW.expires_at IS NULL THEN
        SET NEW.expires_at = DATE_ADD(NOW(), INTERVAL 7 DAY);
    END IF;
END$$
DELIMITER ;

-- --------------------------------------------------------

-- Foreign key constraints
ALTER TABLE `chats`
  ADD CONSTRAINT `fk_chats_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chats_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_chats_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_chat` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`chat_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_messages_tag` FOREIGN KEY (`tag_id`) REFERENCES `messages` (`message_id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `push_subscriptions`
  ADD CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;