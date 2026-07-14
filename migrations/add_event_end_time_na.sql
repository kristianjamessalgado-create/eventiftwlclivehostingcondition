-- End time on last day (or per schedule day): optional time or "not applicable".
ALTER TABLE `events`
  ADD COLUMN `end_time_na` tinyint(1) NOT NULL DEFAULT 0 AFTER `end_time`;

ALTER TABLE `event_schedule_dates`
  ADD COLUMN `end_time` time DEFAULT NULL AFTER `schedule_date`,
  ADD COLUMN `end_time_na` tinyint(1) NOT NULL DEFAULT 0 AFTER `end_time`;
