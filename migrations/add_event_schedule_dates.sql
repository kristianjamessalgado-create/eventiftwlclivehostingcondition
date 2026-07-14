-- Specific calendar days for an event (e.g. intramurals Mon–Sat, skip Sunday).
CREATE TABLE IF NOT EXISTS `event_schedule_dates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `schedule_date` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_schedule_unique` (`event_id`, `schedule_date`),
  KEY `event_id` (`event_id`),
  KEY `schedule_date` (`schedule_date`),
  CONSTRAINT `event_schedule_dates_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
