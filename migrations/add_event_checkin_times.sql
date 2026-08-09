-- Optional attendance window for main-event QR check-in (can differ from event start/end).

ALTER TABLE `events`
  ADD COLUMN `checkin_start_time` time DEFAULT NULL AFTER `end_time_na`,
  ADD COLUMN `checkin_end_time` time DEFAULT NULL AFTER `checkin_start_time`;

ALTER TABLE `event_schedule_dates`
  ADD COLUMN `checkin_start_time` time DEFAULT NULL AFTER `end_time_na`,
  ADD COLUMN `checkin_end_time` time DEFAULT NULL AFTER `checkin_start_time`;
