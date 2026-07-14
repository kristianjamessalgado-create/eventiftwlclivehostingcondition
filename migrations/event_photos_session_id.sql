-- Link photos to a specific activity (event_day_sessions) for the Activities hub gallery.
-- NULL session_id = event-wide photos (existing multimedia dashboard behavior).

ALTER TABLE `event_photos`
  ADD COLUMN `session_id` INT(11) NULL DEFAULT NULL AFTER `event_id`,
  ADD KEY `idx_event_photos_session` (`session_id`);
