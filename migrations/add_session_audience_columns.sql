-- Per-activity audience (department + class sections). NULL = inherit parent event audience.

ALTER TABLE `event_day_sessions`
  ADD COLUMN `department` VARCHAR(800) NULL DEFAULT NULL AFTER `title`,
  ADD COLUMN `target_sections` VARCHAR(800) NULL DEFAULT NULL AFTER `department`;
