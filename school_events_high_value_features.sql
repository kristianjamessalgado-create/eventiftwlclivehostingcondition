-- Run in phpMyAdmin on school_events_db (once).
-- RSVP capacity, event feedback, and indexes.

-- Max attendees (RSVP). NULL = unlimited.
ALTER TABLE `events`
  ADD COLUMN `max_capacity` INT UNSIGNED NULL DEFAULT NULL AFTER `location`;

-- Post-event feedback (one per student per event)
CREATE TABLE IF NOT EXISTS `event_feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `comment` text DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_event_user_feedback` (`event_id`,`user_id`),
  KEY `idx_event_feedback_event` (`event_id`),
  CONSTRAINT `event_feedback_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_feedback_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `event_feedback`
  ADD COLUMN `evaluation_json` JSON NULL DEFAULT NULL AFTER `comment`;

-- Multimedia publishing workflow (draft/published photos)
ALTER TABLE `event_photos`
  ADD COLUMN `status` ENUM('draft','published','rejected') NOT NULL DEFAULT 'draft' AFTER `file_path`,
  ADD COLUMN `published_at` DATETIME NULL DEFAULT NULL AFTER `status`,
  ADD KEY `idx_event_photos_event_status` (`event_id`,`status`);

-- QR check-in security: one device per account per event + location audit
CREATE TABLE IF NOT EXISTS `event_checkin_device_locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `device_hash` varchar(128) NOT NULL,
  `first_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_lat` decimal(10,7) DEFAULT NULL,
  `last_lng` decimal(10,7) DEFAULT NULL,
  `last_accuracy` float DEFAULT NULL,
  `last_geo_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_event_device` (`event_id`,`device_hash`),
  KEY `idx_event_user` (`event_id`,`user_id`),
  CONSTRAINT `fk_checkin_lock_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_checkin_lock_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Event venue map coordinates (create/edit location on OpenStreetMap)
ALTER TABLE `events`
  MODIFY `location` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN `latitude` DECIMAL(10,7) NULL DEFAULT NULL AFTER `location`,
  ADD COLUMN `longitude` DECIMAL(10,7) NULL DEFAULT NULL AFTER `latitude`;
