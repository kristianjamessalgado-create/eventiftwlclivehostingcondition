-- Phase 2–4: categories, notes, status, capacity, contacts, activity check-in, RSVPs.
-- Applied automatically by eventify_event_day_sessions_ensure_enhanced() in PHP.

CREATE TABLE IF NOT EXISTS `event_day_session_rsvps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_user` (`session_id`, `user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `eds_rsvp_session_fk` FOREIGN KEY (`session_id`) REFERENCES `event_day_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `eds_rsvp_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `event_day_session_attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `checked_in_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_user_att` (`session_id`, `user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `eds_att_session_fk` FOREIGN KEY (`session_id`) REFERENCES `event_day_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `eds_att_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
