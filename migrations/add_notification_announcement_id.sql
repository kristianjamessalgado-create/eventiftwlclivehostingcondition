-- Optional: link student bell notifications to admin announcements (also auto-added at runtime).
-- MySQL 8+ / MariaDB may not support IF NOT EXISTS on ADD COLUMN — run once, or rely on PHP ensure.

ALTER TABLE `notifications`
  ADD COLUMN `announcement_id` INT(11) NULL DEFAULT NULL AFTER `event_id`;

ALTER TABLE `notifications`
  ADD KEY `announcement_id` (`announcement_id`);
