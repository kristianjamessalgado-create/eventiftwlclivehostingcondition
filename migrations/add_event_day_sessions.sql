-- Activities / sub-events on a specific day of a parent event (e.g. intramurals: badminton, volleyball).

CREATE TABLE IF NOT EXISTS `event_day_sessions` (

  `id` int(11) NOT NULL AUTO_INCREMENT,

  `event_id` int(11) NOT NULL,

  `schedule_date` date NOT NULL,

  `title` varchar(150) NOT NULL,

  `location` varchar(255) NOT NULL DEFAULT '',

  `start_time` time DEFAULT NULL,

  `end_time` time DEFAULT NULL,

  `sort_order` int(11) NOT NULL DEFAULT 0,

  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),

  PRIMARY KEY (`id`),

  KEY `event_id` (`event_id`),

  KEY `event_schedule_date` (`event_id`, `schedule_date`),

  CONSTRAINT `event_day_sessions_event_fk` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


