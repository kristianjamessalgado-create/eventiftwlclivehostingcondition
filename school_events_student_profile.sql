-- Optional manual migration if auto-ALTER from PHP is not allowed.
-- Student course, year level, and academic school year (for attendance lists).

ALTER TABLE `users` ADD COLUMN `student_course` VARCHAR(120) NULL DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `student_year_level` VARCHAR(40) NULL DEFAULT NULL;
ALTER TABLE `users` ADD COLUMN `student_academic_year` VARCHAR(20) NULL DEFAULT NULL;
