-- Draft/publish workflow for event photos (multimedia dashboard).
-- Run once in phpMyAdmin or MySQL after backing up. Safe to re-run only if columns missing.

ALTER TABLE `event_photos`
  ADD COLUMN `status` varchar(20) NOT NULL DEFAULT 'published' COMMENT 'draft|published' AFTER `file_path`,
  ADD COLUMN `published_at` datetime DEFAULT NULL AFTER `status`;
