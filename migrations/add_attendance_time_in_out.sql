-- Time-in / time-out attendance (optional per event and sub-activity)
ALTER TABLE `events`
  ADD COLUMN IF NOT EXISTS `attendance_mode` enum('single','time_in_out') NOT NULL DEFAULT 'single' AFTER `checkin_end_time`,
  ADD COLUMN IF NOT EXISTS `checkout_end_time` time DEFAULT NULL AFTER `attendance_mode`;

ALTER TABLE `event_day_sessions`
  ADD COLUMN IF NOT EXISTS `attendance_mode` enum('single','time_in_out') NOT NULL DEFAULT 'single' AFTER `checkin_end_time`,
  ADD COLUMN IF NOT EXISTS `checkout_end_time` time DEFAULT NULL AFTER `attendance_mode`;

ALTER TABLE `event_day_session_attendance`
  ADD COLUMN IF NOT EXISTS `checked_out_at` timestamp NULL DEFAULT NULL AFTER `checked_in_at`;
