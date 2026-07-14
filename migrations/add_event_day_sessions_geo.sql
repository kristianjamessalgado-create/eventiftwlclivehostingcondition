-- GPS pin for each day activity venue.

ALTER TABLE `event_day_sessions`

  ADD COLUMN `latitude` decimal(10,7) DEFAULT NULL AFTER `location`,

  ADD COLUMN `longitude` decimal(10,7) DEFAULT NULL AFTER `latitude`;


