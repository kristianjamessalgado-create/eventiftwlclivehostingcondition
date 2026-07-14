-- Event approval OTP support for organizer legitimacy verification

ALTER TABLE `users`
  ADD COLUMN `organizer_contact_email` varchar(100) NULL AFTER `email`,
  ADD COLUMN `organizer_phone` varchar(25) NULL AFTER `organizer_contact_email`,
  ADD COLUMN `organizer_contact_method` enum('email','phone') NOT NULL DEFAULT 'email' AFTER `organizer_phone`;

CREATE TABLE `event_approval_otps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `organizer_id` int(11) NOT NULL,
  `delivery_method` enum('email','phone') NOT NULL,
  `delivery_target` varchar(120) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_id` (`event_id`),
  KEY `idx_org_id` (`organizer_id`),
  KEY `idx_expires` (`expires_at`),
  CONSTRAINT `event_approval_otps_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_approval_otps_organizer_fk` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_approval_otps_verified_by_fk` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `event_approval_otps_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
