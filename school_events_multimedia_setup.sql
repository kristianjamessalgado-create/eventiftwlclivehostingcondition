-- Add 'multimedia' role and event photos support for EVENTIFY
-- Run this in phpMyAdmin or MySQL after backing up your database.

-- 1. Add 'multimedia' to users.role enum
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('super_admin','admin','organizer','student','multimedia') NOT NULL;

-- 2. Create table for event photos (uploaded by multimedia users)
CREATE TABLE IF NOT EXISTS `event_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `event_photos_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_photos_user_fk` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
