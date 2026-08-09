-- Optional attendance window for sub-activities (event_day_sessions).
ALTER TABLE `event_day_sessions`
  ADD COLUMN `checkin_start_time` time DEFAULT NULL AFTER `end_time`,
  ADD COLUMN `checkin_end_time` time DEFAULT NULL AFTER `checkin_start_time`;
