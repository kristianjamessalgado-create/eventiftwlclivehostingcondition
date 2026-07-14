-- Per-day start time for multi-day events (range or specific days).

ALTER TABLE `event_schedule_dates`

  ADD COLUMN `start_time` time DEFAULT NULL AFTER `schedule_date`;


