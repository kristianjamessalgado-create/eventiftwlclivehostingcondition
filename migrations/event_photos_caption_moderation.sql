-- Photo caption, credit, and reject reason (safe to re-run: skips existing columns)
-- Run in phpMyAdmin on production if auto-migrate cannot ALTER tables.

ALTER TABLE `event_photos`
  ADD COLUMN IF NOT EXISTS `caption` varchar(255) DEFAULT NULL COMMENT 'Short photo description' AFTER `file_path`;

ALTER TABLE `event_photos`
  ADD COLUMN IF NOT EXISTS `credit_line` varchar(255) DEFAULT NULL COMMENT 'Photographer credit' AFTER `caption`;

ALTER TABLE `event_photos`
  ADD COLUMN IF NOT EXISTS `reject_reason` varchar(500) DEFAULT NULL COMMENT 'Moderator reason when rejected' AFTER `published_at`;
