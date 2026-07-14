-- Multimedia photo moderator (teacher approves before students see photos).
ALTER TABLE `users`
  ADD COLUMN `is_multimedia_moderator` TINYINT(1) NOT NULL DEFAULT 0 AFTER `role`;
